# Local setup and checks

Use Docker Engine/Desktop with Compose v2. The host does not need PHP or Composer.
These are local development instructions; PHP's built-in server is not production
hosting. Commands run from the repository root.

```sh
docker compose build --pull
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app php tools/configure.php
docker compose run --rm app php bin/console app:check-configuration
```

Open `http://localhost:8086`, sign in as `owner` using the password chosen during
configuration. `.env.local` is created with private permissions. The tool refuses
overwriting an existing file. The password itself is never written to disk.
If QueryLens is already running locally at `http://127.0.0.1:8000`, follow
[connect the existing local services](local-integration.md) to provision the
dedicated role and start Manager on its Docker network. This produces usable
local invitation links. The disposable preview below uses a separate database.
Do not put production credentials into development or tests. Other destinations
require a configured trusted HTTPS `QUERYLENS_BASE_URL` before issuance.

```sh
docker compose run --rm app composer validate --strict
docker compose run --rm app composer lint
docker compose run --rm app composer test
docker compose stop app
```

The unit/HTTP suite supplies synthetic configuration, ignores private `.env.local`,
has no database connection by default and requires no provider key. Lint compiles
the container but does not instantiate a database connection. The configuration
check validates private owner settings without displaying them or contacting a
database. Missing settings fail explicitly; HTTP error pages are generic.

Without a database URL, owner login works and data screens return a safe 503.
For a complete offline preview, stop the app service, follow the disposable
[database checks](database.md), then run:

```sh
docker compose -f compose.test.yaml --profile preview up -d web
```

This uses your privately configured owner identity, a dedicated synthetic runtime
role and an explicitly labeled disposable dataset. Its example invitation links
point to `https://querylens.test`; use the cross-language verifier rather than
opening these offline links. No production/provider connection is involved.
Do not run the ordinary app and preview simultaneously on port 8086. Preview
uses the existing local .env.local owner configuration; configure it first.

```sh
python3 tools/check-contract.py
python3 tools/check-fresh.py
docker compose -f compose.test.yaml --profile preview down --volumes
```

The fresh-setup check copies allowlisted source files into a temporary directory,
installs composer.lock from scratch and runs validation/lint/no-DB tests without
private environments. Never supply a database-owner connection to the web app.

For password rotation, generate a new hash interactively with Symfony's
`security:hash-password` command and privately replace `ADMIN_PASSWORD_HASH`.
Clear `var/cache` when changing configuration and restart the app. Existing
authenticated sessions are compared against the current hash on their next use.
Never paste real passwords into shell command arguments or commit private config.
