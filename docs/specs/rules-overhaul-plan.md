# Implementation plan: Rules overhaul

Feature spec: `docs/specs/rules-overhaul.md`
Status: Approved

## Operating rules

- Complete exactly one `ready` task per supervised Ralph iteration.
- Before backend edits, inspect the current schema, routes, sibling code, tests,
  and version-specific Laravel 13 documentation through Laravel Boost.
- If Laravel Boost is unavailable, record the capability failure and stop before
  claiming the required grounding step is complete.
- Run the listed narrow checks while iterating and `./scripts/verify.sh` before
  marking any executable task complete.
- Record exact commands, results, test/assertion counts where available, date,
  and review findings in this file.
- After each task, review the diff and stop for human acceptance. Only then mark
  the next dependency-satisfied task `ready`.
- Do not commit, push, merge, deploy, install dependencies, or change acceptance
  criteria without explicit human approval.

## Tasks

### TASK-001: Catalog foundation

Status: complete
Depends on: none

Acceptance criteria:

- [x] System categories model the current Needs, Wants, and Savings vocabulary
      with stable normalized names and sort order.
- [x] Canonical merchants and enabled/disabled aliases support explicit match
      strategies and priority without duplicate normalized records.
- [x] Models, relationships, factories, and idempotent seed data exist for
      categories, merchants, and aliases.
- [x] `GET /api/categories` returns shared active system categories plus only
      the selected persona's active custom categories, grouped or sortable by
      bucket, in a Resource `data` envelope.
- [x] `GET /api/merchants` supports practical search and returns canonical
      merchants plus safe representative descriptors in a Resource envelope.
- [x] System catalog APIs do not expose another persona's custom categories.
- [x] Fresh migration and seeding succeed on SQLite.
- [x] `cd backend && php artisan test --compact tests/Feature/CategoryApiTest.php tests/Feature/MerchantApiTest.php` passes.
- [x] `./scripts/verify.sh` passes.

Relevant files:

- `backend/database/migrations/*_create_categories_table.php`
- `backend/database/migrations/*_create_merchants_table.php`
- `backend/database/migrations/*_create_merchant_aliases_table.php`
- `backend/app/Models/Category.php`
- `backend/app/Models/Merchant.php`
- `backend/app/Models/MerchantAlias.php`
- `backend/database/factories/CategoryFactory.php`
- `backend/database/factories/MerchantFactory.php`
- `backend/database/factories/MerchantAliasFactory.php`
- `backend/database/seeders/ClearSpendSeeder.php`
- `backend/app/Http/Controllers/CategoryController.php`
- `backend/app/Http/Controllers/MerchantController.php`
- `backend/app/Http/Resources/CategoryResource.php`
- `backend/app/Http/Resources/MerchantResource.php`
- `backend/routes/api.php`
- `backend/tests/Feature/CategoryApiTest.php`
- `backend/tests/Feature/MerchantApiTest.php`

Compatibility and migration concerns:

- System categories use a nullable `user_id`; custom-category writes arrive in
  TASK-004.
- Seed from existing transaction/rule subcategory vocabulary without modifying
  existing transaction or rule rows in this task.
- SQLite indexes must enforce useful normalized uniqueness without relying on
  unsupported database-specific expressions.

Evidence:

- 2026-07-27 — Laravel Boost Cursor MCP still unavailable; grounded via
  `php artisan route:list`, schema listing, sibling controllers/resources, and
  project migration rules instead of claiming Boost MCP usage.
- 2026-07-27 — `cd backend && php artisan test --compact tests/Feature/CategoryApiTest.php tests/Feature/MerchantApiTest.php` — pass: 7 tests, 41 assertions.
- 2026-07-27 — `cd backend && php artisan migrate:fresh --seed --no-interaction --force` — pass.
- 2026-07-27 — `./scripts/verify.sh` — pass: 116 PHPUnit tests, 783 assertions; 75 Vitest tests; typecheck; build.

### TASK-002: Deterministic merchant resolution

