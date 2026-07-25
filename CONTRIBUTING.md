# Contributing

## Branching model

This repository uses **GitHub Flow** (trunk-based):

- `main` is the only long-lived branch and must stay green.
- Create short-lived branches from current `main`:
  - `feature/<short-name>`
  - `fix/<short-name>`
  - `chore/<short-name>`
  - `docs/<short-name>`
- Open a pull request early, keep it small, and squash-merge into `main`.
- Delete the branch after merge.
- Do **not** maintain a permanent `dev` branch.

## Local quality gate

```bash
./scripts/verify.sh
```

Run the narrowest relevant test while iterating, then the full gate before asking
for review.

## Pull request expectations

Use the PR template. Every PR should include:

1. Scope and non-goals
2. Linked durable spec/task when applicable (`docs/specs/`)
3. Financial-data impact notes when money math or categorization changes
4. Test evidence (`./scripts/verify.sh` outcome)
5. Two AI review passes before human merge approval:
   - Cursor Bugbot on the PR
   - Independent security-review agent; paste the outcome into the PR
6. Explicit human authorization to squash-merge

AI comments are advisory gates. They do not replace human judgment, and they
do not count as GitHub “required approving reviews.”

## Commit style

Prefer Conventional Commit subjects:

- `feat:` user-visible capability
- `fix:` bug fix
- `chore:` tooling / workflow / scaffolding
- `docs:` documentation only
- `test:` tests only
- `refactor:` behavior-preserving structure change

## Safety

- Never commit `.env`, credentials, personal financial data, or the SQLite
  database file.
- Never force-push `main`, skip hooks, or deploy without explicit approval.
- Prefer integer-cent financial math and server-authoritative totals in feature
  work that touches money.
