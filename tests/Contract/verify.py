"""Exercise actual pinned Python access/admission code. No network AI provider."""

import hashlib
import json
import os
import sys
import threading
import time
from datetime import timedelta
from uuid import uuid4

from sqlalchemy import create_engine, event, text

from app.access import AccessStore, credential_parts
from app.demo_gate import AdmissionError, DemoGate, DemoPolicy, LOCK

CHECK = "input"


def run(payload):
    global CHECK
    owner_url = os.environ["TEST_OWNER_DATABASE_URL"]
    if not owner_url.endswith("@database/manager_test"):
        raise RuntimeError("Disposable database required")
    owner = create_engine(owner_url, hide_parameters=True)
    manager = create_engine(os.environ["TEST_MANAGER_DATABASE_URL"], hide_parameters=True)
    demo = create_engine(os.environ["TEST_DEMO_DATABASE_URL"], hide_parameters=True)
    store = AccessStore(demo)
    policy = DemoPolicy("offline-manager-contract-v1", 100_000_000, 60, 10, "text-embedding-3-small")
    gate = DemoGate(demo, policy)
    fixtures = payload["fixtures"]
    with owner.begin() as connection:
        connection.execute(text("INSERT INTO operations.demo_policy(id,fingerprint) VALUES (1,:value) ON CONFLICT(id) DO UPDATE SET fingerprint=EXCLUDED.fingerprint"), {"value": policy.fingerprint})

    CHECK = "php-python-credential-and-login"
    assert store.ready()
    login = fixtures["login"]
    identity = store.authenticate(login["credential"])
    assert identity.token_id == login["id"]
    session, _, session_identity = store.create_session(login["credential"], 3600)
    assert store.authenticate(session, session=True).token_id == login["id"]
    assert credential_parts(login["credential"], "qlt")[0] == login["id"]

    CHECK = "unicode-all-codepoints"
    bits = "".join("1" if chr(point).isprintable() else "0" for point in range(0x110000))
    assert hashlib.sha256(bits.encode()).hexdigest() == payload["printable_sha256"]
    # SQL agrees at every transition of the exact Python classification.
    points = set()
    previous = False
    for point, bit in enumerate(bits):
        printable = bit == "1"
        if printable != previous:
            points.update([point - 1, point, point + 1])
        previous = printable
    points = [point for point in points if 0 < point <= 0x10ffff and not 0xd800 <= point <= 0xdfff]
    with owner.connect() as connection:
        rows = connection.execute(text("SELECT cp,manager.printable_name('X'||chr(cp)) AS accepted FROM unnest(CAST(:points AS integer[])) AS cp"), {"points": points})
        assert all(row.accepted == chr(row.cp).isprintable() for row in rows)

    def revoke(case):
        with manager.begin() as connection:
            result = connection.scalar(text("SELECT manager.revoke_invitation(:operation,:id,'owner')"), {"operation": uuid4().hex, "id": fixtures[case]["id"]})
            assert result == "revoked"

    def denied(action):
        try:
            action()
        except AdmissionError as error:
            assert error.category in {"access_denied", "access_request_limit", "access_budget_exhausted"}
        else:
            raise AssertionError("Admission unexpectedly succeeded")

    CHECK = "revocation-invalidates-token-and-linked-session"
    revoke("login")
    denied(lambda: store.authenticate(login["credential"]))
    denied(lambda: store.authenticate(session, session=True))
    denied(lambda: gate.admit(uuid4().hex, identity=session_identity))

    CHECK = "expiry-invalidates-token-and-linked-session"
    expired = fixtures["expiry"]
    expired_session, _, expired_identity = store.create_session(expired["credential"], 3600)
    with owner.begin() as connection:
        connection.execute(text("SELECT pg_advisory_xact_lock(:key)"), {"key": LOCK})
        connection.execute(text("UPDATE operations.access_tokens SET created_at=clock_timestamp()-interval '2 days',expires_at=clock_timestamp()-interval '1 day' WHERE id=:id"), {"id": expired["id"]})
    denied(lambda: store.authenticate(expired["credential"]))
    denied(lambda: store.authenticate(expired_session, session=True))
    denied(lambda: gate.admit(uuid4().hex, identity=expired_identity))

    CHECK = "administration-wins-lock-before-admission"
    for case in ["revoke_race", "limit_race"]:
        identity = store.authenticate(fixtures[case]["credential"])
        admitted = []
        started = threading.Event()
        finished = threading.Event()

        def admission():
            started.set()
            try:
                gate.admit(uuid4().hex, identity=identity)
                admitted.append("unexpected")
            except AdmissionError as error:
                admitted.append(error.category)
            finally:
                finished.set()

        with manager.begin() as connection:
            connection.execute(text("SELECT pg_advisory_xact_lock(:key)"), {"key": LOCK})
            if case == "revoke_race":
                connection.execute(text("SELECT manager.revoke_invitation(:op,:id,'owner')"), {"op": uuid4().hex, "id": identity.token_id})
            else:
                # The lowered lifetime total is below existing admitted usage.
                with owner.begin() as setup:
                    setup.execute(text("UPDATE operations.access_usage SET admitted_requests=1 WHERE token_id=:id"), {"id": identity.token_id})
                version = connection.scalar(text("SELECT metadata_version FROM manager.invitations WHERE id=:id"), {"id": identity.token_id})
                connection.execute(text("SELECT manager.update_invitation(:op,:id,:version,'Offline limit race',1,1000000,60,NULL,'owner')"), {"op": uuid4().hex, "id": identity.token_id, "version": version})
            worker = threading.Thread(target=admission)
            worker.start()
            assert started.wait(1)
            assert not finished.wait(0.15)
        worker.join(timeout=3)
        assert finished.is_set()
        assert admitted == (["access_denied"] if case == "revoke_race" else ["access_request_limit"])

    CHECK = "admitted-work-finishes-after-revocation"
    identity = store.authenticate(fixtures["admit_first"]["credential"])
    request = uuid4().hex
    revoked_done = threading.Event()
    revoked_errors = []
    revoker = None

    def revoke_during_admission():
        try:
            revoke("admit_first")
        except Exception:
            revoked_errors.append("revoke_failed")
        finally:
            revoked_done.set()

    def hold_admission(connection, cursor, statement, parameters, context, many):
        nonlocal revoker
        if statement.startswith("INSERT INTO operations.demo_leases"):
            revoker = threading.Thread(target=revoke_during_admission)
            revoker.start()
            assert not revoked_done.wait(0.15)

    event.listen(demo, "after_cursor_execute", hold_admission)
    try:
        gate.admit(request, identity=identity)
    finally:
        event.remove(demo, "after_cursor_execute", hold_admission)
    assert revoker is not None
    revoker.join(timeout=3)
    assert revoked_done.is_set() and not revoked_errors
    # Labeled offline receipt: no model or provider execution occurs.
    result = {"usage": {"model": "gpt-5.4-mini", "requests": 1, "input_tokens": 100, "output_tokens": 10, "usage_complete": True}, "embedding_usage": {"model": "text-embedding-3-small", "requests": 1, "prompt_tokens": 20, "usage_complete": True}}
    gate.release(request, result=result)
    gate.release(request, result=result)
    with owner.connect() as connection:
        row = connection.execute(text("SELECT admitted_requests,charged_micro_usd FROM operations.access_usage WHERE token_id=:id"), {"id": identity.token_id}).one()
        assert row.admitted_requests == 1 and row.charged_micro_usd > 0
        assert connection.scalar(text("SELECT usage_state FROM operations.access_requests WHERE request_id=:id"), {"id": request}) == "recorded"

    CHECK = "soft-cost-threshold-crossed-only-after-completion"
    identity = store.authenticate(fixtures["budget"]["credential"])
    with manager.begin() as connection:
        version = connection.scalar(text("SELECT metadata_version FROM manager.invitations WHERE id=:id"), {"id": identity.token_id})
        connection.execute(text("SELECT manager.update_invitation(:op,:id,:version,'Offline budget',10,1,60,NULL,'owner')"), {"op": uuid4().hex, "id": identity.token_id, "version": version})
    request = uuid4().hex
    gate.admit(request, identity=identity)
    gate.release(request, result=result)
    denied(lambda: gate.admit(uuid4().hex, identity=identity))
    with owner.connect() as connection:
        assert connection.scalar(text("SELECT charged_micro_usd FROM operations.access_usage WHERE token_id=:id"), {"id": identity.token_id}) > 1

    CHECK = "incomplete-receipt-preserves-null-cost"
    identity = store.authenticate(fixtures["unknown"]["credential"])
    request = uuid4().hex
    gate.admit(request, identity=identity)
    gate.release(request, result=None)
    with owner.connect() as connection:
        row = connection.execute(text("SELECT usage_state,charged_micro_usd FROM operations.access_requests WHERE request_id=:id"), {"id": request}).one()
        assert row.usage_state == "unknown" and row.charged_micro_usd is None

    CHECK = "audit-failure-rolls-back-mutation"
    rollback = fixtures["rollback"]["id"]
    with owner.begin() as connection:
        connection.exec_driver_sql("CREATE FUNCTION manager.fail_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'offline audit failure'; END $$")
        connection.exec_driver_sql("CREATE TRIGGER offline_fail_audit BEFORE INSERT ON manager.audit FOR EACH ROW EXECUTE FUNCTION manager.fail_audit()")
    try:
        try:
            revoke("rollback")
        except Exception:
            pass
        else:
            raise AssertionError("Mutation unexpectedly committed")
        with owner.connect() as connection:
            assert connection.scalar(text("SELECT revoked_at FROM operations.access_tokens WHERE id=:id"), {"id": rollback}) is None
        failed_id = uuid4().hex
        try:
            with manager.begin() as connection:
                connection.execute(text("SELECT manager.create_invitation(:id,:digest,'Offline failed create',10,1000000,2,30,'owner')"), {"id": failed_id, "digest": "e" * 64})
        except Exception:
            pass
        else:
            raise AssertionError("Unaudited issuance unexpectedly committed")
        with owner.connect() as connection:
            assert connection.scalar(text("SELECT count(*) FROM operations.access_tokens WHERE id=:id"), {"id": failed_id}) == 0
            assert connection.scalar(text("SELECT count(*) FROM operations.access_usage WHERE token_id=:id"), {"id": failed_id}) == 0
    finally:
        with owner.begin() as connection:
            connection.exec_driver_sql("DROP TRIGGER offline_fail_audit ON manager.audit")
            connection.exec_driver_sql("DROP FUNCTION manager.fail_audit()")

    CHECK = "negative-acl-and-lock-timeout"
    with owner.begin() as connection:
        connection.execute(text("SELECT pg_advisory_xact_lock(:key)"), {"key": LOCK})
        started = time.monotonic()
        try:
            revoke("rollback")
        except Exception:
            assert time.monotonic() - started < 3
        else:
            raise AssertionError("Lock wait unexpectedly succeeded")
    for engine in (owner, manager, demo):
        engine.dispose()
    print("Contract passed: PHP issuance -> Python login/session; Unicode; expiry/revoke; lock races; offline accounting; audit rollback; bounded lock wait. No paid calls.")


try:
    run(json.load(sys.stdin))
except Exception:
    # Never print SQLAlchemy exceptions, credentials, DSNs or fixture payloads.
    print("Contract failure: " + CHECK)
    raise SystemExit(1) from None
