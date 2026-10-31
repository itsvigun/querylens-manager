# Connect to the existing local QueryLens

This connects the Manager to the same local PostgreSQL ledger used by QueryLens
at `http://127.0.0.1:8000`. It is distinct from the disposable preview. Existing
invitations/usage are preserved; preview-issued invitations are not imported.

Prerequisites: QueryLens's `querylens-api-1` and `querylens-db-1` containers on
`querylens_default`, pinned Alembic head `0006_post_request_usage`, the built
Manager image/dependencies and an existing private owner `.env.local` from
[setup](setup.md). The explicit owner job refuses unexpected projects/databases,
schema heads or preexisting Manager roles. It is local-only, not a hosting tool.

## One-time provisioning

```sh
python3 tools/connect-local.py
```

The job runs the two reviewed SQL files as one transaction in the existing local
database container, under the shared advisory lock. It asserts that complete
existing invitation/usage rows are unchanged. It creates the NOLOGIN routine
owner, dedicated runtime role, projected views/routines and audit only; Alembic
operations tables are not migrated, recreated or reset.

A new random runtime password is SCRAM-hashed client-side using the existing
Python runtime's libpq connection. Only its verifier reaches SQL. The password
and runtime URL are not displayed or placed in process arguments. The dedicated
URL is saved atomically in `.env.local` with mode 0600; the chosen admin identity
and password hash remain intact. No owner/provider credential is transferred to
the Manager. Re-running provisioning is refused; keep the existing private URL.

## Start or restart

Stop the preview web service if it occupies the same port; its test database and
invitations may remain untouched:

```sh
docker compose -f compose.test.yaml --profile preview stop web
docker compose -f compose.yaml -f compose.local.yaml run --rm app php bin/console cache:clear
docker compose -f compose.yaml -f compose.local.yaml up -d app
```

Always include `compose.local.yaml` for this integration. It attaches only the
Manager web runtime to the existing QueryLens Docker network. It does not move,
restart or replace the Python API/database. The private runtime URL uses Docker's
`db:5432/querylens`; browser links use `http://127.0.0.1:8000` to match Python's
configured public Origin.

Open `http://localhost:8086`, sign in with the existing owner password, and create
a **new invitation**. Its link points to the running local Python UI. Codes from
the old `querylens.test` preview belong to its separate database and remain there.
HTTP links are accepted only for exact localhost/127.0.0.1 hosts in dev/test/local;
production and non-loopback destinations still require HTTPS.

## Verify without an AI request

```sh
docker compose -f compose.yaml -f compose.local.yaml run --rm app php bin/console app:verify-local-integration
```

This explicit local CLI smoke issues one invitation through PHP, checks actual
Python HTTP login/session status/logout, verifies zero questions/cost, and revokes
the verification invitation. It retains a labeled revoked row and sanitized audit;
it never prints or saves the code, session cookie or CSRF token. It refuses
non-development environments and does not call chat/admission/providers. This is
a normal local login smoke, not permission/race fixtures against the shared ledger;
those tests remain restricted to the disposable database.

To stop only this Manager runtime:

```sh
docker compose -f compose.yaml -f compose.local.yaml stop app
```

Do not reset the existing QueryLens database or rerun fixture seed/provisioning
there. Keep test commands under `compose.test.yaml`. Do not run local and preview
web services simultaneously on port 8086. Production/hosted integration still
requires its own owner job, secret management, HTTPS and verification.
