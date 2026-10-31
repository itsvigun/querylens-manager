"""Run PHP -> Python acceptance without writing or printing invitation secrets."""

import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[1]
compose = ["docker", "compose", "-f", str(root / "compose.test.yaml"), "run", "--rm", "-T"]
issued = subprocess.run(compose + ["php", "php", "tests/Contract/issue.php"], cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
if issued.returncode:
    # Do not forward arbitrary exception output from the credential producer.
    raise SystemExit("PHP contract fixture issuance failed; no credential output was retained.")
verified = subprocess.run(compose + ["python", "python", "/checks/verify.py"], cwd=root, input=issued.stdout, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
if verified.returncode:
    # The verifier returns fixed checks only, never exceptions or fixture values.
    safe = verified.stdout.decode(errors="replace").strip()
    print(safe if safe.startswith("Contract failure: ") and len(safe) < 160 else "Python contract verification failed.", file=sys.stderr)
    raise SystemExit(1)
print(verified.stdout.decode().strip())
