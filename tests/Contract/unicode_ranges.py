"""Export Python's printable character contract without relying on host ICU."""

import json
import sys
import unicodedata

ranges = []
start = None
for value in range(sys.maxunicode + 2):
    printable = value <= sys.maxunicode and chr(value).isprintable()
    if printable and start is None:
        start = value
    elif not printable and start is not None:
        ranges.append([start, value])
        start = None
print(json.dumps({"python": sys.version.split()[0], "unicode": unicodedata.unidata_version, "ranges": ranges}, separators=(",", ":")))
