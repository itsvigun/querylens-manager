"""Regenerate the SQL validator from the pinned Python Unicode range fixture."""

import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
contract = json.loads((root / "resources/python-printable-ranges.json").read_text())
multirange = "{" + ",".join(f"[{start},{end})" for start, end in contract["ranges"]) + "}"
sql = f"""-- BEGIN GENERATED PRINTABLE CONTRACT
-- Python {contract['python']}, Unicode {contract['unicode']}; regenerate with tools/generate-name-sql.py.
CREATE FUNCTION manager.printable_name(p_name text) RETURNS boolean
LANGUAGE sql IMMUTABLE SET search_path = pg_catalog AS $printable$
    SELECT p_name IS NOT NULL AND char_length(p_name) BETWEEN 1 AND 128 AND btrim(p_name) <> ''
       AND NOT EXISTS (SELECT 1 FROM regexp_split_to_table(p_name, '') AS ch
                       WHERE NOT ('{multirange}'::int4multirange @> ascii(ch)));
$printable$;
-- END GENERATED PRINTABLE CONTRACT"""
path = root / "database/002_administration.sql"
source = path.read_text()
start = source.index("-- BEGIN GENERATED PRINTABLE CONTRACT")
end = source.index("-- END GENERATED PRINTABLE CONTRACT") + len("-- END GENERATED PRINTABLE CONTRACT")
path.write_text(source[:start] + sql + source[end:])
print("Regenerated SQL printable-name contract.")
