# Documentation

These guides are the portable development context for an independent repository.
They require no private QueryLens plan, environment file or invitation code.

| Guide | Purpose |
|---|---|
| [QueryLens context](querylens-context.md) | Existing product, source baseline, responsibilities and evidence |
| [Architecture](architecture.md) | Console scope, boundaries, proposed integration and open decisions |
| [Integration contract](integration.md) | Existing data, credential format, invariants and compatibility checks |
| [Security](security.md) | Owner access, least privilege, one-time secrets and audit boundaries |
| [Verification](verification.md) | Checks actually completed and required future acceptance |

Start with [README](../README.md). The owner's implementation plan is kept in a
local, ignored `PLAN.md`. Read source/tests from the pinned upstream revision
before implementing a compatibility-sensitive change.
Guides describe planned console behavior unless explicitly marked as verified.

Add setup, testing and deployment guides when their actual commands exist. Do not
publish hypothetical commands as verified setup. Keep the root README short.