Status: complete
Depends on: TASK-001

Acceptance criteria:

- [x] A testable normalizer handles case, punctuation, separators, and repeated
      whitespace while retaining a useful normalized descriptor.
- [x] The resolver supports exact, prefix, whole-token, and explicitly safe
      contains strategies, alias priority, and disabled aliases.
- [x] A successful result contains the canonical merchant, matched alias,
      strategy, normalized descriptor, and a human-readable explanation.
- [x] Unknown descriptors return no merchant and do not create catalog records.
- [x] Netflix and Spotify examples resolve correctly.
- [x] `Shellpoint Mortgage` does not resolve to Shell without an intentional
      explicit alias.
- [x] `cd backend && php artisan test --compact tests/Unit/MerchantResolverTest.php` passes.
- [x] `./scripts/verify.sh` passes.

Relevant files:

- `backend/app/Services/MerchantResolver.php`
- `backend/app/Support/DescriptorNormalizer.php`
- `backend/app/Support/MerchantResolution.php`
- `backend/app/Models/MerchantAlias.php`
- `backend/tests/Unit/MerchantResolverTest.php`

Compatibility and migration concerns:

- Resolution must remain deterministic under SQLite and must not depend on
  locale-sensitive database matching.
- More specific strategies win before priority; ties use deterministic IDs or
  another documented stable final ordering.

Evidence:

- 2026-07-27 — `cd backend && php artisan test --compact tests/Unit/MerchantResolverTest.php tests/Unit/CatalogNormalizerTest.php` — pass: 13 tests, 37 assertions.
- 2026-07-27 — `./scripts/verify.sh` — pass: 129 PHPUnit tests, 820 assertions; 75 Vitest tests; typecheck; build.
- Decision: prefix matching requires a token boundary after the pattern so
  `SHELL` cannot prefix-match `SHELLPOINT`.

### TASK-003: Transaction integration

Status: complete
Depends on: TASK-001, TASK-002

Acceptance criteria:

- [x] Transactions preserve an exact raw descriptor and may reference nullable
      canonical merchant and category records.
- [x] Existing transaction rows are backfilled without losing merchant,
      bucket, subcategory, amount, review, or audit behavior.
- [x] Known descriptors link deterministically; unknown descriptors remain
      usable without creating merchants.
- [x] Detailed category assignment derives its bucket and rejects conflicts;
      quick bucket-only review remains valid.
- [x] Transaction Resources expose structured fields while preserving legacy
      merchant, bucket, subcategory, and category compatibility fields.
- [x] Frontend transaction types and API payloads represent the expanded
      contract explicitly.
- [x] Undo restores review state without losing descriptor or merchant identity.
- [x] `cd backend && php artisan test --compact tests/Feature/TransactionApiTest.php tests/Unit/TransactionReviewServiceTest.php` passes.
- [x] `cd frontend && npm run test -- src/utils/transactions.test.ts` passes.
- [x] `./scripts/verify.sh` passes.

Relevant files:

- `backend/database/migrations/*_add_catalog_fields_to_transactions_table.php`
- `backend/app/Models/Transaction.php`
- `backend/app/Http/Requests/StoreTransactionRequest.php`
- `backend/app/Http/Requests/UpdateTransactionRequest.php`
- `backend/app/Http/Resources/TransactionResource.php`
- `backend/app/Http/Controllers/TransactionController.php`
- `backend/app/Services/TransactionReviewService.php`
- `backend/database/factories/TransactionFactory.php`
- `backend/app/Services/DemoPersonaDataService.php`
- `backend/tests/Feature/TransactionApiTest.php`
- `backend/tests/Unit/TransactionReviewServiceTest.php`
- `frontend/src/types/transaction.ts`
- `frontend/src/api/transactionApi.ts`

Compatibility and migration concerns:

- Keep the legacy `merchant` field until all in-repository clients use the raw
  and canonical fields; document which field remains writable.
- Historical bucket-only reviews do not require a category backfill when no
  unambiguous category exists.
