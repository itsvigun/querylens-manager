# QueryLens Manager — repository instructions

## Context and current phase

This is a PHP/Symfony portfolio companion to the Python QueryLens project.
Read README.md, PLAN.md, docs/README.md and docs/architecture.md first, then
docs/querylens-context.md, docs/integration.md and docs/security.md before changing
the integration. Read docs/verification.md for actual evidence.

Implementation was authorized on 2026-10-31. Work through the next unfinished
milestone in PLAN.md and its acceptance checks. Use only disposable databases
for local verification. Production database access, deployment and paid provider
calls require separate explicit authorization.

## Working style

- Discuss decisions and progress in Ukrainian. Write code, comments, UI,
  repository documentation and commit messages in English.
- Keep the root README concise and contributor context in docs/.
- Finish one milestone and its acceptance checks before broadening scope.
- Update PLAN.md and docs/verification.md with actual work and remaining issues.
- Separate planned behavior from implemented and verified behavior.
- Use Symfony conventions, small application services and explicit dependencies.
  Add modules and packages only when a real feature needs them.
- Verify supported PHP/Symfony versions and package compatibility from official
  sources before selecting or installing new versions. The current selection is
  recorded in docs/decisions.md.
- Do not initialize/publish Git or create commits unless requested.

## Boundaries

- Symfony owns administrative workflows, not AI execution or request admission.
- Preserve QueryLens invitation format, lifecycle, quotas and post-request cost
  semantics. Read the pinned upstream source before modifying shared data.
- No OpenAI key, database owner credential or analytics/knowledge access in the
  console runtime. Provision its dedicated role through an explicit owner job.
- Alembic remains the owner of existing operations tables. Never let Doctrine
  schema tools migrate, recreate or drop those tables.
- Keep secrets out of Git, logs, exception pages, screenshots and test artifacts.
  Only invitation digests persist; the full code/link is shown once after creation.
- Authenticate every administrative action, use CSRF protection, and audit fixed
  administrative actions without credential/question/result content.
- No visitor registration, paid AI calls, queue, scheduler, reports service,
  invitation deletion, quota reset or public admin API in the first release.

## Verification

Use PHPUnit with unit, HTTP and real PostgreSQL integration checks once the app
exists. Stub-provider tests must not require paid keys. Use an isolated disposable
database for cross-language/permission/race tests, never the hosted ledger.
Establish exact setup/lint/test commands when selecting the stack; do not invent
working Composer/Docker commands in advance. Deployment and hosting claims need
separate evidence. Current documentation checks are recorded in verification.md.
