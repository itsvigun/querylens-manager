#!/bin/sh
set -eu
umask 0077

if [ "${APP_ENV:-}" != prod ] || [ "${APP_STATE_DIR:-}" != /data/manager ]; then
    echo 'Production environment/state configuration is invalid.' >&2
    exit 1
fi
case "${APP_ORIGIN:-}" in
    https://*) ;;
    *) echo 'Production requires a canonical HTTPS APP_ORIGIN.' >&2; exit 1 ;;
esac
case "${QUERYLENS_BASE_URL:-}" in
    https://*) ;;
    *) echo 'Production requires an HTTPS QueryLens destination.' >&2; exit 1 ;;
esac
case "${PORT:-}" in
    ''|*[!0-9]*) echo 'PORT must be numeric.' >&2; exit 1 ;;
esac

# Pin the non-threaded MPM required by mod_php on every supported architecture.
a2dismod mpm_event mpm_worker >/dev/null
a2enmod mpm_prefork >/dev/null
apache2ctl -t

mkdir -p /data/manager/sessions /data/manager/login-limiter /app/var/cache
chmod 700 /data/manager /data/manager/sessions /data/manager/login-limiter
chown -R www-data:www-data /data/manager /app/var
runuser -u www-data -- php bin/console app:check-configuration --no-interaction
runuser -u www-data -- php bin/console app:check-database --no-interaction
runuser -u www-data -- php bin/console cache:warmup --no-interaction
exec apache2-foreground
