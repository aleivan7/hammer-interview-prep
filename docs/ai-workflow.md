# AI development workflow

This project uses layered guidance, on-demand skills, Laravel Boost, deterministic
verification, and bounded Ralph iterations. Human review remains the final gate.

## Daily feature workflow

1. Describe the user-visible goal, constraints, non-goals, and acceptance
   criteria.
2. For nontrivial work, check the available skills and MCP tools before
   planning. Do not install anything unless a real gap exists and the user
   approves the source and permissions.
3. Use Cursor Plan Mode for ambiguous or multi-file work. Save an approved
   feature spec and implementation plan under `docs/specs/` when work must
   survive fresh contexts.
4. Create a short-lived branch from current `main` (`feature/*`, `fix/*`,
   `chore/*`, or `docs/*`). Do not use a permanent `dev` branch.
5. Implement the smallest ready task.
6. Run a narrow test while iterating, then run:

   ```bash
   ./scripts/verify.sh
   ```

7. Open or update a pull request. Require:
   - green CI (`./scripts/verify.sh` in GitHub Actions);
   - Cursor Bugbot with fail-on-unresolved when available;
   - an independent security-review agent pass recorded in the PR;
   - explicit human authorization before squash-merge to `main`.
8. Review `git status` and the diff. Interactive agents: the user decides
   whether to commit, push, open a pull request, merge, or deploy. See
   [`CONTRIBUTING.md`](../CONTRIBUTING.md).

## Cursor Automations

Approved cloud automations operate under their configured prompts, not
per-message chat approval. Named automations and scopes live in
[`AGENTS.md`](../AGENTS.md):

- **Add test coverage** and **Generate docs** may create a short-lived
  `test/*` or `docs/*` branch, commit, push, and open or update a PR.
- **Summarize changes daily** is Git/GitHub read-only and may post only to its
  configured destination.
- No automation may merge, deploy, force-push, skip hooks, commit secrets, or
  access production.
- Automations run narrow/relevant checks and record evidence in the PR;
  `./scripts/verify.sh` on CI is the merge gate.
- Interactive chat agents and Ralph CLI still follow the supervised workflow
  above. Ralph remains no-push.

## Installed capabilities

Global skills:

- `brainstorming`: ambiguous or architectural requirement discovery
- `systematic-debugging`: evidence-first bug investigation
- `verification-before-completion`: fresh proof before completion claims

Project skills:

- `vue-best-practices`
- `vue-testing-best-practices`
- `backend/.agents/skills/laravel-best-practices`
- `.cursor/skills/ralph-iteration` (explicit invocation only)

Laravel Boost is the only project MCP server. Its configuration is in
`.cursor/mcp.json`. Use read-only application information, routes, schema, and
version-aware documentation first. Database queries, code execution, and logs
require human approval.

After changing MCP configuration, reload Cursor if the server does not appear.
The server can be smoke-tested without Cursor:

```bash
printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"verify","version":"1.0"}}}' |
  php backend/artisan boost:mcp
```

## Supervised Ralph iteration

Use this first. Prepare documents from:

- `docs/specs/FEATURE_SPEC_TEMPLATE.md`
- `docs/specs/IMPLEMENTATION_PLAN_TEMPLATE.md`

Then explicitly ask Cursor to use the `ralph-iteration` skill with those two
paths. One invocation selects one ready task, verifies it, records evidence,
and stops for review. Invoke it again only after reviewing the diff.

The documentation-only example is:

- `docs/specs/ralph-dry-run.md`
- `docs/specs/ralph-dry-run-plan.md`
- `docs/specs/ralph-dry-run-result.md`

## Bounded fresh-context CLI pilot

Prerequisites:

```bash
agent status
agent models
./scripts/verify.sh
git status --short
```

The working tree must be clean. Start with one iteration:

```bash
./scripts/ralph.sh \
  --spec docs/specs/my-feature.md \
  --plan docs/specs/my-feature-plan.md \
  --max-iterations 1 \
  --max-seconds 1200 \
  --max-diff-lines 400
```

Only increase to three iterations after successful reviewed runs. Every
iteration gets a fresh Agent process, but all iterations share one isolated
`ralph/*` worktree so completed work is durable. The controller:

- enables the Agent sandbox;
- denies Git, GitHub CLI, and deployment commands inside Agent;
- runs the external verification gate;
- stops on timeout, no progress, excessive diff size, or verification failure;
- leaves logs and the worktree under `.ralph/` for human review;
- never pushes, merges, or deploys.

Cursor Agent CLI does not expose a deterministic dollar-cost limit. The
iteration count and wall-clock timeout are the enforced cost proxies.

Checkpoint commits are off by default. A human may explicitly allow local
checkpoint commits for one run:

```bash
./scripts/ralph.sh \
  --spec docs/specs/my-feature.md \
  --plan docs/specs/my-feature-plan.md \
  --checkpoint-commits
```

This never authorizes pushing.

Validate controller inputs without starting Agent:

```bash
./scripts/ralph.sh \
  --spec docs/specs/ralph-dry-run.md \
  --plan docs/specs/ralph-dry-run-plan.md \
  --dry-run
```

## Stopping and recovery

- Stop an interactive Agent with `Ctrl+C`.
- Stop a running controller with `Ctrl+C`; it leaves the isolated worktree and
  logs intact.
- Inspect runs with `git worktree list`, `git -C <worktree> status --short`,
  and the files under `.ralph/logs/`.
- Never use a hard reset to recover. Copy wanted changes or create a
  user-approved commit, then remove a finished worktree with:

  ```bash
  git worktree remove <worktree-path>
  git branch -d <ralph-branch>
  ```

- If verification fails, investigate the first failure before another
  iteration.
- If a hook blocks a command unexpectedly, inspect `.cursor/hooks.json`, run
  `python3 .cursor/hooks/test_safety.py`, and change the narrow rule rather than
  disabling every boundary.

## Disable or remove capabilities

- Temporarily disable Laravel Boost in Cursor settings, or remove its entry
  from `.cursor/mcp.json` after review.
- Remove project skills by deleting their project skill directory and updating
  `skills-lock.json` when applicable.
- Remove a globally installed skill with:

  ```bash
  npx skills remove --global --skill <name> --yes
  ```

- Disable project hooks by renaming `.cursor/hooks.json`, then reload Cursor.
  Restore it before any autonomous run.
- Use `/loop` only for monitoring or waiting in one session. It is not the
  fresh-context coding controller.
