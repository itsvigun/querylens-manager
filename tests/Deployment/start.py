"""Start the production image against the disposable test database for HTTP CI."""

import secrets
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[2]
password = 'offline-production-smoke-password'
hashed = subprocess.run(['docker', 'run', '--rm', '-i', 'querylens-manager:production', 'php', '-r', '$p=stream_get_contents(STDIN);echo password_hash($p,PASSWORD_DEFAULT);'], input=password.encode(), stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True).stdout.decode()
environment = root / 'artifacts/production-http.env'
environment.parent.mkdir(exist_ok=True)
values = {
    'APP_ENV': 'prod', 'APP_DEBUG': '0', 'APP_SECRET': secrets.token_hex(32),
    'ADMIN_USERNAME': 'owner', 'ADMIN_PASSWORD_HASH': hashed,
    'APP_ORIGIN': 'https://manager.test', 'QUERYLENS_BASE_URL': 'https://querylens.test',
    'MANAGER_DATABASE_URL': 'postgresql://querylens_manager:disposable-manager@database/manager_test',
    'PORT': '8080', 'APP_STATE_DIR': '/data/manager',
}
environment.write_text(''.join(key + '=' + value + '\n' for key, value in values.items()))
environment.chmod(0o600)
started = subprocess.run(['docker', 'run', '--rm', '-d', '--name', 'querylens-manager-production-smoke-v2', '--env-file', str(environment), '--network', 'querylens-manager-tests_default', '-p', '127.0.0.1:8087:8080', '-v', 'querylens-manager-production-smoke:/data', 'querylens-manager:production'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
if started.returncode:
    raise SystemExit('Disposable production container could not start; inspect test resources without displaying private environment values.')
print('Production image started with synthetic owner/runtime configuration only.')
