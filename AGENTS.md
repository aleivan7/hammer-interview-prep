# Hammer Interview Prep

## Structure

- `frontend/`: Vue 3.5, Vite, and strict TypeScript. This is the real browser
  application.
- `backend/`: Laravel 13 JSON API with SQLite.
- Laravel's default frontend files under `backend/` are not the application UI.
- Vite proxies `/api` to Laravel at `http://127.0.0.1:8000`.

## Canonical commands

From the repository root:

- `./scripts/verify.sh`: run all project quality gates.
- `cd frontend && npm run dev`: start Vue.
- `cd backend && php artisan serve`: start Laravel.
- `cd backend && php artisan migrate:fresh --seed`: reset local data.
- `cd backend && php artisan test`: run backend tests.
- `cd frontend && npm run typecheck`: type-check Vue.
- `cd frontend && npm run test`: run frontend tests.
- `cd frontend && npm run build`: build Vue.

## Conventions

- Keep the API public and stateless. ClearSpend uses a **demo persona-selection
  flow** (`X-Demo-User` header + `localStorage`), not production authentication.
  Do not add Sanctum, Breeze, passwords, cookies, JWT, or OAuth unless
  explicitly requested.
- Scope all financial queries to the resolved demo user. Route-model binding
  alone is not sufficient ownership protection.
- Use conventional Laravel controllers, Form Requests, Resources, Eloquent
  models, migrations, factories, and feature tests.
- Use Vue Composition API with `<script setup lang="ts">`, native `fetch`, and
  small components. Vue Router is approved for ClearSpend routes (`/login`,
  Overview, Activity, Review, Rules, `/profile`). Do not add Pinia, Axios, or a
  UI framework without a demonstrated need.
- Keep API request/response types explicit and preserve Laravel Resource
  `{ "data": ... }` envelopes.
- Use SQLite locally and in backend tests.
- Never edit `.env` secrets or the database file unless the task requires it.

## GitHub delivery

- Trunk-based GitHub Flow: protect `main`, use short-lived PR branches, squash
  merge, delete branches. See `CONTRIBUTING.md`.
- Every PR must update `docs/change-history.md` with a faithful account of what
  changed, why, and the recorded human/product decision context. Do not invent
  rationale. Link product or architectural decisions from the history entry to
  the relevant spec's `Decisions` section.
- Every PR needs green verification (`./scripts/verify.sh` locally and/or CI),
  Cursor Bugbot, an independent security-review agent pass, and explicit human
  merge approval.

## Cursor Automations

These named Cursor Automations are pre-authorized by their configured prompts.
That authorization is least-privilege and does not grant merge, deploy, or
production access.

| Automation | Git / GitHub | Notes |
|---|---|---|
| **Add test coverage** | May create a `test/*` branch, commit, push, and open/update a PR | Tests only; run narrow relevant targets and record evidence in the PR |
| **Generate docs** | May create a `docs/*` branch, commit, push, and open/update a PR | Documentation-focused PRs under `docs/` and related README/AGENTS files |
| **Summarize changes daily** | Read-only for Git and GitHub | May post the digest to its configured destination only |

Hard boundaries for all agents and automations:

- Do not merge, deploy, access production, force-push, skip hooks, commit
  secrets or the SQLite database, change dependencies, or install/authenticate
  tools without a separate explicit human decision.
- Automation PRs still require green CI (`./scripts/verify.sh`), Cursor Bugbot,
  an independent security-review pass, and explicit human squash-merge approval.
- Report failures clearly; do not bypass quality gates.

Ralph CLI iterations remain separate and must not push, merge, or deploy.

## Verification

- Add or update tests for behavior changes.
- Interactive agents: run the narrow relevant test first, then
  `./scripts/verify.sh` before claiming completion.
- Approved Cursor Automations: run the narrowest relevant targets for the
  diff, record commands and outcomes in the PR, and rely on CI for the full
  gate before merge.
- Treat a passing baseline as required before autonomous iteration.
- Interactive agents: do not commit, push, merge, deploy, or make checkpoint
  commits without explicit user approval in the current session.
