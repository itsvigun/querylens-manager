# Security boundaries

Implemented and tested locally; hosting controls still require separate evidence.
See [verification](verification.md) for dated results.

## Owner access

One privately configured username/password hash; no registration or visitor-token
admin login. Every application route except login requires ROLE_ADMIN. Login,
logout and mutations use CSRF; logout/revocation are POST only. Symfony migrates
the session at login. Password changes invalidate existing sessions on refresh.
A session listener enforces 30 minutes without activity. The owner-wide limiter
allows five failed attempts per 15 minutes, independent of supplied username/IP;
its filesystem state is protected by a process-shared flock.

The host-only `qlm_admin` cookie is HttpOnly, SameSite=Strict and Secure on HTTPS.
Loopback HTTP is permitted for local development. The configured canonical origin
must match the actual request scheme/host. Untrusted Host or forwarded headers do
not determine invitation URLs. All names render literally through Twig escaping.
The chosen single-instance session/hosting policy is in [decisions](decisions.md).

## Runtime permissions

The only accepted database login is `querylens_manager`, without ownership,
memberships or administrative role flags. It has SELECT on projected manager
views and EXECUTE on three validated routines, with no operations schema USAGE.
The routines have a fixed pg_catalog search path and a dedicated NOLOGIN owner
with narrowly granted columns. They acquire Python's existing lock and record
sanitized audit inside the same transaction. Runtime lock/statement waits are
bounded; failed permission, connection and schema checks return safe errors.

No direct token/digest reads, existing usage UPDATE, session/lease mutation,
global-policy edit, analytics/knowledge access, DDL or owner role switching is
allowed. Negative permissions are tested against actual PostgreSQL. Alembic owns
existing tables; the separate Manager SQL owner job owns manager objects only.
Do not place provider keys, database-owner credentials or ingestion credentials
in the console environment. Test-runner owner URLs belong to isolated fixture
processes, never the web/preview service.

## One-time secrets

Generate 256 secret bits with random_bytes and hash the complete compatible ASCII
credential with SHA-256. Only the digest and safe metadata persist. The secret
is rendered in the authenticated 201 response after commit; it is never placed
in redirects, sessions, flashes, logs, profiling or browser storage by the app.
All application responses use no-store, no-referrer, a restrictive CSP, frame
blocking and no third-party assets. HTTPS responses also send HSTS.

The browser necessarily receives a copy. Back navigation may redisplay a previous
response in browser memory; no application can promise to erase a copied code.
Refreshing/resubmitting cannot retrieve it from the server. Session-bound public
operation IDs prevent another issuance on replay. A lost committed response
requires inspecting the invitation, revoking it and explicitly issuing a new one.
Expired form intentions require reloading current metadata; they never create a
new invitation automatically. Browser screenshots/password managers/clipboard
retention remain under the owner's control.

Links use a configured trusted HTTPS origin with `/#at=`. Exact localhost or
127.0.0.1 HTTP origins are allowed only in dev/test/local for the explicit
[local integration](local-integration.md); production rejects that exception.
No secret-bearing
query parameter is generated. Labels do not establish the identity of a person
using a forwarded code. Only Python issues visitor sessions or admits questions.

## Audit, logging and retention

Audit stores operation ID, fixed create/update/revoke action, configured owner,
invitation ID, UTC time and approved limit/expiry metadata; updates also record
whether the label changed. Label values, digests, full codes, passwords, questions,
SQL/results and raw request/usage JSON are excluded. The runtime cannot edit,
delete or read audit. Retention/export belongs to an owner job; rollback preserves
it. Console browsing never counts as an AI question.

Framework diagnostic logging intentionally retains only a fixed event, severity
and UTC time. It discards exception messages, URLs and arbitrary context; DBAL
exceptions are replaced without chaining credential/parameter-bearing errors.
Hosting/proxy/database logging must be reviewed separately, especially bind
parameters containing invitation digests. Disable request bodies, SQL parameters,
debug/profiling and third-party telemetry in the production path.

Only synthetic fixtures are used locally. Test-generated codes flow through memory
pipes, not files or test reports. No hosted ledger, paid call, deployment or real
credential transfer was performed or authorized by the implementation work.
