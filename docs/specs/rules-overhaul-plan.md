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

Status: ready
Depends on: none

Acceptance criteria:

- [ ] System categories model the current Needs, Wants, and Savings vocabulary
      with stable normalized names and sort order.
- [ ] Canonical merchants and enabled/disabled aliases support explicit match
      strategies and priority without duplicate normalized records.
- [ ] Models, relationships, factories, and idempotent seed data exist for
      categories, merchants, and aliases.
- [ ] `GET /api/categories` returns shared active system categories plus only
      the selected persona's active custom categories, grouped or sortable by
      bucket, in a Resource `data` envelope.
- [ ] `GET /api/merchants` supports practical search and returns canonical
      merchants plus safe representative descriptors in a Resource envelope.
- [ ] System catalog APIs do not expose another persona's custom categories.
- [ ] Fresh migration and seeding succeed on SQLite.
- [ ] `cd backend && php artisan test --compact tests/Feature/CategoryApiTest.php tests/Feature/MerchantApiTest.php` passes.
- [ ] `./scripts/verify.sh` passes.

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

- Not started.

### TASK-002: Deterministic merchant resolution

Status: blocked
Depends on: TASK-001

Acceptance criteria:

- [ ] A testable normalizer handles case, punctuation, separators, and repeated
      whitespace while retaining a useful normalized descriptor.
- [ ] The resolver supports exact, prefix, whole-token, and explicitly safe
      contains strategies, alias priority, and disabled aliases.
- [ ] A successful result contains the canonical merchant, matched alias,
      strategy, normalized descriptor, and a human-readable explanation.
- [ ] Unknown descriptors return no merchant and do not create catalog records.
- [ ] Netflix and Spotify examples resolve correctly.
- [ ] `Shellpoint Mortgage` does not resolve to Shell without an intentional
      explicit alias.
- [ ] `cd backend && php artisan test --compact tests/Unit/MerchantResolverTest.php` passes.
- [ ] `./scripts/verify.sh` passes.

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

- Blocked by TASK-001.

### TASK-003: Transaction integration

Status: blocked
Depends on: TASK-001, TASK-002

Acceptance criteria:

- [ ] Transactions preserve an exact raw descriptor and may reference nullable
      canonical merchant and category records.
- [ ] Existing transaction rows are backfilled without losing merchant,
      bucket, subcategory, amount, review, or audit behavior.
- [ ] Known descriptors link deterministically; unknown descriptors remain
      usable without creating merchants.
- [ ] Detailed category assignment derives its bucket and rejects conflicts;
      quick bucket-only review remains valid.
- [ ] Transaction Resources expose structured fields while preserving legacy
      merchant, bucket, subcategory, and category compatibility fields.
- [ ] Frontend transaction types and API payloads represent the expanded
      contract explicitly.
- [ ] Undo restores review state without losing descriptor or merchant identity.
- [ ] `cd backend && php artisan test --compact tests/Feature/TransactionApiTest.php tests/Unit/TransactionReviewServiceTest.php` passes.
- [ ] `cd frontend && npm run test -- src/utils/transactions.test.ts` passes.
- [ ] `./scripts/verify.sh` passes.

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

- Blocked by TASK-001 and TASK-002.

### TASK-004: Custom categories

Status: blocked
Depends on: TASK-001

Acceptance criteria:

- [ ] A selected persona can create, rename, and archive only their own custom
      categories under one valid bucket.
- [ ] System categories cannot be modified or archived through custom-category
      endpoints.
- [ ] Visible same-bucket names are unique after case and whitespace
      normalization.
- [ ] Archived categories remain serialized on historical transactions but are
      excluded from new assignments and rules.
- [ ] Another persona cannot view through list APIs, mutate, or assign the
      custom category.
- [ ] Persona reset recreates that persona's intended demo state without
      changing shared system categories or another persona's categories.
- [ ] `cd backend && php artisan test --compact tests/Feature/CategoryApiTest.php tests/Feature/ProfileApiTest.php` passes.
- [ ] `./scripts/verify.sh` passes.

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

- Blocked by TASK-001.

### TASK-005: Structured rules backend

Status: blocked
Depends on: TASK-002, TASK-003, TASK-004

Acceptance criteria:

- [ ] Rules reference a canonical merchant and target category while retaining
      optional account, amount range, priority, enabled, and auto-review fields.
- [ ] The target bucket is derived from the category; conflicting inputs cannot
      be stored.
