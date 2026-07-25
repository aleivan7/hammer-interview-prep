# Implementation plan: ClearSpend weekend POC

Feature spec: `docs/specs/clearspend.md`
Status: Approved

## Tasks

### TASK-001: Durable specs and baseline

Status: complete
Depends on: none

Acceptance criteria:

- [x] Feature spec and implementation plan exist under `docs/specs/`.
- [x] `./scripts/verify.sh` passes on current `main` baseline.

Evidence:

- 2026-07-25 — `./scripts/verify.sh` — pass (10 PHPUnit, 6 Vitest, typecheck, build).
- Specs written: `clearspend.md`, `clearspend-plan.md`.

### TASK-002: Scaffold, financial invariants, smoke tests

Status: complete
Depends on: TASK-001

Acceptance criteria:

- [x] Backend enums/contracts/services folders and frontend router/layout/types scaffold exist.
- [x] Money helpers and invariant unit tests for cents math pass.
- [x] Smoke/contract tests for API envelope and frontend mount pass.

Evidence:

- Backend: `app/Enums`, `app/Contracts`, `app/Services`, `app/Support`, Money + SafeToSpend unit tests.
- Frontend: `src/router`, `src/layouts/AppShell.vue`, typed `src/api` / `src/types`, navigation smoke test.
- `TransactionCategorizer` bound in `AppServiceProvider`.

### TASK-003: Domain, seed, dashboard/transaction/rule APIs

Status: complete
Depends on: TASK-002

Acceptance criteria:

- [x] Accounts, plan, cash flows, rules, audit, and cent-based transactions persist.
- [x] Dashboard, transactions CRUD/filter, review, undo, Smart Review, rules APIs work.
- [x] Feature/unit tests cover safe-to-spend, rules precedence, batch retry safety.

Evidence:

- Migrations + `ClearSpendSeeder`, factories.
- Feature tests: `DashboardApiTest`, `TransactionApiTest`, `SmartReviewApiTest`,
  `CategorizationRuleApiTest`; unit: `MoneyTest`, `SafeToSpendServiceTest`.
- Smart Review reconstructs prior applied rows for the same `batch_key`.

### TASK-004: Shell, Overview, Activity

Status: complete
Depends on: TASK-003

Acceptance criteria:

- [x] Desktop sidebar shell with Overview and Activity routes.
- [x] Overview shows safe-to-spend breakdown and insights from API.
- [x] Activity table supports search/filters and create/edit.

Evidence:

- Routes `/`, `/activity` via Vue Router + `AppShell`.
- `OverviewView` + overview components; `ActivityView` + table/filters/dialog.

### TASK-005: Review, Smart Review, Rules

Status: complete
Depends on: TASK-004

Acceptance criteria:

- [x] Multi-input review (drag/keyboard/buttons) with undo.
- [x] Smart Review batch UI with explanations.
- [x] Rules builder CRUD wired to API.

Evidence:

- `TransactionReviewView` drag mapping (L=Wants, R=Needs, D=Savings), shortcuts, undo, Smart Review.
- `RulesView` CRUD for merchant/account/amount/bucket/priority/enabled/auto_review.

### TASK-006: Polish, verify, document

Status: complete
Depends on: TASK-005

Acceptance criteria:

- [x] Responsive/a11y polish and expanded frontend tests.
- [x] README rewritten for ClearSpend.
- [x] `./scripts/verify.sh` passes.

Evidence:

- Dark ClearSpend tokens in `src/style.css`; mobile nav in shell.
- Vitest: review coverage + `App.navigation.test.ts`.
- README / AGENTS / this plan updated for ClearSpend + Vue Router approval.

## Verification log

- 2026-07-25 — `./scripts/verify.sh` — pass on foundation baseline before ClearSpend work.
- 2026-07-25 — `./scripts/verify.sh` on `feature/clearspend-poc` — pass (29 PHPUnit, 9 Vitest, typecheck, build, pint).
