# Documentation

These guides are the portable development context for an independent repository.
They require no private QueryLens plan, environment file or invitation code.

| Guide | Purpose |
|---|---|
| [QueryLens context](querylens-context.md) | Existing product, source baseline, responsibilities and evidence |
| [Local setup](setup.md) | Reproducible development and verification commands |
| [Shared local integration](local-integration.md) | Connect the running Python UI and Manager to one local ledger |
| [Implementation decisions](decisions.md) | Selected stack, integration, authentication and ownership |
| [Railway deployment](deployment.md) | Production serving, scoped owner job, persistent state and rollback |
| [Database owner jobs](database.md) | Disposable checks, grants, release order and rollback |
| [Offline walkthrough](walkthrough.md) | Demonstrate the first-release workflows with synthetic data |
| [Architecture](architecture.md) | Console scope, boundaries, proposed integration and open decisions |
| [Integration contract](integration.md) | Existing data, credential format, invariants and compatibility checks |
| [Security](security.md) | Owner access, least privilege, one-time secrets and audit boundaries |
| [Verification](verification.md) | Checks actually completed and required future acceptance |

Start with [README](../README.md). The owner's implementation plan is kept in a
local, ignored `PLAN.md`. Read source/tests from the pinned upstream revision
before implementing a compatibility-sensitive change.
Guides distinguish locally implemented/verified behavior from remaining hosted
acceptance. Historical preparation records describe the state at their dates.

Add setup, testing and deployment guides when their actual commands exist. Do not
publish hypothetical commands as verified setup. Keep the root README short.
