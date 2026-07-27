# Feature: Rules overhaul

Status: Approved
Owner: Alejandro
Last updated: 2026-07-27

## Goal

Replace ClearSpend's free-text merchant and subcategory rules with a structured,
explainable categorization system built on canonical merchants, deterministic
merchant aliases, and system or user-owned categories, while preserving the
current demo-persona isolation, Smart Review, Activity, Review, and undo
behavior.

## Behavior

### Category and merchant catalogs

- Given any selected demo persona, when categories are requested, then shared
  system categories are grouped under Needs, Wants, or Savings and only that
  persona's active custom categories are assignable.
- Given a selected demo persona, when they create, rename, or archive a custom
  category, then only that persona can see or modify it.
- Given a system category, when a persona attempts to rename or archive it,
  then the API rejects the request.
- Given a custom category name that differs from an existing visible category
  only by case or surrounding whitespace, when it is submitted in the same
  bucket, then the API rejects it as a duplicate.
- Given an archived custom category referenced by historical transactions,
  when history is viewed, then its name and bucket remain visible, but it
  cannot be assigned to a new transaction or rule.
- Given the merchant catalog, when merchants are requested, then canonical
  merchant records and representative raw descriptor examples are returned in
  a Laravel Resource `data` envelope.

### Deterministic merchant resolution

- Given a raw bank descriptor, when it matches an enabled alias by exact,
  prefix, whole-token, or explicitly safe contains strategy, then ClearSpend
  resolves the canonical merchant using strategy specificity and configured
  priority.
- Given `NETFLIX-NY`, `NETFLIX.COM 408724`, `spotify-94820349857`, or
  `SPOTIFY USA`, when resolution runs, then the result is the expected Netflix
  or Spotify canonical merchant with an explanation of the alias and strategy.
- Given `Shellpoint Mortgage`, when only a normal Shell alias exists, then it
  does not resolve to Shell unless an explicit alias intentionally permits it.
- Given no safe alias match, when resolution runs, then the original raw
  descriptor remains usable and no canonical merchant record is created
  automatically.

### Transactions

- Given an existing or newly imported transaction, when it is stored, then its
  original raw merchant descriptor is preserved without normalization or loss.
- Given a resolvable raw descriptor, when transaction integration runs, then
  the transaction may also reference the canonical merchant.
- Given a category assignment, when the transaction is reviewed, then its
  Needs, Wants, or Savings bucket is derived from the category and cannot
  conflict with it.
- Given a quick bucket-only review, when no detailed category is selected, then
  the existing bucket review remains valid.
- Given a legacy client, when transaction resources are returned during the
  overhaul, then the existing merchant, bucket, subcategory, and category
  compatibility fields remain available while structured fields are added.
- Given an undo action, when a reviewed transaction is reverted, then the prior
  review state is restored without losing its raw descriptor or canonical
  merchant link.

### Structured categorization rules

- Given a selected demo persona, when they create or update a rule, then the
  rule selects a canonical merchant and target category rather than storing
  new free-text merchant or category values.
- Given a target category, when a rule is saved, then its target bucket is
  derived from the category and a conflicting bucket cannot be stored.
- Given another persona's custom category or rule, when it is referenced,
  viewed, changed, or deleted, then the API rejects or hides it according to
  the existing user-scoping convention.
- Given enabled rules with account, amount, priority, and auto-review
  conditions, when Smart Review runs, then those conditions and lower-number
  priority ordering continue to apply before heuristics.
- Given a raw descriptor that resolves through a merchant alias, when a rule
  targets that canonical merchant, then the rule can match it.
- Given an auto-review-disabled rule, when a suggestion is requested, then the
  rule may explain a suggestion but Smart Review does not automatically apply
  it.
- Given a repeated user-scoped Smart Review batch key, when the request is
  retried, then the existing idempotent result behavior is preserved.

### Rules interface

- Given the Rules form, when a user searches for a merchant, then they can
  choose a canonical merchant from a searchable selector.
- Given the category selector, when it opens, then active system and user
  categories are grouped by Needs, Wants, and Savings.
- Given no suitable category, when a user creates one inline, then the partially
  completed rule remains intact and the new category becomes selected.
- Given merchant and category selections, when the form changes, then a plain
  language preview explains representative descriptors and the target category
  and bucket.
- Given advanced conditions, when the user expands the advanced section, then
  account, minimum amount, maximum amount, priority, enabled, and auto-review
  controls remain available.

### Activity and Review integration

- Given a resolved transaction, when it appears in Activity or Review, then the
  canonical merchant is primary and the original raw descriptor remains
  available for inspection.
- Given Activity create or edit, when a category is selected, then the
  transaction receives that category and its derived bucket.