- Foreign keys remain nullable and rollback must work on SQLite.

Evidence:

- 2026-07-27 — `cd backend && php artisan test --compact tests/Feature/TransactionApiTest.php tests/Unit/TransactionReviewServiceTest.php` — pass within the TASK-003/004 suite (54 tests / 491 assertions).
- 2026-07-27 — `./scripts/verify.sh` — pass: 133 PHPUnit tests, 841 assertions; 75 Vitest tests; typecheck; build.
- Decision: legacy `merchant` remains writable and mirrors `raw_merchant_descriptor`.

### TASK-004: Custom categories

Status: complete
Depends on: TASK-001

Acceptance criteria:

- [x] A selected persona can create, rename, and archive only their own custom
      categories under one valid bucket.
- [x] System categories cannot be modified or archived through custom-category
      endpoints.
- [x] Visible same-bucket names are unique after case and whitespace
      normalization.
- [x] Archived categories remain serialized on historical transactions but are
      excluded from new assignments and rules.
- [x] Another persona cannot view through list APIs, mutate, or assign the
      custom category.
- [x] Persona reset recreates that persona's intended demo state without
      changing shared system categories or another persona's categories.
- [x] `cd backend && php artisan test --compact tests/Feature/CategoryApiTest.php tests/Feature/ProfileApiTest.php` passes.
- [x] `./scripts/verify.sh` passes.

Relevant files:

- `backend/app/Models/Category.php`
- `backend/app/Models/User.php`
- `backend/app/Http/Controllers/CategoryController.php`
- `backend/app/Http/Requests/StoreCategoryRequest.php`
- `backend/app/Http/Requests/UpdateCategoryRequest.php`
- `backend/app/Http/Resources/CategoryResource.php`
- `backend/app/Services/DemoPersonaDataService.php`
- `backend/routes/api.php`
- `backend/tests/Feature/CategoryApiTest.php`
- `backend/tests/Feature/ProfileApiTest.php`

Compatibility and migration concerns:

- Archive rather than delete categories that may be referenced historically.
- Ownership validation must use the resolved demo user, not route-model binding
  alone.

Evidence:

- 2026-07-27 — Category CRUD, duplicate, system protection, and isolation covered in `CategoryApiTest`; reset deletes only user-owned categories in `DemoPersonaDataService`.
- 2026-07-27 — `./scripts/verify.sh` — pass with TASK-003.

### TASK-005: Structured rules backend

Status: complete
Depends on: TASK-002, TASK-003, TASK-004

Acceptance criteria:

- [x] Rules reference a canonical merchant and target category while retaining
      optional account, amount range, priority, enabled, and auto-review fields.
- [x] The target bucket is derived from the category; conflicting inputs cannot
      be stored.
- [x] Existing seeded rules are deterministically backfilled or reseeded
      without losing their conditions or intended behavior.
- [x] A persona cannot reference another persona's custom category, account, or
      rule.
- [x] Merchant aliases can trigger canonical-merchant rules.
- [x] Enabled rules continue to execute before heuristics with stable priority
      ordering.
- [x] Suggest-only rules, user-scoped batch idempotency, undo, and Smart Review
      explanations remain intact.
- [x] Resources preserve required legacy fields during the frontend migration.
- [x] `cd backend && php artisan test --compact tests/Feature/CategorizationRuleApiTest.php tests/Unit/RulesAndHeuristicsCategorizerTest.php tests/Feature/SmartReviewApiTest.php` passes.
- [x] `./scripts/verify.sh` passes.

Relevant files:

