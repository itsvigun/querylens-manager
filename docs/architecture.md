# Console architecture

Status: local implementation and Railway deployment. HTTPS, owner-session restart
and PHP-issued Python HTTP login are verified without provider execution.
See [decisions](decisions.md) and [verification](verification.md).

## Product and boundaries

The owner signs in, creates a named invitation, privately copies its one-time
link, inspects sanitized usage, and edits lifetime limits/expiry or revokes it.
There is no registration, Company CRUD, public admin API, invitation deletion,
quota reset, provider execution, scheduler or worker.

PHP 8.5 / Symfony 7.4 uses Security, Forms/Validator, Twig and explicit application
services. Doctrine DBAL issues parameterized SQL to a restricted read/routine
boundary. ORM and Doctrine migrations are absent because Alembic owns operations.

```mermaid
flowchart LR
    Owner[Owner browser] --> Console[Symfony Manager]
    Console -->|SELECT views / EXECUTE named routines| Manager[(manager schema)]
    Manager -->|Restricted NOLOGIN owner| Operations[(operations schema)]
    Visitor[Invited visitor] --> Python[Python QueryLens]
    Python -->|Admission / sessions / accounting| Operations
    Python --> AI[AI and read-only analytics]
    Job[Explicit owner job] -->|Alembic first / Manager SQL second| Operations
    Job --> Manager
```

The applications deliberately share a PostgreSQL ledger. This requires coordinated
schema releases and a pinned Python contract, not independent database ownership.
`manager.compatibility` verifies Alembic head on each new runtime connection;
unsupported heads fail closed. No migration, role grant or seed runs on startup.

| Responsibility | Implementation owner |
|---|---|
| Owner login, CSRF, session/attempt limits | Symfony Security and security listeners |
| Forms and operation intentions | Symfony controllers, InvitationOptions, OperationIntents |
| Invitation mutations and atomic audit | Three SECURITY DEFINER routines owned by a NOLOGIN role |
| Existing operations schema | Pinned Python/Alembic owner jobs |
| New manager schema/views/routines/audit | Explicit Manager SQL owner jobs |
| Visitor sessions, admission, concurrency, post-request usage | Python only |
| Read projections and pagination | InvitationReader and restricted SQL views |

All mutations acquire the existing transaction advisory lock `716202608` before
reading state. Paired initial rows, metadata changes and audit commit atomically.
Editing compares a metadata version to reject stale forms. Operation IDs make
successful replays harmless. First revocation time and every usage counter remain
preserved; already admitted Python work can finish after revocation.

The creation response renders plaintext only after commit. Its session contains
public operation IDs and CSRF data only. A replay/lost response cannot recover a
secret and cannot silently issue a second invitation. See [security](security.md).

## Source map

- `src/Security`: owner configuration/provider, login limiter, idle timeout, origin/headers.
- `src/Invitation`: validated options, code/link generation, intentions and DBAL services.
- `src/Controller`, `src/Form`, `templates`: authenticated HTTP workflows and escaped UI.
- `src/Infrastructure`: lazy dedicated-role connection and compatibility guard.
- `database`: explicit, versioned owner jobs; no operations-table DDL.
- `tests`: unit/HTTP, PostgreSQL permission and pinned Python contract checks.

## Hosting and remaining acceptance

Railway serves a dedicated Apache/mod_php production image through its HTTPS
edge, with a single replica and persistent private session/limiter volume.
Manager uses only its restricted PostgreSQL role. Hosted owner login, actual
process restart/session continuity, one-time issuance/replay and Python HTTP
login/session/logout/revoke passed without AI execution. See
[deployment](deployment.md) and [verification](verification.md).

Backup-restore rehearsal, visual fragment-link acceptance in the hosted Python
browser and published sanitized portfolio screenshots are separate remaining
checks. No paid AI accuracy/spending evidence is claimed by the Manager tests.
