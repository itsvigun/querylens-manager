# Verification record

This record distinguishes completed documentation checks from future application
acceptance. Planned behavior is described in PLAN.md and the topic guides.

## 2026-10-21 — documentation-only preparation

Created a separate sibling directory, `querylens-manager`, with README, PLAN,
AGENTS, ignore rules and topic documentation. Inspected the existing QueryLens
source at `5f2c38e`, including invitation issuance/update/revocation, credential
format, schema metadata, locking, accounting and latest verification evidence.

Checked all 10 files, 13 local Markdown links, milestone markers,
Git-ignore behavior for private/runtime paths and absence of complete invitation
credentials or matching local secrets. Re-read the generated files. The directory
contains only Markdown documentation and `.gitignore`, with no `.git` directory.

No Composer/package installation, PHP/Symfony runtime, application code, migration,
test suite, database/provider request, deployment or Git initialization was run.
There are therefore no application test results or working setup commands yet.
The Python application and its tracked repository files were not changed.

Future evidence belongs here after actual implementation: exact versions and
commands, unit/HTTP checks, real PostgreSQL compatibility/ACL/race results,
fresh setup, CI and hosted smoke. Preserve the distinction between offline/stub
fixtures, database behavior and live provider quality/cost.

## 2026-10-21 — owner-selected project name

Renamed the directory and project headings to QueryLens Manager
(`querylens-manager`). Verified that all 10 files were preserved with only the
intended name substitutions before appending this record, and that all local
Markdown links still resolve. No application work or Git initialization occurred.

## 2026-10-21 — local plan exclusion

Added `/PLAN.md` to the existing `.gitignore` at the owner's request. Confirmed
that `PLAN.md` is not tracked and `git check-ignore -v PLAN.md` matches this rule.
The plan remains available locally. No commit or push was performed.

## 2026-10-21 — initial publication preparation

Confirmed that Git is initialized on `main` with no commits and `origin` pointing
to `https://github.com/itsvigun/querylens-manager.git`. The owner authorized the
initial commit and push while keeping `PLAN.md` ignored. Updated the README's Git
status and removed published Markdown links to the local-only plan.

Checked all 9 publication files and 11 local Markdown links: no broken links,
links to the ignored plan or matching private-key/invitation/API-key patterns.
Confirmed that `git check-ignore -v PLAN.md` matches `/PLAN.md`.

The staging attempt was blocked by the execution environment: Git could not create
`.git/index.lock` (`Operation not permitted`). No files were staged, no commit was
created and no push was attempted. Publication remains pending execution from an
environment that permits Git metadata writes.

Application implementation, database access and deployment remain unimplemented.


## 2026-10-31 — milestone 1 foundation

The owner authorized implementation. Re-read the pinned upstream `5f2c38e` source
for credential generation/authentication, CLI validation/mutations, operations
metadata, admission locking, observability and role provisioning. No upstream
file or hosted service was changed. Recorded decisions in decisions.md.

Selected PHP 8.5.12, Symfony 7.4 LTS (core 7.4.22), DBAL 4.5.0, PHPUnit 12.5.42
and Composer 2.10.3 using official compatibility sources and actual dependency
resolution. Symfony transitive components are constrained below 8.0 to retain
the selected LTS family. Composer reported no security advisories.

Built the Docker image with intl/mbstring/pdo_pgsql; installed the locked
dependencies. `docker compose run --rm app composer test` passed 10 tests /
26 assertions with no database URL or provider key: protected console, owner
login/logout, CSRF rejection, untrusted origin rejection and safe configuration
errors. PHP syntax, service container and all three Twig templates passed lint.
The initial HTTP run exposed an APP_ENV collision with Compose's development
environment; the test bootstrap now explicitly isolates both server/env values.

Private configuration is not prefilled with a usable owner password. The owner
chooses one through `tools/configure.php`; no real credential was generated,
printed or committed during this work. CI is defined but has not run remotely.
Database ACL/concurrency/cross-language acceptance and deployment remain future
work. The built-in HTTP server is development-only.


## 2026-10-31 — milestone 2 read-only console

Built the pinned Python source snapshot using its frozen uv.lock (Python 3.14.8).
Started a separately named tmpfs PostgreSQL 17/pgvector 0.8.6 cluster with no
published ports. Ran actual upstream Alembic migrations and provision_roles,
then `database/001_read_model.sql`, which verifies head 0006_post_request_usage.
Seeded 27 synthetic invitations and all five accounting states.

