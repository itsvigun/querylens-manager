"""Apply the explicit Manager write owner job to the disposable database only."""

import os
from pathlib import Path
from sqlalchemy import create_engine

url = os.environ["TEST_OWNER_DATABASE_URL"]
if not url.endswith("@database/manager_test"):
    raise SystemExit("Only the disposable test database is allowed")
engine = create_engine(url, hide_parameters=True)
with engine.begin() as connection:
    with connection.connection.driver_connection.cursor() as cursor:
        cursor.execute(Path("/manager-database/002_administration.sql").read_text())
engine.dispose()
print("Constrained administration routines enabled in disposable database.")
