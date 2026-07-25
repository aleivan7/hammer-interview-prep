# Contributing

## Branching model

This repository uses **GitHub Flow** (trunk-based):

- `main` is the only long-lived branch and must stay green.
- Create short-lived branches from current `main`:
  - `feature/<short-name>`
  - `fix/<short-name>`
  - `chore/<short-name>`
  - `docs/<short-name>`
  - `test/<short-name>` (automation or test-only PRs)
- Open a pull request early, keep it small, and squash-merge into `main`.
- Delete the branch after merge.
- Do **not** maintain a permanent `dev` branch.

## Local quality gate

```bash
./scripts/verify.sh
```

Run the narrowest relevant test while iterating, then the full gate before asking
for review.

### Automation-authored pull requests

PRs opened by the repository-approved Cursor Automations (**Add test coverage**,
**Generate docs**) follow the same merge gates. Those automations may create a
`test/*` or `docs/*` branch, commit, push, and open or update a PR when their
run produces a useful change. They must:

1. Run the narrowest relevant tests for the diff.
2. Paste command output in the PR test plan.
3. Rely on green CI (`./scripts/verify.sh` in GitHub Actions) for the full gate
   when the automation environment cannot mirror a local workstation.

**Summarize changes daily** is Git/GitHub read-only. No automation may merge,
deploy, force-push, skip hooks, or access production. Ralph CLI iterations
remain no-push.

## Repository security settings

After the foundation lands on `main`, enable these under
**Settings → Code security and analysis**:

- Dependency graph
- Dependabot alerts / security updates (optional but recommended)
- Secret scanning / push protection when available
- Cursor Bugbot with **fail on unresolved issues**

Dependency Review CI is soft-gated until the dependency graph is enabled.

## Pull request expectations

Use the PR template. Every PR should include:

1. Scope and non-goals
2. Decision rationale in the author's own words, including meaningful tradeoffs
3. A linked durable spec/task when applicable (`docs/specs/`)
4. An entry in `docs/change-history.md` with the PR's what, why, decision
   context, and supporting links
5. Financial-data impact notes when money math or categorization changes
6. Test evidence (narrow automation/local commands and/or green CI
   `./scripts/verify.sh`)
7. Two AI review passes before human merge approval:
   - Cursor Bugbot on the PR
   - Independent security-review agent; paste the outcome into the PR
8. Explicit human authorization to squash-merge

### Merged change history

`docs/change-history.md` is the repository's durable index of merged work.
Update it in the same PR before merge:

- Add one newest-first entry for every PR, including docs, tests, and chores.
- Once the PR is open, use its actual number and URL.
- Faithfully condense rationale from the author, issue, or linked spec. Do not
  invent missing motives or present an agent inference as the author's view.
- Record meaningful constraints, rejected alternatives, and non-goals when they
  explain the implementation.
- For product or architectural choices, also add a dated, PR-linked item to the
  relevant spec's `Decisions` section.

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
