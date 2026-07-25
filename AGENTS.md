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

- Keep the API public and stateless; do not add authentication unless requested.
- Use conventional Laravel controllers, Form Requests, Resources, Eloquent
  models, migrations, factories, and feature tests.
- Use Vue Composition API with `<script setup lang="ts">`, native `fetch`, and
  small components. Do not add Router, Pinia, Axios, or a UI framework without
  a demonstrated need.
- Keep API request/response types explicit and preserve Laravel Resource
  `{ "data": ... }` envelopes.
- Use SQLite locally and in backend tests.
- Never edit `.env` secrets or the database file unless the task requires it.

## GitHub delivery

- Trunk-based GitHub Flow: protect `main`, use short-lived PR branches, squash
  merge, delete branches. See `CONTRIBUTING.md`.
- Every PR needs local `./scripts/verify.sh`, Cursor Bugbot, an independent
  security-review agent pass, and explicit human merge approval.

## Verification

- Add or update tests for behavior changes.
- Run the narrow relevant test first, then `./scripts/verify.sh` before claiming
  completion.
- Treat a passing baseline as required before autonomous iteration.
- Never push, merge, deploy, or make checkpoint commits without explicit user
  approval.
