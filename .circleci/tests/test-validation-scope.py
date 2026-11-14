"""Exercise the docs filter against disposable repositories, without network access."""

import os
from pathlib import Path
import subprocess
import tempfile

SCRIPT = Path(__file__).resolve().parents[1] / "scripts/check-validation-scope.sh"
UPSTREAM = "https://github.com/FOSSBilling/FOSSBilling.git"


def git(repository, *arguments):
    return subprocess.run(
        ["git", "-C", str(repository), *arguments],
        check=True, capture_output=True, text=True,
    )


def identify(repository):
    git(repository, "config", "user.name", "CI Test")
    git(repository, "config", "user.email", "ci@example.invalid")


def check_cases(root):
    origin = root / "origin"
    origin.mkdir()
    git(origin, "init", "-b", "main")
    identify(origin)
    for name in ["README.md", "app.php"]:
        (origin / name).write_text("base\n")
    git(origin, "add", ".")
    git(origin, "commit", "-m", "Initial")

    fork = root / "fork"
    git(root, "clone", str(origin), str(fork))
    identify(fork)
    (fork / "app.php").write_text("Additional fork change\n")
    git(fork, "add", ".")
    git(fork, "commit", "-m", "Change fork main")

    binaries = root / "bin"
    binaries.mkdir()
    agent = binaries / "circleci-agent"
    agent.write_text('#!/bin/sh\n[ "$*" = "step halt" ] || exit 2\necho HALTED\n')
    agent.chmod(0o755)

    # label, changed files, rename, branch, trigger, force, broken fetch, halt
    cases = [
        ("docs", ["README.md"], False, "docs", "webhook", "false", False, True),
        ("nested docs", ["docs/guide/howto.md"], False, "docs", "webhook", "false", False, True),
        ("empty", [], False, "docs", "webhook", "false", False, False),
        ("main", ["README.md"], False, "main", "webhook", "false", False, False),
        ("manual", ["README.md"], False, "docs", "api", "false", False, False),
        ("forced", ["README.md"], False, "docs", "webhook", "true", False, False),
        ("code", ["app.php"], False, "code", "webhook", "false", False, False),
        ("mixed", ["README.md", "app.php"], False, "mixed", "webhook", "false", False, False),
        ("ci config", [".circleci/config.yml"], False, "ci", "webhook", "false", False, False),
        ("renamed code", [], True, "rename", "webhook", "false", False, False),
        ("fork main", ["README.md"], False, "docs", "webhook", "false", False, False),
        ("fetch failure", ["README.md"], False, "docs", "webhook", "false", True, False),
    ]
    for label, files, rename, branch, trigger, force, broken, expected in cases:
        repository = root / label.replace(" ", "-")
        git(root, "clone", str(fork if label == "fork main" else origin), str(repository))
        identify(repository)
        git(repository, "checkout", "-b", "test")
        for name in files:
            path = repository / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("changed\n")
        if rename:
            git(repository, "mv", "app.php", "docs.md")
        if files or rename:
            git(repository, "add", ".")
            git(repository, "commit", "-m", "Change")
        # Redirect the canonical upstream fetch to our local fixture.
        target = root / "missing" if broken else origin
        git(repository, "config", f"url.{target}.insteadOf", UPSTREAM)
        environment = dict(
            os.environ,
            PATH=str(binaries) + os.pathsep + os.environ["PATH"],
            VALIDATION_TRIGGER=trigger,
            CIRCLE_BRANCH=branch,
            FORCE_FULL_VALIDATION=force,
        )
        result = subprocess.run(
            ["bash", str(SCRIPT)], cwd=repository, env=environment,
            capture_output=True, text=True, check=True,
        )
        if ("HALTED" in result.stdout) != expected:
            raise AssertionError((label, result.stdout, result.stderr))
        print(f"{label}: PASS")


if __name__ == "__main__":
    with tempfile.TemporaryDirectory(prefix="fb-scope-") as directory:
        check_cases(Path(directory))