- Given Activity search, when a user searches by canonical merchant, raw
  descriptor, or category where practical, then matching user-scoped
  transactions are returned.
- Given Review list, Focus mode, or a bulk action, when categorization occurs,
  then the user can keep a quick bucket assignment or choose a detailed active
  category.
- Given the existing Review route, when the overhaul ships, then it remains
  list-first and month-scoped with Focus mode, swipe and keyboard mapping, Smart
  Review explanations, bulk behavior, and undo.

### Reset and migration

- Given a persona reset, when financial data is reseeded, then that persona's
  custom categories and structured rules reset without changing another
  persona or deleting the shared system catalog.
- Given existing demo records, when migrations and seeding run, then legacy
  raw descriptors, reviewed buckets, known subcategories, and rule conditions
  are preserved or deterministically mapped.
- Given a migration rollback, when the new migrations are reversed, then they
  remove their own schema safely under SQLite without corrupting the existing
  base tables.

## Constraints

- Keep the API public and stateless with `X-Demo-User`; this remains a demo
  persona selector, not production authentication.
- Scope every financial and custom-category query to the resolved demo user.
  Route-model binding alone is not ownership protection.
- Store money as integer cents and keep Laravel authoritative for financial
  calculations.
- Preserve Laravel Resource `{ "data": ... }` envelopes and explicit frontend
  request/response types.
- Preserve raw merchant descriptors exactly; canonical merchant relationships
  are nullable.
- Use deterministic local matching only. Do not add a hosted model or silently
  create canonical merchants from unknown descriptors.
- Remain compatible with SQLite for local development and tests.
- Use conventional Laravel models, migrations, factories, Form Requests,
  Resources, controllers, services, and feature tests.
- Use Vue 3 Composition API with `<script setup lang="ts">`, native `fetch`, and
  small typed components. Do not add Pinia, Axios, or a UI framework.
- Do not add dependencies without explicit approval.
- Deliver one independently verifiable task per supervised Ralph iteration.
- `./scripts/verify.sh` is a blocking completion gate.

## Non-goals

- Production authentication or authorization.
- Hosted AI categorization or probabilistic merchant matching.
- Automatic ingestion or learning of new canonical merchants.
- Real bank synchronization or Plaid integration.
- Nested boolean rule groups, regex entry, or an unrestricted expert rule DSL.
- Replacing the existing Review page with a card-only or unscoped workflow.
- Deleting legacy compatibility fields before all in-repository clients migrate.
- Deployment or hosting as part of this feature.

## Acceptance criteria

- [ ] Shared system categories, canonical merchants, and deterministic aliases
      are seeded and exposed through user-safe APIs.
- [ ] Custom categories support owner-scoped create, rename, and archive with
      duplicate, system-protection, historical-reference, and reset behavior.
- [ ] Merchant resolution passes all required positive examples and the
      Shellpoint/Shell false-positive regression.
- [ ] Transactions preserve raw descriptors, optionally link structured
      merchants/categories, derive bucket consistently, and retain legacy API
      compatibility and undo behavior.
- [ ] Rules reference canonical merchants and categories while preserving
      account/amount/priority/enabled/auto-review behavior, user scoping,
      precedence, alias matching, and batch idempotency.
- [ ] Rules UI provides searchable/grouped selectors, inline category creation,
      form-state preservation, preview, advanced controls, and frontend tests.
- [ ] Activity and Review expose canonical and raw merchant context plus
      detailed category assignment without regressing month scope, Focus mode,
      bulk review, swipe/keyboard mapping, suggestions, or undo.
- [ ] Persona reset preserves the system catalog and cannot affect another
      persona's custom data.
- [ ] New migrations migrate and roll back successfully on SQLite.
- [ ] Independent correctness and security reviews have no unresolved blocking
      findings.
- [ ] `./scripts/verify.sh` passes.

## Decisions

- 2026-07-27: Keep the current Needs, Wants, and Savings buckets authoritative;
  categories are structured children of one bucket.
- 2026-07-27: Seed the system category catalog from the vocabulary already used
  by current persona data, adding only categories required by accepted examples.
- 2026-07-27: Preserve both quick bucket-only review and optional detailed
  category assignment.
- 2026-07-27: Preserve raw descriptors exactly and make canonical merchant and
  category links nullable for unknown or partially reviewed transactions.
- 2026-07-27: Merchant resolution is deterministic and explanation-bearing;
  unsafe generic substring matching is not a default strategy.
- 2026-07-27: Keep compatibility fields throughout this feature so backend and
  frontend slices can ship independently without breaking existing behavior.
- 2026-07-27: Archive custom categories instead of deleting them so historical
  transaction labels remain explainable.
- 2026-07-27: Hosting is deferred until the completed application can be
  evaluated against current free-tier persistence and cold-start constraints.

## Open questions

- None.
