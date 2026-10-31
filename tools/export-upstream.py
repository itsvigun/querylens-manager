"""Export only tracked files from the pinned QueryLens revision, never its .env."""

import io
import subprocess
import sys
import tarfile
from pathlib import Path

REVISION = "5f2c38e2dc1550e7ffe57dd0d9a8f6bbc26826ff"
root = Path(__file__).resolve().parents[1]
destination = root / "artifacts/upstream"
if destination.exists():
    raise SystemExit("artifacts/upstream already exists; move it before exporting a fresh snapshot")
source = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else root.parent / "querylens"
if not source.is_dir():
    source = root / "artifacts/querylens-source"
    subprocess.run(["git", "clone", "--no-checkout", "https://github.com/itsvigun/querylens.git", str(source)], check=True)
resolved = subprocess.check_output(["git", "-C", str(source), "rev-parse", REVISION], text=True).strip()
if resolved != REVISION:
    raise SystemExit("Pinned revision mismatch")
archive = subprocess.check_output(["git", "-C", str(source), "archive", REVISION])
destination.mkdir(parents=True)
with tarfile.open(fileobj=io.BytesIO(archive)) as exported:
    exported.extractall(destination, filter="data")
print("Exported pinned QueryLens tracked source to ignored artifacts/upstream.")