`docker compose -f compose.test.yaml run --rm php composer test:database` passed
3 tests / 34 assertions: anonymous detail protection, pagination, literal Twig
name rendering, fixed outcome/error projection, exact micro-USD and exclusion of
synthetic content/digest canaries. Confirmed forbidden direct operations reads,
usage reset/deletion, analytics/knowledge access and manager DDL fail for the
actual runtime login. The role defaults to read-only transactions. No production
connection or provider call was made.

`composer lint` passed PHP/container and five Twig templates; the no-DB suite
passed 11 tests / 29 assertions before the additional session/throttle checks.
Because debug is disabled, new service files initially encountered stale compiled
cache; lint now clears its environment cache and tests recreate only test cache.
The read-only milestone is complete locally; mutation and hosted evidence are
still separate.


## 2026-10-31 — milestone 3 safe administration

Implemented validated Symfony forms and DBAL services for create/edit/revoke,
explicit owner-only SQL provisioning, fixed-action atomic audit, stale edit
rejection and session-bound operation intents. A creation replay returns a safe
receipt without its secret; no credential goes into redirects, sessions, flashes
or audit. Failed/lost responses have explicit retry/replacement guidance.
Labels are excluded from audit metadata, which retains limits/expiry only.

Recreated the tmpfs database from scratch and ran both SQL owner jobs against
actual Alembic head. The combined suite subsequently passed 43 unit/HTTP tests /
69 assertions and 8 real-DB tests / 83 assertions. Verified create and edit forms,
POST/CSRF revocation, replay handling, unchanged counters and expiry, lower totals,
first revocation timestamp, revoked-edit rejection and denied audit/direct writes.
A default form mapping initially converted missing strings to null; explicit
empty-data/hidden-field transformers now validate incomplete forms safely.

`python3 tools/check-contract.py` passed actual PHP issuance -> Python
AccessStore.authenticate/create_session, expiry/revocation of tokens and linked
sessions, both lock orderings for admission/revocation and administration-first
limit changes, offline recorded/unknown accounting, post-request threshold
crossing, failed-audit rollback (including paired creation rows), and bounded
lock waits. Credentials traveled only through captured in-memory process pipes;
no full invitation was printed or saved. Python provider calls were not made.

Python 3.14.8 uses Unicode 16.0.0. Generated its 736 printable codepoint ranges
for PHP and SQL, avoiding ICU/PCRE version assumptions. Cross-language verification
compared SHA-256 classification over all 1,114,112 codepoints and SQL validation
at every classification boundary. Plain ASCII spaces trim as Python does; other
Unicode whitespace/control/format characters are rejected.

The framework logger now emits fixed event/level/time fields only, discarding
exception messages, request URLs and arbitrary context. A canary test proves
these contents are excluded. Runtime remains without provider/owner credentials.
Hosted lifecycle/session/HTTPS acceptance and remote CI remain unverified.


## 2026-10-31 — final local operational acceptance

Final results after the compatibility guard and measured model/token projection:

- `docker compose run --rm app composer lint`: PHP syntax, service container and
  all eight Twig templates passed with debug disabled.
- `docker compose run --rm app composer test`: 45 tests / 73 assertions, including
  owner-wide throttling, idle expiry, password-rotation invalidation and logout CSRF.
- Fresh tmpfs cluster -> actual upstream Alembic/role provisioning -> both Manager
  owner jobs -> `composer test:database`: 9 tests / 91 assertions. Added runtime
  rejection of an unsupported Alembic head and negative session/lease/policy/DDL/
  temporary-table/role-switch permissions. Measured model/token fields are fixed
  model identifiers and bounded numeric projections, not raw usage JSON.
- `python3 tools/check-contract.py`: passed the complete pinned Python contract,
  with only a safe summary emitted and no persisted invitation credentials.
- `python3 tools/check-fresh.py`: installed all 82 locked dependencies into a
  clean temporary source copy and passed Composer validation, lint and the then
  current 43-test no-DB suite, without .env.local/vendor/runtime files. Two later
  security tests passed in the final 45-test run; installation inputs are unchanged.
- Chrome on the actual loopback development server: owner sign-in, rendered
  paginated list and create form visually inspected with synthetic metadata only.
  No creation response was screenshotted. Temporary preview was stopped afterward.
- `python3 tools/check-repository.py`: 80 publishable source files / 25 local
  Markdown links passed credential-pattern and broken/private-plan link checks.
  `git diff --check` passed; private config, vendor, caches, artifacts and PLAN.md
  remain ignored. Pinned upstream working tree stayed unchanged.

