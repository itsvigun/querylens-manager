"""Explicit, scoped Railway owner job and runtime-secret provisioning.

Requires owner authorization. Never run from CI or application startup. Existing
operations rows are preserved and provider/owner credentials never enter Manager.
"""

import base64
import json
import os
import re
import secrets
import shlex
import subprocess
from pathlib import Path

from importlib.util import module_from_spec, spec_from_file_location

ROOT = Path(__file__).resolve().parents[1]
PROJECT = "b5a42a05-9ecc-4fb3-8564-87d44ddeeb6f"
ENVIRONMENT = "production"
SERVICE = "querylens-manager"
ORIGIN = "https://querylens-manager-production.up.railway.app"
QUERYLENS = "https://querylens-production-f241.up.railway.app"
PRIVATE = ROOT / ".access-tokens"
SETTINGS = PRIVATE / "railway-manager-secrets.json"
SSH = ["railway", "ssh", "--project", PROJECT, "--environment", ENVIRONMENT, "--service", "db", "--"]
PSQL = 'exec psql -X --quiet -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --tuples-only --no-align'


def run(command, data=None, stage="Railway owner job"):
    result = subprocess.run(command, input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE, cwd=ROOT, timeout=180)
    if result.returncode:
        raise RuntimeError(stage + " failed; private subprocess output omitted.")
    return result.stdout


def write_private(path, content):
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, "w") as file:
        file.write(content)
    path.chmod(0o600)


