# QueryLens integration baseline

Status: implemented locally and on Railway with DBAL, restricted views and
constrained routines. Disposable cross-language checks and bounded hosted HTTP
login evidence are recorded separately in verification.md.
Upstream revision: `5f2c38e`; reinspect source before implementing against a newer
revision. Existing schema ownership stays with Alembic.

## Existing data

| Relation | Purpose | Intended console access |
|---|---|---|
| `operations.access_tokens` | ID, digest, label, dates, lifetime/rate limits | Projected reads; constrained create/edit/revoke |
| `operations.access_usage` | Admitted count, known cost, unknown count, activity/rate counters | Projected reads; initial row INSERT only when issuing |
| `operations.access_requests` | Request IDs, dates, state, cost and sanitized summary | Allowlisted read-only usage report |
| `operations.access_sessions` | Python visitor-session digests and expiry | No secret/digest access or session mutation needed |
| `operations.demo_policy` | Accounting/admission configuration fingerprint | No edit |
| `operations.demo_months` | Shared monthly usage and legacy archived reserves | Outside initial console editing |
| `operations.demo_leases` | Durable active-question concurrency | No recovery, deletion or mutation |

Exact grants and constrained routines are in database/001_read_model.sql and
database/002_administration.sql; owner-job sequencing is in database.md. Avoid
blanket table access: determine views/column grants/routines and test negative
privileges. Existing Python API credentials cannot issue/revoke/edit invitations;
do not reuse them or a database owner password for the console.

## Credential compatibility

- Invitation: `qlt_<32 lowercase hex identifier>_<43 base64url secret characters>`.
- Total invitation length: 80 ASCII characters. The ID is public metadata, not a
  credential. The secret contains 256 random bits.
- Generate with a cryptographically secure source. UUID identifier and secret
  generation must match Python's accepted representation.
- Persist the lowercase hexadecimal SHA-256 digest of the full ASCII credential,
  not the secret alone. Never persist the full code or fragment link.
- Python uses a separate `qls_...` visitor-session format; Symfony does not issue
  those sessions or reuse them for administrative authentication.
- Build the invitation URL from a configured trusted HTTPS QueryLens base URL
  (or explicit development-only loopback HTTP for the shared local integration),
  using `/#at=<credential>`. Never construct it from an untrusted Host header.
- Secret presentation must be one-time, owner-authenticated, no-store/no-referrer
  and absent from logs/profiling/telemetry. The browser must receive it for copy;
  this does not authorize persistent storage. Lost codes require new issuance.

## Input contract at the baseline revision

| Input | Default | Accepted values |
|---|---|---|
| Name | Required | Trimmed, printable, nonblank, at most 128 characters |
| Lifetime question total | 10 | Integer 1–10000 |
| Known-cost USD threshold | 1 | Decimal 0–100, at most six decimal places |
| Questions/minute | 2 | Integer 1–60 |
| Expiry interval | 30 days | Integer 1–365 days |

Use exact decimal/integer conversion: USD 1 equals 1000000 micro-USD. Do not use
floating point for monetary validation/storage. Existing money counters are
BIGINT; preserve precision through database reads, formatting and serialization.
Unicode name semantics must be tested against Python rather than approximated by
an ASCII-only validator. All administrative times are actual timezone-aware UTC.

## Lifecycle and transactions

Creation writes an invitation and its initial zeroed usage row in one transaction.
Only show the full secret after commit succeeds. Updating limits changes lifetime
totals, not a fresh allowance; admitted count, known/unknown cost, rate counters,
dates and identity must be preserved. Remaining questions are bounded below by
zero. Editing a limit below current use must not refund historical use.

At the baseline, changing expiry uses database current time plus the submitted
interval, not the old expiry. Revocation preserves the first revocation timestamp.
The existing CLI rejects updates to revoked invitations. Do not add reactivation,
deletion, quota reset or digest rotation behind an ordinary edit operation.

The Python owner CLI, session operations and admission currently coordinate using
the transaction-scoped PostgreSQL advisory lock with key `716202608`. A direct-DB
adapter must participate in that same protocol, including atomic initial rows and
revocation/limit changes. Do not independently invent a row-lock scheme and assume
it coordinates with Python. Keep transactions short and bound lock wait time.
Administrative audit must commit with each mutation, or the mutation must roll back.

Admitted requests keep their consumed question and may finish after revocation.
Expiry/revocation applies to later authentication/admission and linked Python
browser sessions. Do not delete sessions or active leases to simulate revocation.
Do not run schema changes, seed or ingestion when opening the console.

## Usage presentation

The Python service alone atomically enforces lifetime questions, soft known-cost
thresholds, rate limits, one active question and shared global controls. Symfony
must not reserve money, debit estimated maximum charges or maintain a competing
usage counter. USD thresholds can be crossed by the final admitted question;
unknown usage is excluded from known cost. An allowance is not an OpenAI balance.

| Request state | Display meaning |
|---|---|
| `recorded` | Complete validated usage with a non-null estimated micro-USD cost |
| `unknown` | Incomplete/interrupted usage; cost is NULL, not zero |
| `pending` | Admitted request without completed accounting yet |
| `not_admitted` | Rejected before admission; no recorded request charge |
| `legacy` | Historical record without reconstructable measured cost |

Historical `reserved_micro_usd` and `legacy_reserved_micro_usd` are not current
available funds or new charges. Do not count them against the post-request model.
`first_used_at`/`last_used_at` describe admitted questions, not page views/logins.
The intended company label cannot prove which person used a forwarded invitation.

Use paginated allowlisted projections of request ID/time, outcome/error category,
known model/counter fields, usage state and estimated cost. Do not expose raw JSON,
digests, headers, credentials, question text, SQL/results or retrieved documents.

## Compatibility acceptance required

Use a fresh migrated/provisioned disposable PostgreSQL, never the production ledger.
Prove PHP issuance -> Python login, preserved counters through edit, revoke/expiry
with linked sessions, overlapping administration/admission, double submission,
digest compatibility, exact money and allowed/denied role permissions. Use labeled
offline providers where a request receipt is needed; no paid OpenAI key is required.
Baseline test locations are listed in [QueryLens context](querylens-context.md).