The final fixture expansion initially used JSON numeric literals inside a
SQLAlchemy text statement, which interpreted colons as binds; the fixture now
passes JSON through an explicit bound parameter. The clean database run above
was repeated successfully after that correction.

CI now defines source checks, build/install/lint/unit/HTTP, real PostgreSQL and
Python contract jobs, but no remote workflow was run. Deployment, production PHP
serving/TLS/proxy configuration, hosted session/restart/audit behavior, hosted
Python UI login and published portfolio screenshots remain outstanding. No
production connection, paid AI execution, commit or push was performed.


## 2026-10-31 — local preview link diagnosis

The owner tested an invitation from the preview console against the existing
QueryLens UI at 127.0.0.1:8000. Read-only lookup by public invitation ID confirmed
that it is present and active in the disposable Manager database, but absent
from the database configured in the running QueryLens API. The API lookup used
its existing dedicated control role with a read-only transaction. Anonymous
access/status returned HTTP 401, as expected.

The preview destination querylens.test is an offline placeholder; substituting
the real local UI origin cannot share invitations across separate databases.
A normal local integration still needs an explicit Manager role/owner job on
the intended shared database and a trusted local-link origin design. No token
was copied, no shared ledger/configuration was changed, and no AI call was made.
No full credential or digest is retained in this record.


## 2026-10-31 — shared local QueryLens connection

The owner explicitly authorized connecting Manager to the existing local Python
service. Re-read the pinned source and verified its local container/network and
Alembic head before changing permissions. Added `compose.local.yaml`, an explicit
`tools/connect-local.py` owner job and development-only loopback invitation URLs.
The HTTPS-only production rule and non-loopback HTTP rejection remain tested.

The owner job executed both reviewed SQL files as one transaction on the local
`querylens-db-1` database, under the shared advisory lock. In-transaction hashes
of all existing token/usage rows were equal before/after provisioning. The
existing ledger contained one token/usage pair; neither row was rewritten. New
Manager schema/views/routines/audit and dedicated roles were installed, with a
random SCRAM runtime credential saved only in private mode-0600 `.env.local`.
Existing admin username/password hash were preserved. Owner/provider credentials
were not placed in the Manager runtime.

Initial SCRAM preparation required a libpq connection; the job now uses the
existing Python demo-control connection to hash client-side. The failed attempts
stopped before schema mutation; no private subprocess output was forwarded.

Stopped only the preview web service, retaining its disposable data. Started
Manager on `querylens_default` using the local override. QueryLens API/database
were not restarted or moved. Actual verification:

- PHP/container/eight-Twig lint passed; no-DB PHPUnit suite passed 56 tests /
  85 assertions, including loopback-link allow/deny cases and production refusal
  of the local integration command. Existing real-DB race/ACL fixtures were not
  run against this shared ledger.
- `app:verify-local-integration` issued a dedicated verification invitation through
  PHP, received HTTP 200 from Python login and authenticated session status,
  verified zero admitted requests/known/unknown cost, logged out, and revoked the
  check invitation through the audited Manager routine. The labeled revoked row
  and its create/revoke audit are retained. No code/cookie/CSRF value was printed,
  saved or screenshotted; no chat/admission/provider call occurred.
- QueryLens `/health/ready` returned ready with database/migrations/pgvector/access
  all true; Manager `/login` returned HTTP 200. The existing owner password was
  not needed for the CLI smoke and was not changed.
- Source checks passed 85 files / 31 local Markdown links; Git diff whitespace
  checks passed. Upstream working tree stayed unchanged.

New invitations from `http://localhost:8086` now use the shared ledger and links
to `http://127.0.0.1:8000`. Earlier preview-issued invitations remain exclusively
in manager_test; they were not copied or silently reissued. The owner must create
a new invitation in the connected console. Hosting and paid AI remain outside
this local verification.


## 2026-10-31 — owner-authorized publication preparation

The owner requested committing and pushing the complete implementation/local
integration. Reviewed all 92 staged files, including Twig templates, for complete
invitation/session/provider credentials and accidental copies of private local
APP_SECRET, admin hash, runtime URL or its password. No matching private value
or runtime/private path was staged. PLAN.md, .env.local, vendor, caches and
artifacts remain ignored. Source/link and staged whitespace checks passed.

The preceding verification sections retain their actual local results; no paid
or hosted check was added for publication. The push will trigger the checked-in
CI definition; remote workflow success still needs its own evidence.
