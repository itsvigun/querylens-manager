# Planned security boundaries

No console authentication or security controls have been implemented yet. These
requirements guide milestone design and acceptance; they are not deployment claims.

## Administrative access

One owner identity is sufficient initially, provisioned privately with a password
hash. No public registration or invitation-based admin login. Use Symfony Security,
explicit route protection, secure host-only HttpOnly cookies, session rotation,
logout, CSRF for mutations and bounded login attempts. Choose session storage and
restart behavior before deployment. The owner session must have a different
cookie/name and trust boundary from Python visitor access.

An invitation can grant only demo access, never administrator rights. Changes are
POST mutations with validated DTOs/forms; GET links cannot revoke/change limits.
Names are rendered literally with Twig escaping. Any configured external link
destination must be trusted; visitor-supplied hosts/redirects are not authority.

## Credentials and grants

Give the console its own restricted runtime PostgreSQL role; keep DDL and role
provisioning in explicit owner jobs. No owner/superuser password, provider API key,
analytics reader or knowledge writer belongs in the console runtime. Do not reuse
the Python demo-control role: its intentionally restricted rights differ.

Restrict existing operations writes to issuing the paired initial records and
approved invitation metadata mutations. No existing usage/accounting UPDATE,
credential readback, session/lease manipulation or global policy edit. Prove
negative ACLs with real PostgreSQL; constraints and application forms alone do not
establish least privilege. If routines are chosen, review their execution grants,
search path and privilege boundary before implementing them.

Existing Alembic migrations own operations tables. Doctrine migrations/schema
diff must never recreate/drop them. Administrative audit storage has no selected
migration owner yet; resolve it explicitly before adding tables.

## One-time secret delivery

Show a newly issued invitation/link only in the authenticated creation response
after successful commit. Persist only its digest and safe metadata. Do not put the
secret into redirects, a Symfony session/flash message, browser storage, logs,
exception dumps, profiler traces or background jobs. Use no-store/no-referrer and
keep third-party analytics/assets out of the secret page. Refresh/back behavior,
double submission, connection loss and any temporary server buffer need a reviewed
design. Do not promise recoverability from a digest or silently create extra codes.

Prefer the QueryLens `#at=` link. Its initial fragment is not sent to the server;
the compatible `?at=` variant reaches hosting infrastructure and can appear in
edge logs even if later scrubbed. Either format is transferable bearer access.
Never commit real links, codes, environments, database exports or screenshots
containing them. Private files belong under ignored `.access-tokens/` or `artifacts/`.

## Audit and data minimization

Administrative audit should record a fixed action, server-side owner identity,
invitation ID, UTC timestamp and approved changed metadata. Do not record secrets,
digests, passwords, auth headers, question/result content or full HTTP requests.
Keep it distinct from Python's request-usage audit; console browsing is not an
admitted AI question. Decide label retention/access before deployment.

Reports must preserve NULL/unknown accounting and label micro-USD as estimates.
Do not reset usage, reclaim crash leases or invent missing charges. Recovery and
global policy changes stay explicit owner operations outside the first console.

## Verification boundary

Use synthetic/disposable fixtures for tests and screenshots. Local permission
tests do not prove hosted exposure/session behavior; verify them separately.
No paid AI calls, production writes, deployment or secret transfer is authorized
by the documentation-only preparation task.