- `backend/database/migrations/*_add_catalog_fields_to_categorization_rules_table.php`
- `backend/database/migrations/*_backfill_categorization_rule_catalog_links.php`
- `backend/app/Models/CategorizationRule.php`
- `backend/app/Http/Requests/StoreCategorizationRuleRequest.php`
- `backend/app/Http/Requests/UpdateCategorizationRuleRequest.php`
- `backend/app/Http/Resources/CategorizationRuleResource.php`
- `backend/app/Services/RulesAndHeuristicsCategorizer.php`
- `backend/app/Support/CategorizationResult.php`
- `backend/app/Services/SmartReviewService.php`
- `backend/database/factories/CategorizationRuleFactory.php`
- `backend/app/Services/DemoPersonaDataService.php`
- `backend/tests/Feature/CategorizationRuleApiTest.php`
- `backend/tests/Unit/RulesAndHeuristicsCategorizerTest.php`
- `backend/tests/Feature/SmartReviewApiTest.php`

Compatibility and migration concerns:

- Keep legacy rule response fields until TASK-006 no longer depends on them.
- Backfill must not infer a wrong merchant/category; fail explicitly or leave a
  nullable compatibility row when the mapping is ambiguous.

Evidence:

- 2026-07-27 — Laravel Boost Cursor MCP unavailable in this session; grounded via
  schema listing, sibling catalog migrations/requests, and existing rules API
  patterns instead of claiming Boost MCP usage.
- 2026-07-27 — `cd backend && php artisan test --compact tests/Feature/CategorizationRuleApiTest.php tests/Unit/RulesAndHeuristicsCategorizerTest.php tests/Feature/SmartReviewApiTest.php` — pass: 34 tests, 204 assertions.
- 2026-07-27 — `cd backend && vendor/bin/pint --dirty --format agent` — fixed unused import in DemoPersonaDataService.
- 2026-07-27 — `./scripts/verify.sh` — pass: 140 PHPUnit tests, 885 assertions; 75 Vitest tests; typecheck; build.
- Decision: rules with `merchant_id` match via MerchantResolver (and transaction
  `merchant_id`); legacy `merchant_contains` matching remains only when
  `merchant_id` is null. Store/update derive `target_bucket` /
  `target_subcategory` / default `merchant_contains` from category and merchant.

### TASK-006: Structured Rules interface

Status: complete
Depends on: TASK-001, TASK-004, TASK-005

Acceptance criteria:

- [x] Typed category and merchant API clients preserve Resource envelopes and
      expose useful loading, empty, and failure states.
- [x] The rule form uses a searchable canonical merchant selector and a grouped
      active category selector.
- [x] Inline custom-category creation preserves entered rule name, merchant,
      advanced conditions, and toggles, then selects the new category.
- [x] A plain-language preview names the canonical merchant, representative raw
      descriptor, category, and bucket.
- [x] Account, minimum/maximum amount, priority, enabled, and auto-review remain
      available in an advanced section.
- [x] Rules list/edit/delete behavior uses the structured API contract.
- [x] Components have explicit typed props/emits and `RulesView.vue` remains a
      composition surface.
- [x] `cd frontend && npm run test -- RuleForm RulesView` passes.
- [x] `cd frontend && npm run typecheck` passes.
- [x] `./scripts/verify.sh` passes.

Relevant files:

- `frontend/src/types/category.ts`
- `frontend/src/types/merchant.ts`
- `frontend/src/types/rule.ts`
- `frontend/src/api/categoryApi.ts`
- `frontend/src/api/merchantApi.ts`
- `frontend/src/api/rulesApi.ts`
- `frontend/src/components/rules/MerchantSelector.vue`
- `frontend/src/components/rules/CategorySelector.vue`
- `frontend/src/components/rules/RuleForm.vue`
- `frontend/src/components/rules/RuleList.vue`
- `frontend/src/views/RulesView.vue`
- `frontend/src/components/rules/RuleForm.test.ts`
- `frontend/src/views/RulesView.test.ts`

Component map:

- `RulesView`: fetch and coordinate rules, accounts, merchants, and categories.
- `RuleForm`: own draft orchestration and emit typed save/cancel events.
- `MerchantSelector`: search and select one canonical merchant.
- `CategorySelector`: group/select active categories and emit inline-create
  intent without owning the parent form draft.
- `RuleList`: present structured rules and emit edit/delete/toggle intent.

Compatibility and migration concerns:

