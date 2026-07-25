# Security policy

## Supported versions

This repository is an interview / portfolio proof of concept. Security fixes are
applied on `main` only.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security-sensitive findings.

Email the repository owner through the contact method listed on their GitHub
profile, or open a private GitHub security advisory if the repository has that
feature enabled.

Include:

- Affected commit SHA or release tag
- Impact (data exposure, integrity of financial totals, RCE, etc.)
- Reproduction steps or proof-of-concept description
- Suggested remediation if available

## Project-specific expectations

- Do not commit real bank credentials, Plaid secrets, or personal financial
  data.
- Prefer synthetic seed data for demos.
- ClearSpend’s `X-Demo-User` header is a **demo tenancy selector**, not secure
  authentication. Do not describe it as production auth.
- Even in this demo model, financial endpoints must not leak one persona’s
  records to another selected persona.
- Treat financial calculation bugs as security-relevant integrity issues.
- CI and agent workflows must not print secrets or dump `.env` contents.
