"""Migrate and seed the dedicated disposable cluster using pinned upstream code."""

import json
from pathlib import Path

from alembic import command
from alembic.config import Config
from sqlalchemy import create_engine, text

from app.config import Settings
from scripts.provision_roles import RolePasswords, provision_roles


def main():
    settings = Settings(_env_file=None)
    if settings.postgres_host != "database" or settings.postgres_db != "manager_test":
        raise RuntimeError("Only the Compose disposable database is allowed")
    engine = create_engine(settings.database_url, hide_parameters=True)
    with engine.connect() as connection:
        if connection.scalar(text("SELECT count(*) FROM pg_namespace WHERE nspname='operations'")):
            raise RuntimeError("Test database is not fresh; recreate the disposable stack")
    command.upgrade(Config("/upstream/alembic.ini"), "head")
    provision_roles(settings, RolePasswords(
        _env_file=None,
        analytics_readonly_password="disposable-analytics-reader",
        knowledge_writer_password="disposable-knowledge-writer",
        knowledge_readonly_password="disposable-knowledge-reader",
        demo_control_password="disposable-demo-control",
    ))
    with engine.begin() as connection:
        connection.exec_driver_sql(Path("/manager-database/001_read_model.sql").read_text())
        connection.exec_driver_sql("ALTER ROLE querylens_manager LOGIN PASSWORD 'disposable-manager'")
        connection.exec_driver_sql("GRANT CONNECT ON DATABASE manager_test TO querylens_manager")
        for index in range(1, 28):
            identifier = f"{index:032x}"
            connection.execute(text("""
                INSERT INTO operations.access_tokens
                (id,secret_digest,name,created_at,expires_at,max_requests,budget_micro_usd,requests_per_minute)
                VALUES (:id,:digest,:name,clock_timestamp(),clock_timestamp()+interval '30 days',10,1000000,2)
            """), {"id": identifier, "digest": "d" * 64, "name": '<script>alert("label")</script>' if index == 1 else f"Offline recipient {index}"})
            connection.execute(text("""
                INSERT INTO operations.access_usage
                (token_id,admitted_requests,reserved_micro_usd,rate_count,charged_micro_usd,unknown_requests)
                VALUES (:id,2,999,0,12345,1)
            """), {"id": identifier})
        for index, state in enumerate(["recorded", "pending", "unknown", "legacy", "not_admitted"], 100):
            connection.execute(text("""
                INSERT INTO operations.access_requests
                (request_id,token_id,created_at,reserved_micro_usd,charged_micro_usd,usage_state,summary)
                VALUES (:request,:token,clock_timestamp(),0,:cost,:state,
                CAST(:summary AS jsonb))
            """), {"request": f"{index:032x}", "token": f"{1:032x}", "cost": 12345 if state == "recorded" else None, "state": state,
                "summary": json.dumps({"question": "PRIVATE_CONTENT_CANARY", "status": "answered", "secret_digest": "DIGEST_CANARY", "error_category": "PRIVATE_ERROR_CANARY", "billing_usage": {"model": "gpt-5.4-mini", "input_tokens": 100, "output_tokens": 10, "embedding_model": "text-embedding-3-small", "embedding_tokens": 20}})})
    engine.dispose()
    print("Pinned Alembic schema, restricted read role and synthetic fixtures prepared.")


if __name__ == "__main__":
    main()