def main():
    os.umask(0o077)
    PRIVATE.mkdir(mode=0o700, exist_ok=True)
    PRIVATE.chmod(0o700)
    probe_sql = "SELECT json_build_object('database',current_database(),'head',(SELECT version_num FROM public.alembic_version),'manager',EXISTS(SELECT 1 FROM pg_namespace WHERE nspname='manager') OR EXISTS(SELECT 1 FROM pg_roles WHERE rolname IN ('querylens_manager','querylens_manager_owner')));"
    raw = run(SSH + ["sh", "-c", PSQL], probe_sql.encode(), "Hosted database preflight")
    probe = next(json.loads(line) for line in raw.decode().splitlines() if line.startswith('{'))
    if probe['database'] != 'querylens' or probe['head'] != '0006_post_request_usage':
        raise RuntimeError('Refusing an unsupported hosted database/head.')
    if SETTINGS.exists():
        saved = json.loads(SETTINGS.read_text())
    else:
        if probe['manager']:
            raise RuntimeError('Manager objects already exist without this owner job private record.')
        owner_password = secrets.token_urlsafe(32)
        hashed = run(['docker', 'run', '--rm', '-i', 'querylens-manager:production', 'php', '-r', '$p=stream_get_contents(STDIN);echo password_hash($p,PASSWORD_DEFAULT);'], owner_password.encode(), 'Owner password hashing').decode()
        saved = {'owner_password': owner_password, 'runtime_password': secrets.token_urlsafe(32), 'app_secret': secrets.token_hex(32), 'owner_hash': hashed, 'provisioned': False}
        write_private(SETTINGS, json.dumps(saved))

    if not saved['provisioned']:
        if probe['manager']:
            raise RuntimeError('Inspect the already created Manager objects before retrying owner provisioning.')
        backup_command = '''set -eu; umask 077; file=$(mktemp); trap 'rm -f "$file"' EXIT
pg_dump --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --schema operations --format custom --file "$file"
printf '\nMANAGER_BACKUP_BEGIN\n'; base64 "$file"; printf '\nMANAGER_BACKUP_END\n'
'''
        encoded = run(SSH + ['sh', '-c', backup_command], stage='Private operations backup').decode()
        data = base64.b64decode(encoded.split('MANAGER_BACKUP_BEGIN\n', 1)[1].split('MANAGER_BACKUP_END', 1)[0])
        if not data.startswith(b'PGDMP'):
            raise RuntimeError('Private operations backup verification failed.')
        backup = PRIVATE / 'railway-operations-before-manager.dump'
        backup.write_bytes(data)
        backup.chmod(0o600)
        print('Private operations backup captured; no ledger data displayed.')

        # Reuse the pinned local libpq helper; no hosted owner password is fetched.
        specification = spec_from_file_location('local_owner_job', ROOT / 'tools/connect-local.py')
        local = module_from_spec(specification)
        specification.loader.exec_module(local)
        encryption = """import json,sys,psycopg2
from psycopg2.extensions import encrypt_password
from app.config import DemoControlSettings
p=json.load(sys.stdin); s=DemoControlSettings(_env_file=None)
assert s.postgres_host=='db' and s.postgres_db=='querylens'
with psycopg2.connect(user=s.postgres_user,password=s.postgres_password.get_secret_value(),host=s.postgres_host,port=s.postgres_port,dbname=s.postgres_db,connect_timeout=3) as c:
    print(encrypt_password(p['password'],'querylens_manager',c,algorithm='scram-sha-256'))
"""
        verifier = run(['docker', 'exec', '-i', 'querylens-api-1', 'python', '-c', encryption], json.dumps({'password': saved['runtime_password']}).encode(), 'Runtime SCRAM hashing').decode().strip()
        if not re.fullmatch(r'SCRAM-SHA-256\$[0-9]+:[A-Za-z0-9+/=]+\$[A-Za-z0-9+/=]+:[A-Za-z0-9+/=]+', verifier):
            raise RuntimeError('Invalid SCRAM verifier representation.')
        sql = """BEGIN;
SET LOCAL lock_timeout='1s'; SET LOCAL statement_timeout='15s';
SELECT pg_advisory_xact_lock(716202608);
CREATE TEMP TABLE manager_before ON COMMIT DROP AS
SELECT (SELECT md5(coalesce(jsonb_agg(to_jsonb(t) ORDER BY id)::text,'[]')) FROM operations.access_tokens t) AS tokens,
       (SELECT md5(coalesce(jsonb_agg(to_jsonb(u) ORDER BY token_id)::text,'[]')) FROM operations.access_usage u) AS usage;
"""
        sql += local.unwrap_sql(ROOT / 'database/001_read_model.sql')
        sql += local.unwrap_sql(ROOT / 'database/002_administration.sql')
        sql += "\nGRANT CONNECT ON DATABASE querylens TO querylens_manager;\nALTER ROLE querylens_manager LOGIN PASSWORD '" + verifier + "';\n"
        sql += """DO $preserved$
BEGIN
IF (SELECT tokens FROM manager_before) IS DISTINCT FROM (SELECT md5(coalesce(jsonb_agg(to_jsonb(t) ORDER BY id)::text,'[]')) FROM operations.access_tokens t)
 OR (SELECT usage FROM manager_before) IS DISTINCT FROM (SELECT md5(coalesce(jsonb_agg(to_jsonb(u) ORDER BY token_id)::text,'[]')) FROM operations.access_usage u) THEN
 RAISE EXCEPTION 'Existing invitations/accounting changed'; END IF;
END; $preserved$;
COMMIT;
SELECT 'MANAGER_PROVISIONED';
"""
        result = run(SSH + ['sh', '-c', PSQL], sql.encode(), 'Manager schema/role transaction')
        if b'MANAGER_PROVISIONED' not in result:
            raise RuntimeError('Owner job completion could not be confirmed.')
        saved['provisioned'] = True
        write_private(SETTINGS, json.dumps(saved))
        print('Hosted Manager schema/role committed; existing token/usage rows preserved.')

    variables = {
        'APP_ENV': 'prod', 'APP_DEBUG': '0', 'APP_SECRET': saved['app_secret'],
        'ADMIN_USERNAME': 'owner', 'ADMIN_PASSWORD_HASH': saved['owner_hash'],
        'APP_ORIGIN': ORIGIN, 'QUERYLENS_BASE_URL': 'https://${{querylens.RAILWAY_PUBLIC_DOMAIN}}',
        'MANAGER_DATABASE_URL': 'postgresql://querylens_manager:' + saved['runtime_password'] + '@${{db.RAILWAY_PRIVATE_DOMAIN}}:5432/${{db.POSTGRES_DB}}',
        'APP_STATE_DIR': '/data/manager', 'PORT': '8080', 'RAILWAY_DOCKERFILE_PATH': 'Dockerfile.railway',
    }
    for key, value in variables.items():
        run(['railway', 'variable', 'set', key, '--stdin', '--skip-deploys', '--json', '--service', SERVICE, '--environment', ENVIRONMENT, '--project', PROJECT], value.encode(), 'Runtime variable ' + key)
    write_private(PRIVATE / 'railway-manager-owner.txt', 'URL: ' + ORIGIN + '\nUsername: owner\nPassword: ' + saved['owner_password'] + '\n')
    print('Runtime-only Railway variables configured. Owner login is in private .access-tokens/railway-manager-owner.txt.')


if __name__ == '__main__':
    try:
        main()
    except Exception:
        raise SystemExit('Railway owner provisioning failed; private output omitted. Check the last completed safe stage before retrying.') from None
