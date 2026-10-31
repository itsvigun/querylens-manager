"""Explicit owner job: provision Manager on the existing local QueryLens ledger.

No owner/provider secret is read or transferred to the console. SQL, SCRAM
verifiers and private configuration are passed through captured memory pipes.
"""

import json
import os
import re
import secrets
import subprocess
import tempfile
from pathlib import Path
from urllib.parse import quote

ROOT = Path(__file__).resolve().parents[1]
DATABASE_CONTAINER = "querylens-db-1"
API_CONTAINER = "querylens-api-1"
OWNER_CLIENT = [
    "docker", "exec", "-i", DATABASE_CONTAINER, "sh", "-c",
    'exec psql -X --quiet -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --tuples-only --no-align',
]


def run(command, data=None, stage="local owner job"):
    result = subprocess.run(command, input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE, cwd=ROOT)
    if result.returncode:
        # Never forward exception/SQL/environment output containing private values.
        raise RuntimeError(stage + " failed; no private subprocess output was displayed.")
    return result.stdout


def inspect_labels(container):
    labels = json.loads(run(["docker", "inspect", "--format", "{{json .Config.Labels}}", container], stage="Container verification"))
    expected = "db" if container == DATABASE_CONTAINER else "api"
    if labels.get("com.docker.compose.project") != "querylens" or labels.get("com.docker.compose.service") != expected:
        raise RuntimeError("Refusing a container outside the expected local QueryLens Compose project.")


def unwrap_sql(path):
    source = path.read_text()
    # Only remove the two known outer transaction lines, never PL/pgSQL blocks.
    source, begins = re.subn(r"(?m)^BEGIN;$", "", source, count=1)
    source, commits = re.subn(r"(?m)^COMMIT;$", "", source, count=1)
    if begins != 1 or commits != 1:
        raise RuntimeError("Owner SQL must have exactly one outer transaction wrapper.")
    return source


def replace_setting(source, key, value):
    line = key + "='" + value + "'"
    updated, replacements = re.subn(r"(?m)^" + re.escape(key) + r"=.*$", lambda _: line, source)
    return updated if replacements else source.rstrip() + "\n" + line + "\n"


def main():
    for container in [DATABASE_CONTAINER, API_CONTAINER]:
        inspect_labels(container)
    configuration = ROOT / ".env.local"
    if not configuration.is_file():
        raise RuntimeError("Configure the private owner identity with tools/configure.php first.")
    probe = json.loads(run(OWNER_CLIENT, b"SELECT json_build_object('database',current_database(),'head',(SELECT version_num FROM public.alembic_version),'already_provisioned',EXISTS(SELECT 1 FROM pg_roles WHERE rolname IN ('querylens_manager','querylens_manager_owner')) OR EXISTS(SELECT 1 FROM pg_namespace WHERE nspname='manager'));\n"))
    if probe["database"] != "querylens" or probe["head"] != "0006_post_request_usage":
        raise RuntimeError("Refusing an unexpected local database or migration head.")
    if probe["already_provisioned"]:
        raise RuntimeError("Manager is already provisioned. Keep its existing private URL and use the documented local start command.")

    password = secrets.token_urlsafe(32)
    encryption = """import json,sys,psycopg2
from psycopg2.extensions import encrypt_password
from app.config import DemoControlSettings
p=json.load(sys.stdin)
s=DemoControlSettings(_env_file=None)
assert s.postgres_host == 'db' and s.postgres_db == 'querylens'
with psycopg2.connect(user=s.postgres_user,password=s.postgres_password.get_secret_value(),host=s.postgres_host,port=s.postgres_port,dbname=s.postgres_db,connect_timeout=3) as connection:
    print(encrypt_password(p['password'],'querylens_manager',connection,algorithm='scram-sha-256'))
"""
    verifier = run(["docker", "exec", "-i", API_CONTAINER, "python", "-c", encryption], json.dumps({"password": password}).encode(), stage="SCRAM verifier preparation").decode().strip()
    if not re.fullmatch(r"SCRAM-SHA-256\$[0-9]+:[A-Za-z0-9+/=]+\$[A-Za-z0-9+/=]+:[A-Za-z0-9+/=]+", verifier):
        raise RuntimeError("Unexpected SCRAM verifier format.")
    private = configuration.read_text()
    private = replace_setting(private, "MANAGER_DATABASE_URL", "postgresql://querylens_manager:" + quote(password, safe="") + "@db:5432/querylens")
    private = replace_setting(private, "QUERYLENS_BASE_URL", "http://127.0.0.1:8000")
    private = replace_setting(private, "APP_ORIGIN", "http://localhost:8086")

    # PostgreSQL scripts and all checks are committed together under Python's lock.
    sql = """BEGIN;
SET LOCAL lock_timeout='1s';
SET LOCAL statement_timeout='15s';
SELECT pg_advisory_xact_lock(716202608);
CREATE TEMP TABLE manager_before ON COMMIT DROP AS
SELECT (SELECT md5(coalesce(jsonb_agg(to_jsonb(t) ORDER BY id)::text,'[]')) FROM operations.access_tokens t) AS tokens,
       (SELECT md5(coalesce(jsonb_agg(to_jsonb(u) ORDER BY token_id)::text,'[]')) FROM operations.access_usage u) AS usage;
"""
    sql += unwrap_sql(ROOT / "database/001_read_model.sql")
    sql += unwrap_sql(ROOT / "database/002_administration.sql")
    sql += "\nGRANT CONNECT ON DATABASE querylens TO querylens_manager;\n"
    sql += "ALTER ROLE querylens_manager LOGIN PASSWORD '" + verifier + "';\n"
    sql += """DO $preserved$
BEGIN
    IF (SELECT tokens FROM manager_before) IS DISTINCT FROM
       (SELECT md5(coalesce(jsonb_agg(to_jsonb(t) ORDER BY id)::text,'[]')) FROM operations.access_tokens t)
       OR (SELECT usage FROM manager_before) IS DISTINCT FROM
       (SELECT md5(coalesce(jsonb_agg(to_jsonb(u) ORDER BY token_id)::text,'[]')) FROM operations.access_usage u) THEN
        RAISE EXCEPTION 'Existing invitation or accounting data changed';
    END IF;
END;
$preserved$;
COMMIT;
"""
    descriptor, temporary = tempfile.mkstemp(prefix=".env.local.pending-", dir=ROOT)
    try:
        with os.fdopen(descriptor, "w") as file:
            file.write(private)
        os.chmod(temporary, 0o600)
        run(OWNER_CLIENT, sql.encode(), stage="Schema and role provisioning")
        os.replace(temporary, configuration)
        os.chmod(configuration, 0o600)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)
    print("Local Manager role and schema provisioned; existing invitations/usage preserved. Private runtime URL saved without displaying credentials.")


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, OSError, ValueError) as error:
        raise SystemExit(str(error)) from None
