#!/usr/bin/env python3

import json
import os
import re
import sys
from pathlib import Path
from typing import Any


def find_command(value: Any) -> str:
    if isinstance(value, dict):
        command = value.get("command")
        if isinstance(command, str):
            return command

        for nested in value.values():
            found = find_command(nested)
            if found:
                return found

    if isinstance(value, list):
        for nested in value:
            found = find_command(nested)
            if found:
                return found

    return ""


def deny(reason: str) -> None:
    print(
        json.dumps(
            {
                "permission": "deny",
                "user_message": reason,
                "agent_message": f"Safety hook blocked this command: {reason}",
            }
        )
    )
    raise SystemExit(0)


payload = json.load(sys.stdin)
command = find_command(payload)
normalized = " ".join(command.split())

if re.search(r"\bgit\s+push\b.*(?:--force(?:-with-lease)?|-f(?:\s|$))", normalized):
    deny("Force-pushing is disabled for this project.")

if re.search(r"\bgit\s+reset\s+--hard\b", normalized):
    deny("Destructive Git resets are disabled for this project.")

production_database = (
    r"(?:APP_ENV=production.*\bartisan\s+(?:migrate|db:wipe|schema:drop)\b)"
    r"|(?:\bartisan\s+(?:migrate|db:wipe|schema:drop)\b.*--env(?:=|\s+)production)"
)
if re.search(production_database, normalized, re.IGNORECASE):
    deny("Production database operations are disabled.")

deployment_commands = [
    r"\bkubectl\s+(?:apply|delete|rollout)\b",
    r"\bterraform\s+(?:apply|destroy)\b",
    r"\b(?:vercel|netlify)\b.*\b(?:deploy|--prod)\b",
    r"\b(?:fly\s+deploy|railway\s+up|vapor:deploy)\b",
]
if any(re.search(pattern, normalized, re.IGNORECASE) for pattern in deployment_commands):
    deny("Deployments are disabled; a human must run them explicitly.")

secret_access = (
    r"\b(?:cat|less|more|rg|grep|awk|sed)\b.*"
    r"(?:^|[/.\s])(?:\.env(?:\.\w+)?|credentials(?:\.json)?|id_rsa|\.ssh|\.aws)(?:\s|$)"
)
if re.search(secret_access, normalized, re.IGNORECASE) or re.search(
    r"\b(?:printenv|env)\b\s*$", normalized
):
    deny("Unattended secret or environment inspection is disabled.")

if re.search(r"(?:^|[;&|]\s*)sudo\b", normalized):
    deny("Privileged commands are disabled.")

workspace = Path(os.getcwd()).resolve()
mutating_command = re.search(
    r"(?:^|[;&|]\s*)(?:rm|mv|cp|install|mkdir|touch|chmod|chown|tee)\b|(?:^|[^>])>{1,2}\s*/",
    normalized,
)

if mutating_command:
    for raw_path in re.findall(r"(?<![\w.-])(/[^\s;&|><'\"]+)", normalized):
        path = Path(raw_path).resolve(strict=False)
        if path == Path("/dev/null"):
            continue

        if path != workspace and workspace not in path.parents:
            deny(f"Writes outside the workspace are disabled ({raw_path}).")

print(json.dumps({"permission": "allow"}))