- [ ] Existing seeded rules are deterministically backfilled or reseeded
      without losing their conditions or intended behavior.
- [ ] A persona cannot reference another persona's custom category, account, or
      rule.
- [ ] Merchant aliases can trigger canonical-merchant rules.
- [ ] Enabled rules continue to execute before heuristics with stable priority
      ordering.
- [ ] Suggest-only rules, user-scoped batch idempotency, undo, and Smart Review
      explanations remain intact.
- [ ] Resources preserve required legacy fields during the frontend migration.
- [ ] `cd backend && php artisan test --compact tests/Feature/CategorizationRuleApiTest.php tests/Unit/RulesAndHeuristicsCategorizerTest.php tests/Feature/SmartReviewApiTest.php` passes.
- [ ] `./scripts/verify.sh` passes.

Relevant files:

- `backend/database/migrations/*_add_catalog_fields_to_categorization_rules_table.php`
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

- Blocked by TASK-002, TASK-003, and TASK-004.

### TASK-006: Structured Rules interface

Status: blocked
Depends on: TASK-001, TASK-004, TASK-005

Acceptance criteria:

- [ ] Typed category and merchant API clients preserve Resource envelopes and
      expose useful loading, empty, and failure states.
- [ ] The rule form uses a searchable canonical merchant selector and a grouped
      active category selector.
- [ ] Inline custom-category creation preserves entered rule name, merchant,
      advanced conditions, and toggles, then selects the new category.
- [ ] A plain-language preview names the canonical merchant, representative raw
      descriptor, category, and bucket.
- [ ] Account, minimum/maximum amount, priority, enabled, and auto-review remain
      available in an advanced section.
- [ ] Rules list/edit/delete behavior uses the structured API contract.
- [ ] Components have explicit typed props/emits and `RulesView.vue` remains a
      composition surface.
- [ ] `cd frontend && npm run test -- RuleForm RulesView` passes.
- [ ] `cd frontend && npm run typecheck` passes.
- [ ] `./scripts/verify.sh` passes.

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

- Blocked by TASK-001, TASK-004, and TASK-005.

### TASK-007: Activity and Review integration

Status: blocked
Depends on: TASK-003, TASK-004, TASK-005

Acceptance criteria:

- [ ] Activity and Review display canonical merchant names when available and
      retain access to the exact raw descriptor.
- [ ] Activity create/edit supports active detailed category assignment and
      bucket-only compatibility.
- [ ] Search matches canonical merchant, raw descriptor, and category where the
      API can do so without broad unscoped queries.
- [ ] Review list, Focus mode, and bulk actions support category assignment
      without removing quick Needs/Wants/Savings review.
- [ ] Suggestions explain merchant resolution and selected category.
- [ ] Existing month scope, list-first Review layout, focus behavior,
      swipe/keyboard mappings, Smart Review, and undo regressions remain covered.
- [ ] `cd frontend && npm run test -- ActivityView TransactionReviewView` passes.
- [ ] `cd backend && php artisan test --compact tests/Feature/TransactionApiTest.php tests/Feature/SmartReviewApiTest.php` passes.
- [ ] `cd frontend && npm run typecheck` passes.
- [ ] `./scripts/verify.sh` passes.

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

- Blocked by TASK-003, TASK-004, and TASK-005.

### TASK-008: Final hardening and reviewer evidence

Status: blocked
Depends on: TASK-006, TASK-007

Acceptance criteria:

- [ ] Cross-persona tests cover categories, rules, transactions, search, reset,
      and structured Smart Review results.
- [ ] Migration fresh/seed and rollback/re-migrate behavior pass on SQLite.
- [ ] Reset preserves the system catalog and cannot modify another persona's
      custom data.
- [ ] Archived category history, unknown merchants, descriptor preservation,
      alias false positives, idempotency, suggest-only rules, and undo have
      explicit regression coverage.
- [ ] Frontend loading, empty, validation, API error, keyboard, and accessible
      label/focus behavior is covered proportionally.
- [ ] Independent correctness review and separately authorized security/Bugbot
      reviews have no unresolved blocking findings.
- [ ] Interviewer-facing documentation links this spec, this evidence log, the
      workflow controls, and the canonical pull request without exposing raw
      private transcripts.
- [ ] `./scripts/verify.sh` passes immediately before completion.

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

- Blocked by TASK-006 and TASK-007.

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
