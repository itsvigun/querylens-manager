# Database integration and owner jobs

The console does not migrate or provision on startup. It accepts only a
`querylens_manager` PostgreSQL login without role memberships, ownership or
administrative flags. The web environment must contain only that role's URL.
There is no Doctrine schema command or migration generator installed.

## Disposable verification

The contract snapshot is upstream commit
`5f2c38e2dc1550e7ffe57dd0d9a8f6bbc26826ff`. Export tracked files only:

```sh
python3 tools/export-upstream.py
docker compose -f compose.test.yaml build python
docker compose -f compose.test.yaml up -d --wait database
docker compose -f compose.test.yaml run --rm python python /checks/prepare.py
docker compose -f compose.test.yaml run --rm python python /checks/enable_administration.py
docker compose -f compose.test.yaml run --rm php composer test:database
python3 tools/check-contract.py
```

If `artifacts/upstream` is already present from the implementation/checks, skip
the export step; the exporter refuses to overwrite it. For a new snapshot, move
the previous export aside first.

The exporter uses `../querylens` when present, or clones the public upstream into
ignored `artifacts/`; an optional source-repository path is accepted as its first
argument. It never copies working-tree changes, environment files or private
invitation files. Preparation refuses a non-fresh database or any destination
other than Compose's `database/manager_test`. It runs the actual pinned Alembic
migrations and role provisioner, then the Manager owner job and synthetic seed.
No paid key, provider request, hosted database or production data is involved.

The dedicated stack publishes no database ports and uses tmpfs storage. To remove
only this disposable stack and its data:

```sh
docker compose -f compose.test.yaml down --volumes
```

## Reviewed provisioning sequence

The following describes the deployment contract; production execution has not
been authorized or verified.

1. Confirm the upstream commit and `public.alembic_version` head
   `0006_post_request_usage`. Back up the shared ledger using the owner's policy.
2. Run upstream migrations and role provisioning as their existing owner job.
   The owner removes PUBLIC privileges from operations/analytics/knowledge and
   database CREATE/TEMPORARY. Manager provisioning refuses unexpected PUBLIC
   schema access; it must not silently broaden or replace upstream ACLs.
3. Run `database/001_read_model.sql` using a privileged, isolated owner job. It
   creates NOLOGIN routine-owner/runtime roles and the `manager` schema/views,
   grants column SELECT only to the view owner, and read-only SELECT on projected
   views to the runtime. The compatibility view guards the Alembic head on every new connection.
   It refuses existing roles rather than inheriting unknown
   privileges. The runtime has no operations schema USAGE.
4. Run `database/002_administration.sql` as the same isolated owner job. It adds
   the atomic audit table and three constrained SECURITY DEFINER mutation routines,
   denies PUBLIC/internal-helper execution, and switches the runtime default to
   read/write transactions. The runtime still has no direct operations writes.
   Printable Unicode semantics are pinned to Python 3.14.8 / Unicode 16.0.0;
   `tools/generate-name-sql.py` regenerates the SQL from the checked-in range file.
5. Grant CONNECT on the intended database and privately provision a distinct
   runtime password/login through the hosting secret-management facility. Use
   SCRAM and avoid passwords in SQL history, command arguments or logs. The test
   provisioner uses public synthetic passwords exclusively on its disposable DB.
6. Set `MANAGER_DATABASE_URL`, validate ACLs and complete the isolated acceptance
   suite before allowing hosted administrative use. No console connection is
   authorized to an existing hosted ledger by these local instructions.

Alembic owns all existing `operations` tables. The Manager SQL owner jobs own only
`manager` objects and explicit new role grants. Deploy upstream migrations first,
then compatible Manager owner jobs, then the console. Stop the console on any
unsupported shared-schema revision and re-run contract tests before upgrading.

## Rollback boundary

Disable the console and its runtime login first; the Python app can keep using
existing invitations and counters. Do not roll back or drop operations tables.
Keep administrative audit according to the owner's retention policy. Remove only
reviewed Manager views/routines/schema grants once the audit is exported safely
and all dependencies are known. Role/schema removal is a separate owner job;
there is intentionally no automatic destructive rollback command.


The SQL scripts themselves are transaction-wrapped and fail on unsupported heads;
001 refuses preexisting roles and 002 refuses preexisting objects. They are ordered
owner jobs, not repeatable startup scripts. For an owner-approved deployment use
an isolated PostgreSQL client with a private service/password file:

```sh
psql -X --set ON_ERROR_STOP=1 --file database/001_read_model.sql
psql -X --set ON_ERROR_STOP=1 --file database/002_administration.sql
```

Set the client connection through the owner's secure `PGSERVICE` configuration;
never paste a DSN/password into shell history. These files were executed and
verified through the disposable Python/psycopg owner job, not a hosted client.
No upstream Alembic or role-provisioning source change is required for this pinned
revision. Future upstream schema changes require a coordinated Manager release.
