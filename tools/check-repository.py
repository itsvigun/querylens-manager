"""Check publishable source for broken local Markdown links and credential patterns."""

import os
import re
from pathlib import Path

root = Path(__file__).resolve().parents[1]
excluded = {".git", ".agents", ".codex", "vendor", "var", "artifacts", "node_modules", ".phpunit.cache", "__pycache__"}
patterns = [re.compile(r"ql[ts]_[a-f0-9]{32}_[A-Za-z0-9_-]{43}"), re.compile(r"sk-(?:proj-)?[A-Za-z0-9_-]{24,}"), re.compile(r"-----BEGIN [A-Z ]*PRIVATE KEY-----")]
checked = links = 0
errors = []
for current, directories, names in os.walk(root):
    directories[:] = [name for name in directories if name not in excluded]
    for name in names:
        if name == "PLAN.md" or (name.startswith(".env") and name != ".env.example"):
            continue
        path = Path(current) / name
        if path.suffix not in {".md", ".php", ".json", ".lock", ".yaml", ".yml", ".sql", ".css", ".ini", ".py", ".dist"} and name not in {"Dockerfile", ".gitignore", ".dockerignore", ".env.example", "console"}:
            continue
        source = path.read_text()
        checked += 1
        if any(pattern.search(source) for pattern in patterns):
            errors.append(f"Credential pattern in {path.relative_to(root)} (value omitted)")
        if path.suffix == ".md":
            for target in re.findall(r"\[[^\]]*\]\(([^)]+)\)", source):
                if "://" in target or target.startswith(("#", "mailto:")):
                    continue
                target = target.split("#", 1)[0]
                links += 1
                resolved = (path.parent / target).resolve()
                if not resolved.exists() or resolved == root / "PLAN.md":
                    errors.append(f"Broken/private-plan link in {path.relative_to(root)}")
if errors:
    raise SystemExit("\n".join(errors))
print(f"Safe source checks passed: {checked} files, {links} local Markdown links.")
