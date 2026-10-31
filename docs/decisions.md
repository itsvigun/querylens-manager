# Implementation decisions

Selected on 2026-10-31 after the owner's implementation request. The pinned
QueryLens revision remains `5f2c38e` / `0006_post_request_usage`.

## Stack

PHP 8.5, Symfony 7.4 LTS, Twig, Security, Forms/Validator, Doctrine DBAL 4 and
PHPUnit 12. No ORM or Doctrine schema/migration bundle is installed: the console
must not infer or migrate the existing operations schema. Composer locks exact
resolved packages. The development image uses PHP 8.5.12 with intl, mbstring and
pdo_pgsql, plus PHP's built-in XML, session, ctype, iconv and tokenizer support.

Official compatibility references checked before selection:
[PHP support](https://www.php.net/supported-versions.php),
[Symfony 7.4](https://symfony.com/releases/7.4),
[Symfony requirements](https://symfony.com/doc/7.4/setup.html),
[DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/4.3/reference/introduction.html),
[PHPUnit 12](https://docs.phpunit.de/en/12.5/installation.html).

## Integration and ownership

Use direct PostgreSQL access. A new private administrative HTTP API would add a
second authenticated server surface to upstream without improving the initial
workflow. DBAL uses parameterized statements and no SQL logger/middleware.

Alembic exclusively owns `operations`. The Manager owner job owns a separate
`manager` schema containing projected views, constrained routines and an append-only
administrative audit. That job must verify the pinned upstream migration head
before provisioning. It runs explicitly, never on web startup. A dedicated
NOLOGIN routine owner receives only the operations columns needed for creation,
metadata edits and revocation. The runtime receives view SELECT and named routine
EXECUTE only. It receives no direct operations writes, digest SELECT, analytics,
knowledge, session, lease or global-policy access. No inherited roles, ownership,
DDL or database-owner credentials are allowed in the web container.

Routines use fixed `pg_catalog` search paths and qualified relations, validate
inputs and use `pg_advisory_xact_lock(716202608)` before reading current state or
writing. Transactions commit initial token/usage rows and administrative audit
together. Lock/statement timeouts bound waits. Counter changes remain Python-owned.
All expiry calculations use `clock_timestamp()` after acquiring the shared lock.

## Owner access and hosting policy

One privately provisioned username/password hash. Symfony sessions rotate at
login and are invalidated by logout and password changes. The owner-wide login
limiter persists in the local filesystem across PHP processes; no IP trust or
client-controlled username can bypass it. Five failed attempts per 15 minutes
also bound attempts against nonexistent usernames. Login and POST mutations use
session CSRF tokens. Logout is POST with CSRF.

Single-instance hosting is the initial policy. Sessions live under private
`var/sessions`, with a 30-minute inactivity limit; no shared visitor cookie. Deploy
with HTTPS and a configured canonical origin, host-only Secure/HttpOnly/SameSite
cookies, no debug/profiler, no third-party assets, no request/body/SQL logging.
Host/reverse-proxy TLS must preserve the canonical request scheme; arbitrary
forwarded headers are not trusted. Restart with ephemeral sessions signs the
owner out; login limiter storage must survive restarts. Multiple replicas require
an explicit shared session/limiter design first. Hosted restart/session behavior
is now verified; see the dated verification record.

## One-time delivery and replay policy

The intended issuance ID and CSRF token may live in the admin session; plaintext
invitation credentials may not. A POST generates the secret in memory, persists
only its digest, and renders the authenticated no-store/no-referrer response only
after commit. There is no secret-bearing redirect, flash, session or log record.
An issuance ID identifies at most one invitation. A replay returns a safe receipt
without its secret and cannot silently create another invitation. If a committed
response is lost, the secret is unrecoverable: inspect the list, revoke that
invitation and explicitly create another. A failed transaction may be retried
using the same issuance intent. Editing uses an expected metadata version to
reject stale forms; edit/revoke operation IDs make successful replays harmless.

## Verification topology

Unit and HTTP foundation tests run with no database URL or paid keys. Real-DB
tests use a separately named disposable PostgreSQL 17 service without published
ports. Cross-language tests import the pinned Python source and exercise its
actual credential/session/admission code with synthetic/offline receipts. They
must not load either repository's private environment or use a hosted ledger.

Provisioning, rollback and deployment commands belong in the setup/operations
guides once implemented and exercised. Hosted acceptance and paid AI execution
require separate authorization/evidence.

## Shared local development connection

After the owner's explicit connection request on 2026-10-31, added a local Compose
override and owner job for the already running QueryLens ledger. The job preserves
all prior token/usage rows and existing admin identity, creates a new runtime-only
SCRAM credential, and installs Manager schema/grants transactionally. See
[local integration](local-integration.md). Preview fixtures are not copied.
The link builder allows exact loopback HTTP only in dev/test/local to match
Python's configured public Origin; production/remote HTTPS rules remain enforced.


## Production serving and state

Railway deployment was separately authorized on 2026-10-31. Production uses a
PHP 8.5.12 Apache/mod_php prefork image with no access logs, explicit HTTPS mapping
at the server boundary, a canonical Host check and a constant-only liveness route.
No forwarded host/protocol header can override application authority. Session and
limiter files use one persistent /data volume; code/cache are container-local.
Startup checks configuration/restricted DB compatibility without provisioning.
Custom Dockerfile/API service settings avoid the deprecated railway.toml format.
