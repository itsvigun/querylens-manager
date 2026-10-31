-- Explicit owner job; never loaded by the application. Requires upstream Alembic head.
BEGIN;
SELECT pg_advisory_xact_lock(716202608);
DO $check$
BEGIN
    IF (SELECT count(*) FROM public.alembic_version) <> 1
       OR NOT EXISTS (SELECT 1 FROM public.alembic_version WHERE version_num = '0006_post_request_usage') THEN
        RAISE EXCEPTION 'Unsupported QueryLens migration head';
    END IF;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname IN ('querylens_manager', 'querylens_manager_owner')) THEN
        RAISE EXCEPTION 'Manager roles already exist; inspect privileges before provisioning';
    END IF;
END;
$check$;
CREATE ROLE querylens_manager_owner NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS;
CREATE ROLE querylens_manager NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS;
CREATE SCHEMA manager AUTHORIZATION querylens_manager_owner;
REVOKE ALL ON SCHEMA manager FROM PUBLIC;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
-- Upstream's role provisioner already removes PUBLIC schema/table access. Refuse
-- unexpected legacy PUBLIC privileges instead of changing shared production ACLs.
DO $check$
BEGIN
    IF has_schema_privilege('querylens_manager', 'analytics', 'USAGE')
       OR has_schema_privilege('querylens_manager', 'knowledge', 'USAGE')
       OR has_schema_privilege('querylens_manager', 'operations', 'USAGE') THEN
        RAISE EXCEPTION 'Unexpected PUBLIC grants; run/review upstream role provisioning first';
    END IF;
END;
$check$;
GRANT USAGE ON SCHEMA operations TO querylens_manager_owner;
GRANT SELECT (version_num) ON public.alembic_version TO querylens_manager_owner;
GRANT SELECT (id, name, created_at, expires_at, revoked_at, max_requests, budget_micro_usd, requests_per_minute)
    ON operations.access_tokens TO querylens_manager_owner;
GRANT SELECT (token_id, admitted_requests, charged_micro_usd, unknown_requests, first_used_at, last_used_at)
    ON operations.access_usage TO querylens_manager_owner;
GRANT SELECT (request_id, token_id, created_at, completed_at, charged_micro_usd, usage_state, summary)
    ON operations.access_requests TO querylens_manager_owner;
SET LOCAL ROLE querylens_manager_owner;
ALTER DEFAULT PRIVILEGES IN SCHEMA manager REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC;
CREATE VIEW manager.compatibility AS
SELECT count(*) = 1 AND bool_and(version_num = '0006_post_request_usage') AS supported
FROM public.alembic_version;
CREATE VIEW manager.invitations AS
SELECT t.id, t.name, t.created_at, t.expires_at, t.revoked_at,
       t.max_requests, t.budget_micro_usd, t.requests_per_minute,
       u.admitted_requests, u.charged_micro_usd, u.unknown_requests,
       u.first_used_at, u.last_used_at,
       greatest(0, t.max_requests - u.admitted_requests) AS remaining_requests,
       CASE WHEN t.revoked_at IS NOT NULL THEN 'revoked'
            WHEN t.expires_at <= clock_timestamp() THEN 'expired'
            WHEN u.admitted_requests >= t.max_requests THEN 'question_limit'
            WHEN u.charged_micro_usd >= t.budget_micro_usd THEN 'cost_threshold'
            ELSE 'active' END AS lifecycle,
       md5(jsonb_build_array(t.name, t.max_requests, t.budget_micro_usd,
                            t.requests_per_minute, extract(epoch FROM t.expires_at),
                            extract(epoch FROM t.revoked_at))::text) AS metadata_version
FROM operations.access_tokens t JOIN operations.access_usage u ON u.token_id = t.id;
CREATE VIEW manager.requests AS
SELECT request_id, token_id, created_at, completed_at, charged_micro_usd, usage_state,
       CASE WHEN usage_state='recorded' AND summary #>> '{billing_usage,model}' = 'gpt-5.4-mini'
            THEN 'gpt-5.4-mini' ELSE NULL END AS model,
       CASE WHEN usage_state='recorded' AND summary #>> '{billing_usage,input_tokens}' ~ '^([0-9]{1,9}|1000000000)$'
            THEN (summary #>> '{billing_usage,input_tokens}')::integer ELSE NULL END AS input_tokens,
       CASE WHEN usage_state='recorded' AND summary #>> '{billing_usage,output_tokens}' ~ '^([0-9]{1,9}|1000000000)$'
            THEN (summary #>> '{billing_usage,output_tokens}')::integer ELSE NULL END AS output_tokens,
       CASE WHEN usage_state='recorded' AND summary #>> '{billing_usage,embedding_model}' IN ('text-embedding-3-small','text-embedding-3-large')
            THEN summary #>> '{billing_usage,embedding_model}' ELSE NULL END AS embedding_model,
       CASE WHEN usage_state='recorded' AND summary #>> '{billing_usage,embedding_tokens}' ~ '^([0-9]{1,9}|1000000000)$'
            THEN (summary #>> '{billing_usage,embedding_tokens}')::integer ELSE NULL END AS embedding_tokens,
       CASE WHEN summary->>'status' IN ('answered', 'clarification', 'insufficient_context', 'unsupported', 'error')
            THEN summary->>'status' ELSE 'unreported' END AS outcome,
       CASE WHEN summary->>'error_category' IN
          ('access_denied', 'access_request_limit', 'access_budget_exhausted', 'access_rate_limit',
           'access_busy', 'access_unavailable', 'request_interrupted', 'request_failed',
           'demo_busy', 'demo_rate_limit', 'demo_budget_exhausted', 'demo_controls_unavailable',
           'invalid_input', 'invalid_request', 'request_too_large', 'configuration_error')
            THEN summary->>'error_category'
            WHEN summary->>'error_category' IS NOT NULL THEN 'request_failed' ELSE NULL END AS error_category
FROM operations.access_requests;
GRANT USAGE ON SCHEMA manager TO querylens_manager;
GRANT SELECT ON manager.compatibility, manager.invitations, manager.requests TO querylens_manager;
RESET ROLE;
ALTER ROLE querylens_manager SET search_path = pg_catalog;
ALTER ROLE querylens_manager SET timezone = 'UTC';
ALTER ROLE querylens_manager SET statement_timeout = '5s';
ALTER ROLE querylens_manager SET lock_timeout = '1s';
ALTER ROLE querylens_manager SET default_transaction_read_only = on;
COMMIT;
