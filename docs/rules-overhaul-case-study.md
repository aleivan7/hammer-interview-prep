# Rules overhaul case study

This document is the interviewer-facing summary of how ClearSpend's Rules
overhaul was delivered with a supervised AI engineering workflow.

## Product outcome

ClearSpend replaced free-text merchant and subcategory rules with:

- shared system categories under Needs, Wants, and Savings
- owner-scoped custom categories
- canonical merchants and deterministic aliases
- structured rules that target a merchant and category
- Activity/Review displays that preserve exact raw bank descriptors

Quick bucket review, Focus mode, Smart Review precedence, undo, demo-persona
isolation, integer cents, and Resource `{ "data": ... }` envelopes remain
intact.

## Workflow under the hood

```text
Specify → Ground → Plan → Execute → Prove → Review
```

| Layer | Role in this feature |
|---|---|
| Human | Approved the durable spec/plan, owned architecture, and remains the merge authority |
| Laravel Boost / grounding | Required for schema/routes/version-aware docs; when Cursor MCP Boost was unavailable, the agent recorded the gap and grounded via artisan schema/routes plus sibling Laravel patterns |
| Ralph-style iteration | One independently verifiable task at a time with durable plan status and evidence |
| Verification | Narrow PHPUnit/Vitest checks during iteration, then blocking `./scripts/verify.sh` |
| Independent review | Diff/security review before merge; Bugbot on the eventual PR |

Durable artifacts:

- [`docs/specs/rules-overhaul.md`](specs/rules-overhaul.md)
- [`docs/specs/rules-overhaul-plan.md`](specs/rules-overhaul-plan.md)
- [`docs/ai-workflow.md`](ai-workflow.md)

## Architecture decisions

1. Categories are children of one authoritative bucket.
2. Raw descriptors are preserved exactly; canonical merchant links are nullable.
3. Merchant matching is deterministic. Prefix matching requires a token boundary
   so `SHELL` cannot match `SHELLPOINT`.
4. Legacy API fields remain during migration so backend and frontend slices can
   ship independently.
5. Custom categories are archived, not deleted, so historical labels remain
   explainable.

## Verification evidence (final)

- 2026-07-27 — migration fresh/seed, rollback step 8, re-migrate, and reseed —
  pass on SQLite after fixing index drop order for `raw_merchant_descriptor`.
- 2026-07-27 — isolation/regression suite
  (`CategoryApiTest`, `CategorizationRuleApiTest`, `MerchantResolverTest`,
  `SmartReviewApiTest`, `ProfileApiTest`) — pass.
- 2026-07-27 — `./scripts/verify.sh` — pass: 140 PHPUnit tests, 885 assertions;
  80 Vitest tests; typecheck; production build.

## Agent safety boundaries used

- No push/merge/deploy without explicit human approval
- No new dependencies
- No hosted LLM categorization
- Demo `X-Demo-User` remains a persona selector, not production auth
- Acceptance criteria were not rewritten to hide failing work

## Reviewer links

- Repository: https://github.com/aleivan7/hammer-interview-prep
- Feature branch: `feature/rules-overhaul-catalog`
- Spec/plan: linked above
- Canonical PR: open from the feature branch after human review of the
  implementation diff

Hosting remains deferred until free-tier persistence and cold-start constraints
are evaluated separately.
