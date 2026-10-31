"""Verify the locked PHP setup in a fresh temporary directory with no private files."""

import shutil
import subprocess
import tempfile
from pathlib import Path

root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix="querylens-manager-fresh-") as temporary:
    directory = Path(temporary)
    for name in ["composer.json", "composer.lock", "phpunit.xml.dist", "bin", "config", "src", "templates", "public", "resources", "tests", "tools"]:
        source = root / name
        if source.is_dir():
            shutil.copytree(source, directory / name, ignore=shutil.ignore_patterns("__pycache__"))
        else:
            shutil.copy2(source, directory / name)
    docker = ["docker", "run", "--rm", "-v", f"{directory}:/app", "querylens-manager-app:latest"]
    for command in [["composer", "install", "--no-interaction", "--prefer-dist"], ["composer", "validate", "--strict"], ["composer", "lint"], ["composer", "test"]]:
        subprocess.run(docker + command, check=True)
print("Fresh locked install, validation, lint and no-database tests passed without private files.")
