# QueryLens Manager

A planned PHP/Symfony administration application for
[QueryLens](https://github.com/itsvigun/querylens): issue named demo invitations,
manage their limits and expiry, and inspect sanitized usage and estimated AI cost.

**Status: planning and documentation only.** No Symfony application, dependencies,
database integration, tests or deployment have been implemented in this directory.

## First release

- Owner-only administrative login; no visitor registration.
- Three main screens: invitation list, create/edit form and usage details.
- Create a compatible invitation and display its secret/private link once.
- Update lifetime limits or expiry and revoke invitations without resetting usage.
- Inspect question counts, usage states, known cost and last admitted activity.

Python continues to own AI execution, admission checks and usage accounting.
The console does not need an OpenAI key or access to analytics/knowledge data.

## Start here

1. Read [docs/README.md](docs/README.md) for the documentation map.
2. Follow [AGENTS.md](AGENTS.md) when implementing or maintaining this project.

The owner's implementation plan is maintained locally in an ignored `PLAN.md`.

The proposed first integration uses the existing PostgreSQL operations schema
with a new restricted role. This requires an explicit compatibility and permission
design before implementation; see [architecture](docs/architecture.md).

Git is initialized and connected to
[itsvigun/querylens-manager](https://github.com/itsvigun/querylens-manager).
The repository contains documentation and ignore rules only.
