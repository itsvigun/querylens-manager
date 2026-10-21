# Existing QueryLens context

Snapshot date: 2026-10-21. This describes the Python companion, not an implemented
Symfony application.

## Product and portfolio purpose

QueryLens answers business questions over synthetic PostgreSQL data using
documentation retrieval, OpenAI tool calling and validated read-only SQL. Answers
include executed results, SQL, sources, usage and limitations. It is a Backend /
Applied AI portfolio project intended to be explainable in an interview.

The console adds practical PHP/Symfony administrative workflows. Python remains
responsible for the AI/SQL pipeline; PHP manages invitations and presents usage.

## Baseline and source map

Repository: [itsvigun/querylens](https://github.com/itsvigun/querylens).
Inspected revision: [`5f2c38e`](https://github.com/itsvigun/querylens/tree/5f2c38e).
Existing operations migration head: `0006_post_request_usage`.
Local companion location is normally `../querylens`, but an independent clone
must use the upstream links rather than depend on that filesystem layout.

| Upstream path | Why it matters |
|---|---|
| `docs/architecture.md`, `docs/access.md` | Product boundaries and invitation semantics |
| `docs/observability.md`, `docs/deployment.md` | Sanitized audit and operational constraints |
| `scripts/access_tokens.py` | Existing create/update/revoke/list/usage behavior and validation |
| `app/access.py`, `app/api/access.py` | Credential format, sessions, expiry/revocation, CSRF and admission |
| `app/demo_gate.py` | Atomic shared admission and advisory lock |
| `app/db/operations.py` | Canonical metadata for operations tables |
| `migrations/versions/0005_invitation_access.py` | Original invitation schema |
| `migrations/versions/0006_post_request_usage.py` | Post-request cost semantics and legacy migration |
| `scripts/provision_roles.py` | Existing runtime grants; no console role exists yet |
| `tests/test_access.py`, `tests/test_access_integration.py` | Auth, lifecycle, quotas, ACL and recovery contracts |
| `tests/test_ui_browser.py`, `docs/verification.md` | UI access behavior and dated verification evidence |

The optional Ukrainian `QUERYLENS_PLAN.md`, environments, private invitation
files and raw reports belong to the owner's local workspace and are not copied.

## Existing runtime

Python 3.14.8, FastAPI/Uvicorn, SQLAlchemy/Alembic, PostgreSQL 17 with pgvector,
OpenAI Responses/embeddings and bounded LangGraph orchestration. `analytics`,
`knowledge` and `operations` are separate schemas in one PostgreSQL instance.
Seed, migrations, role provisioning and ingestion are explicit jobs, never startup.

Business results use synthetic-v1, EUR and the fixed 2026-10-01 UTC reference date.
Administrative expiry/activity instead use actual UTC time. AI accounting is in
integer micro-USD, distinct from the business dataset's EUR amounts.

## Existing invitation behavior

Owner-issued named opaque codes require no visitor registration. Defaults are 10
lifetime questions, USD 1 known-cost threshold, 2 questions/minute and 30-day
expiry. Existing issued codes may have different limits and must keep them.
One active question per invitation; shared global controls also apply.

No monetary reservation. Full validated usage is recorded after completion;
partial usage is unknown. Cost estimates are not invoices or provider balances.
Expiry/revocation blocks future authentication/admission, including linked browser
sessions, while already admitted work can finish. CLI jobs are outside HTTP quotas.

The Python browser exchanges a code through POST login into a separate HttpOnly
session. It accepts recommended `/#at=<code>` links and compatible `/?at=<code>`
links, scrubbing them before API fetches, including same-page fragment navigation.
The console's admin session must not reuse these visitor credentials or cookies.

## Evidence and limits

The Python app has a Railway deployment with author-confirmed invitation login
and accounting. Its latest recorded local access checks include 54 Chromium
tests and real PostgreSQL login. A bounded hosted CLI sample matched five SQL
tables but passed three of five strict answer/source grades. Full deployment
acceptance and hosted invitation-link acceptance remain unconfirmed in this
snapshot. These facts do not establish console integration or general AI accuracy.

No current deployment, token validity or account balance was checked while
preparing these docs. Reinspect upstream source and actual evidence when work starts.
