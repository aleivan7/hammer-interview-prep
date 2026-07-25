#!/usr/bin/env python3

import json
import subprocess
import sys
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]


def run_hook(script: str, payload: dict[str, object]) -> dict[str, object]:
    result = subprocess.run(
        [sys.executable, str(ROOT / ".cursor" / "hooks" / script)],
        cwd=ROOT,
        input=json.dumps(payload),
        capture_output=True,
        check=True,
        text=True,
    )

    return json.loads(result.stdout)


class ShellSafetyTest(unittest.TestCase):
    def test_allows_safe_commands(self) -> None:
        result = run_hook("shell-safety.py", {"command": "git status --short"})

        self.assertEqual(result["permission"], "allow")

    def test_denies_force_pushes(self) -> None:
        result = run_hook("shell-safety.py", {"command": "git push --force origin main"})

        self.assertEqual(result["permission"], "deny")

    def test_denies_writes_outside_workspace(self) -> None:
        result = run_hook("shell-safety.py", {"command": "touch /tmp/outside.txt"})

        self.assertEqual(result["permission"], "deny")

    def test_denies_deployments(self) -> None:
        result = run_hook("shell-safety.py", {"command": "kubectl apply -f deployment.yml"})

        self.assertEqual(result["permission"], "deny")


class McpSafetyTest(unittest.TestCase):
    def test_allows_read_only_laravel_tools(self) -> None:
        result = run_hook(
            "mcp-safety.py",
            {"server": "laravel-boost", "tool_name": "database-schema"},
        )

        self.assertEqual(result["permission"], "allow")

    def test_requires_approval_for_sensitive_laravel_tools(self) -> None:
        result = run_hook(
            "mcp-safety.py",
            {"server": "laravel-boost", "tool_name": "database-query"},
        )

        self.assertEqual(result["permission"], "ask")


if __name__ == "__main__":
    unittest.main()
