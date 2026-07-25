#!/usr/bin/env python3

import json
import sys
from typing import Any


def string_for_keys(value: Any, keys: set[str]) -> str:
    if isinstance(value, dict):
        for key, nested in value.items():
            if key in keys and isinstance(nested, str):
                return nested

        for nested in value.values():
            found = string_for_keys(nested, keys)
            if found:
                return found

    if isinstance(value, list):
        for nested in value:
            found = string_for_keys(nested, keys)
            if found:
                return found

    return ""


payload = json.load(sys.stdin)
tool = string_for_keys(payload, {"tool_name"}).lower()

sensitive_tools = (
    "database-query",
    "tinker",
    "execute-php",
    "browser-logs",
    "last-error",
    "read-log-entries",
)

if tool in sensitive_tools:
    print(
        json.dumps(
            {
                "permission": "ask",
                "user_message": (
                    "Laravel Boost is requesting database, code-execution, or "
                    "log access. Review the exact MCP call before allowing it."
                ),
                "agent_message": "This sensitive Laravel Boost call requires human approval.",
            }
        )
    )
else:
    print(json.dumps({"permission": "allow"}))