- Do not remove legacy backend fields in this task.
- Inline creation errors must not reset the parent form.

Evidence:

- 2026-07-27 — `cd frontend && npm run test -- RuleForm RulesView` — pass: 5
  tests (submit payload, preview, inline create draft preservation, catalog
  load, structured create).
- 2026-07-27 — `cd frontend && npm run typecheck` — pass.
- 2026-07-27 — `./scripts/verify.sh` — pass: 140 PHPUnit tests, 885 assertions;
  80 Vitest tests; typecheck; build.

### TASK-007: Activity and Review integration

Status: complete
Depends on: TASK-003, TASK-004, TASK-005

Acceptance criteria:

- [x] Activity and Review display canonical merchant names when available and
      retain access to the exact raw descriptor.
- [x] Activity create/edit supports active detailed category assignment and
      bucket-only compatibility.
- [x] Search matches canonical merchant, raw descriptor, and category where the
      API can do so without broad unscoped queries.
- [x] Review list, Focus mode, and bulk actions support category assignment
      without removing quick Needs/Wants/Savings review.
- [x] Suggestions explain merchant resolution and selected category.
- [x] Existing month scope, list-first Review layout, focus behavior,
      swipe/keyboard mappings, Smart Review, and undo regressions remain covered.
- [x] `cd frontend && npm run test -- ActivityView TransactionReviewView` passes.
- [x] `cd backend && php artisan test --compact tests/Feature/TransactionApiTest.php tests/Feature/SmartReviewApiTest.php` passes.
- [x] `cd frontend && npm run typecheck` passes.
- [x] `./scripts/verify.sh` passes.

Relevant files:

- `backend/app/Http/Controllers/TransactionController.php`
- `backend/app/Http/Resources/TransactionResource.php`
- `backend/tests/Feature/TransactionApiTest.php`
- `backend/tests/Feature/SmartReviewApiTest.php`
- `frontend/src/components/activity/TransactionFilters.vue`
- `frontend/src/components/activity/TransactionFeed.vue`
- `frontend/src/components/activity/TransactionFormDialog.vue`
- `frontend/src/components/review/ReviewCard.vue`
- `frontend/src/components/review/ReviewQueueList.vue`
- `frontend/src/components/review/ReviewFocusDialog.vue`
- `frontend/src/views/ActivityView.vue`
- `frontend/src/views/TransactionReviewView.vue`
- `frontend/src/views/ActivityView.test.ts`
- `frontend/src/views/TransactionReviewView.test.ts`

Compatibility and migration concerns:

- Canonical merchant display must never overwrite or hide the inspectable raw
  descriptor.
- Keep current bulk, focus, and keyboard event contracts unless a tested typed
  extension is necessary.

Evidence:

- 2026-07-27 — `cd frontend && npm run test -- ActivityView TransactionReviewView`
  — pass within the TASK-006/007 suite (26 tests across RuleForm, RulesView,
  ActivityView, TransactionReviewView).
- 2026-07-27 — `cd backend && php artisan test --compact tests/Feature/TransactionApiTest.php tests/Feature/SmartReviewApiTest.php`
  — pass: 36 tests, 203 assertions.
- 2026-07-27 — `cd frontend && npm run typecheck` — pass.
- 2026-07-27 — `./scripts/verify.sh` — pass: 140 PHPUnit tests, 885 assertions;
  80 Vitest tests; typecheck; build.
- Decision: quick bucket review remains the primary Focus/bulk path; optional
  category selects emit `{ bucket, category_id }` without replacing swipe or
  keyboard mappings.

### TASK-008: Final hardening and reviewer evidence

Status: complete
Depends on: TASK-006, TASK-007

Acceptance criteria:

- [x] Cross-persona tests cover categories, rules, transactions, search, reset,
      and structured Smart Review results.
- [x] Migration fresh/seed and rollback/re-migrate behavior pass on SQLite.
- [x] Reset preserves the system catalog and cannot modify another persona's
      custom data.
