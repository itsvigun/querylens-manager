# QueryLens Manager

Owner-only Symfony administration for
[QueryLens](https://github.com/itsvigun/querylens) demo invitations.

**Status:** milestones 1–3 implemented and verified locally with PHP 8.5,
Symfony 7.4 LTS and PostgreSQL 17. Hosting is not deployed or verified.

- Owner login/logout, CSRF, login throttling and separate admin sessions.
- Paginated invitations and sanitized usage, exact micro-USD and unknown states.
- Create with one-time code/link, edit lifetime limits/expiry and revoke.
- Restricted database routines, shared Python locking and atomic audit.
- Unit/HTTP, real PostgreSQL permissions and pinned Python contract tests; CI defined.

Python retains AI execution, admission and accounting. No provider key,
analytics/knowledge access, quota reset or registration belongs in this console.

Start with [local setup](docs/setup.md), the [offline walkthrough](docs/walkthrough.md)
and [verification evidence](docs/verification.md). See the
[documentation map](docs/README.md), [architecture](docs/architecture.md) and
[contributor instructions](AGENTS.md). The owner's milestone plan remains local
in ignored `PLAN.md`.

Already running QueryLens on port 8000? Use the
[shared local integration](docs/local-integration.md) for working invitation links.
