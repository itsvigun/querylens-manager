# Railway deployment

Deployment preparation and hosted acceptance are recorded in
[verification](verification.md). Local tests alone are not hosted evidence.
The owner authorized the existing `believable-spontaneity` production project;
the new service is `querylens-manager`, alongside the existing `querylens`, `db`,
`setup` and `ingest`. Only the Manager runtime is deployed/restarted here.

## Runtime

`Dockerfile.railway` installs locked production dependencies and runs PHP 8.5.12
with Apache/mod_php prefork. The ordinary Dockerfile retains local PHP CLI setup.
Only application source and Composer inputs are copied into the production image;
private config, credentials, backups, tests and runtime state are absent.

Railway's edge terminates TLS. The dedicated production Apache configuration maps
the HTTPS scheme server-side; it never trusts arbitrary forwarded hosts/protocols.
Symfony validates the configured canonical origin, emits Secure/HttpOnly/Strict
cookies, no-store/no-referrer/CSP/frame controls and HSTS on application responses.
Access logs are disabled and framework errors retain only fixed event/level/time.
Only the front controller and stylesheet can be served from the public root.

One replica in `iad` uses a separate persistent volume at `/data`. Private admin
sessions and login limiter state are under `/data/manager`, owned by www-data with
0700 directories. Flock protects limiter updates across Apache processes. The
state survives container restarts/redeploys. Do not share this filesystem design
across replicas. Deployments with a Railway volume can have brief downtime.

Startup validates owner configuration and the restricted database/schema, then
warms the cache and starts Apache. It never migrates, seeds, provisions roles,
resets accounting or calls providers. `/health/live` is a public constant-only
liveness response without metadata, sessions or database details; all admin routes
remain protected. Database validation happens before the web server starts.

Official references: [Railway Dockerfiles](https://docs.railway.com/builds/dockerfiles),
[private networking](https://docs.railway.com/networking/private-networking),
[volumes](https://docs.railway.com/volumes/reference),
[Symfony TLS termination](https://symfony.com/doc/7.4/deployment/proxies.html).

## Explicit owner provisioning

`tools/provision-railway.py` is a separately invoked owner job scoped to the
approved project/environment. It uses Railway SSH to the database container;
the owner's database password is never fetched or given to Manager. It verifies
Alembic head, captures a private operations-only pg_dump, runs the two SQL files
in one locked transaction, and compares complete existing token/usage hashes.
No operation table DDL, seed, ingestion, policy change or API restart occurs.

The local pinned Python/libpq helper creates the runtime SCRAM verifier; plaintext
passwords stay out of SQL, command arguments and subprocess output. New admin and
runtime credentials are distinct random values, with only the admin hash sent to
Railway. The setup record and owner login file live under ignored mode-0700
`.access-tokens`, with mode-0600 files. This is a one-time setup/resume tool, not
an ongoing password-rotation tool; preserve its private record.

Only these application variables belong in Manager:

| Variable | Purpose |
|---|---|
| APP_ENV / APP_DEBUG | prod / 0 |
| APP_SECRET | Dedicated random Symfony secret |
| ADMIN_USERNAME / ADMIN_PASSWORD_HASH | Dedicated owner identity/hash |
| APP_ORIGIN | Exact HTTPS Manager domain |
| QUERYLENS_BASE_URL | HTTPS reference to querylens.RAILWAY_PUBLIC_DOMAIN |
| MANAGER_DATABASE_URL | Dedicated querylens_manager credential; references db private domain/database name only |
| APP_STATE_DIR | /data/manager |
| PORT | 8080 |
| RAILWAY_DOCKERFILE_PATH | Dockerfile.railway |

No OpenAI key, owner user/password, reader/writer/control credential or analytics/
knowledge access belongs in this service. References create visible canvas links
to the existing db and QueryLens without copying their sensitive credentials.

## Build and deploy settings

Apply `deploy/railway-manager.graphql` with the exact service/environment IDs
through `railway api --file`; the variables contain no secrets. The GraphQL schema
was inspected before selecting fields. The API Builder enum no longer accepts
DOCKERFILE, so custom Dockerfile detection uses RAILWAY_DOCKERFILE_PATH. Legacy
railway.toml was not retained; CLI reports its end of support on 2026-12-01.
Do not apply project-wide IaC that takes ownership of the existing Python services.

Upload only this repository with the normal Git-ignore behavior; never use
`--no-gitignore`. CLI uploads include the working tree, so record the actual
deployment/source relationship rather than treating a Git hash as proof that
uncommitted files were excluded. Source-linked GitHub deployment should use the
reviewed/published production commit and the same service settings.

## Acceptance and rollback

`tools/check-production-http.py` checks the production Apache image against a
disposable database. Its explicit `--hosted` mode uses the private owner record
to verify HTTPS login/headers/cookies, owner-session persistence through a Manager
restart, one-time creation/replay, Python login/status/logout and audited revoke.
It never invokes chat/admission, writes a full code to disk or dumps responses.
The verification invitation is retained revoked with its audit, not deleted.
Permission/race/general fixtures stay disposable-only; never run those on hosting.

Rollback stops the Manager runtime first. Disable/revoke the dedicated runtime
login/Manager schema access via the owner job if necessary; keep audit and all
operations data. Do not downgrade Alembic or reset tokens/usage to undo this
administrative service. Existing QueryLens can continue independently. Restoring
the private operations backup is a separate owner recovery decision, not an
automatic deployment rollback.