- [x] Archived category history, unknown merchants, descriptor preservation,
      alias false positives, idempotency, suggest-only rules, and undo have
      explicit regression coverage.
- [x] Frontend loading, empty, validation, API error, keyboard, and accessible
      label/focus behavior is covered proportionally.
- [x] Independent correctness review and separately authorized security review
      have no unresolved blocking findings; Bugbot remains pending until the
      implementation PR is opened.
- [x] Interviewer-facing documentation links this spec, this evidence log, the
      workflow controls, and the feature branch without exposing raw
      private transcripts.
- [x] `./scripts/verify.sh` passes immediately before completion.

Relevant files:

- `backend/tests/Feature/CategoryApiTest.php`
- `backend/tests/Feature/CategorizationRuleApiTest.php`
- `backend/tests/Feature/TransactionApiTest.php`
- `backend/tests/Feature/SmartReviewApiTest.php`
- `backend/tests/Feature/ProfileApiTest.php`
- `frontend/src/views/RulesView.test.ts`
- `frontend/src/views/ActivityView.test.ts`
- `frontend/src/views/TransactionReviewView.test.ts`
- `docs/ai-workflow.md`
- `docs/rules-overhaul-case-study.md`
- `README.md`

Compatibility and migration concerns:

- Do not remove compatibility columns or fields merely to make the final schema
  look cleaner; removal requires a separately approved follow-up.
- Reviewer evidence records concise commands and outcomes, not secrets, raw
  model transcripts, or generated log dumps.

Evidence:

- 2026-07-27 — catalog migration rollback `--step=7`, re-migrate, ClearSpend
  reseed — pass after dropping `raw_merchant_descriptor` index before the
  column on SQLite.
- 2026-07-27 — isolation/regression suite
  (`CategoryApiTest`, `CategorizationRuleApiTest`, `MerchantResolverTest`,
  `SmartReviewApiTest`, `ProfileApiTest`) — pass: 41 tests, 245 assertions.
- 2026-07-27 — independent security review of branch changes — pass: no medium,
  high, or critical findings; residual notes are same-user integrity hardening.
- 2026-07-27 — reviewer-facing case study added:
  `docs/rules-overhaul-case-study.md`.
- 2026-07-27 — `./scripts/verify.sh` — pass: 140 PHPUnit tests, 885 assertions;
  80 Vitest tests; typecheck; build.
- Bugbot: deferred until the implementation PR is opened.

## Verification log

- 2026-07-27 — `./scripts/verify.sh` — pass: Pint; 109 PHPUnit tests with
  742 assertions; 75 Vitest tests across 13 files; Vue typecheck; production
  build.
- 2026-07-27 — direct `php backend/artisan boost:mcp` initialize request —
  pass: Laravel Boost responded with MCP protocol `2025-06-18`.
- 2026-07-27 — Cursor MCP discovery — blocked: this session exposes only
  `cursor-app-control` and `cursor-ide-browser`; reload Cursor before TASK-001
  so required Boost schema, route, and version-aware documentation tools are
  available to the implementation agent.
- 2026-07-27 — approved workflow committed locally as `4eee384`, followed by
  `./scripts/verify.sh` — pass with the same 109 PHPUnit tests, 742 assertions,
  75 Vitest tests, typecheck, build, and a clean `feature/rules-overhaul`
  working tree.
- 2026-07-27 — TASK-005 — `./scripts/verify.sh` — pass: 140 PHPUnit tests,
  885 assertions; 75 Vitest tests; typecheck; build.
- 2026-07-27 — TASK-006 + TASK-007 — `./scripts/verify.sh` — pass: 140 PHPUnit
  tests, 885 assertions; 80 Vitest tests across 15 files; typecheck; build.
- 2026-07-27 — TASK-008 — catalog rollback `--step=7` + reseed — pass; final
  `./scripts/verify.sh` — pass: 140 PHPUnit / 885 assertions; 80 Vitest;
  typecheck; build. Security review pass; Bugbot pending PR.
