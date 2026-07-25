# Feature: ClearSpend weekend POC

Status: Approved
Owner: Alejandro
Last updated: 2026-07-25

## Goal

Deliver a desktop-first, Dollarwise-inspired ClearSpend web POC where a synthetic
user can see an explainable safe-to-spend forecast, review transactions into
Needs / Wants / Savings with multi-input gestures, apply Smart Review automation
with custom rules, and manage activity in a scannable table—all with
cent-accurate, server-authoritative financial math.

## Behavior

- Given a seeded synthetic persona with accounts, plan, cash flows, rules, and
  mixed transactions, when the app loads Overview, then the user sees
  safe-to-spend with a breakdown, 50/30/20 progress, upcoming cash flow, recent
  activity, and account sync health.
- Given unreviewed transactions, when the user opens Review, then they can
  categorize with drag (left=Wants, right=Needs, down=Savings), keyboard
  shortcuts, and buttons; undo reverses the last review.
- Given high-confidence rule/heuristic matches, when Smart Review runs, then
  those transactions are auto-reviewed with explanations and uncertain ones
  remain in the queue.
- Given Activity filters/search, when the user edits or creates a manual
  transaction, then the table and dashboard totals reconcile from the API.
- Given a practical rule (merchant text + optional account/amount conditions),
  when saved and enabled with auto-review, then matching future Smart Review
  runs apply it first.
- Given transfers or refunds, when spending and safe-to-spend are computed, then
  transfers are excluded from spending and refunds reverse the correct bucket
  without floating-point math.

## Constraints

- Public, stateless API; no authentication or Plaid in this POC.
- Persist money as integer cents; serialize display amounts as two-decimal strings.
- Laravel is authoritative for dashboard totals; Vue formats/presents only.
- Vue Router allowed for four routes; no Pinia, Axios, or UI framework.
- Smart Review is rules + local heuristics with an AI-ready seam; never labeled
  as a hosted LLM.
- Synthetic demo data only; no personal screenshot data.
- `./scripts/verify.sh` remains the quality gate.

## Non-goals

- Authentication, multi-user authz, real bank sync, hosted AI, subscriptions,
  production deploy, multi-currency, nested AND/OR rule builders, full account
  management route.

## Acceptance criteria

- [x] Overview, Activity, Review, and Rules routes work in a desktop sidebar shell
      that remains usable on tablet/mobile.
- [x] Adjustable 50/30/20 plan drives explainable safe-to-spend and insights.
- [x] Review supports drag, keyboard, buttons, undo, and left/right/down mapping.
- [x] Smart Review auto-applies high-confidence matches, explains results, and
      leaves uncertain items for the user.
- [x] Activity is a desktop table with search/filters and create/edit dialogs.
- [x] Rules CRUD covers merchant, optional account/amount, bucket/subcategory,
      priority, enabled, and auto-review.
- [x] Financial invariant tests cover cents, transfers, refunds, retry-safe batch
      review, rollback, and reconciliation.
- [x] README presents ClearSpend honestly as a Dollarwise-inspired concept.
- [x] `./scripts/verify.sh` passes.

## Decisions

- 2026-07-25: Brand as ClearSpend (original concept name).
- 2026-07-25: Buckets are Needs / Wants / Savings; debt is a Savings subcategory.
- 2026-07-25: Seeded demo data with future Plaid/auth seams.
- 2026-07-25: Hybrid local categorizer (rules + heuristics), AI-ready interface.
- 2026-07-25: Trunk-based GitHub Flow; foundation already merged via PR #1.

## Open questions

- None for weekend POC scope.
