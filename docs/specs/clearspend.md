# Feature: ClearSpend weekend POC

Status: Approved
Owner: Alejandro
Last updated: 2026-07-25

## Goal

Deliver a desktop-first, Dollarwise-inspired ClearSpend web POC where a tester
chooses one of three seeded synthetic personas, then sees an explainable
safe-to-spend forecast, reviews transactions into Needs / Wants / Savings with
multi-input gestures, applies Smart Review automation with custom rules, and
manages activity in a scannable table—all with cent-accurate,
server-authoritative financial math and complete data isolation between personas.

## Behavior

- Given no selected demo user, when the app opens, then the visitor sees a
  persona-selection screen for three fictional users and must choose one before
  entering the app.
- Given a selected demo user, when API requests are made, then the frontend sends
  `X-Demo-User` and every financial response is scoped to that user only.
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
- Given the Profile screen, when the user switches personas or resets demo data,
  then selection/clearing and per-user reseed work without affecting other
  personas.

## Constraints

- Public, stateless API with a lightweight demo-user header; no production auth
  packages, passwords, cookies, JWT, OAuth, or Plaid in this POC.
- Persist money as integer cents; serialize display amounts as two-decimal strings.
- Laravel is authoritative for dashboard totals; Vue formats/presents only.
- Vue Router allowed for login/profile plus the four app routes; no Pinia,
  Axios, or UI framework.
- Smart Review is rules + local heuristics with an AI-ready seam; never labeled
  as a hosted LLM.
- Synthetic demo data only; no personal screenshot data.
- `./scripts/verify.sh` remains the quality gate.

## Non-goals

- Production authentication/authorization, real bank sync, hosted AI,
  subscriptions, production deploy, multi-currency, nested AND/OR rule builders,
  full account management route.

## Acceptance criteria

- [x] Demo persona selection, profile, switch, and per-user reset work with
      isolated synthetic datasets.
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
- 2026-07-25: Feature 1 — three isolated demo personas via `X-Demo-User`
  (explicitly not production authentication).
- 2026-07-25: Hybrid local categorizer (rules + heuristics), AI-ready interface.
- 2026-07-25: Trunk-based GitHub Flow; foundation already merged via PR #1.
- 2026-07-25: Keep integer-cent financial math and server-authoritative totals
  while excluding production auth, bank integrations, hosted AI, and deployment
  from the interview POC (PR #9).
- 2026-07-25: Use a near-black and emerald visual system without adding a UI
  framework or changing established application behavior (PR #11).
- 2026-07-25: Treat `X-Demo-User` as a public demo selector, not authentication,
  while enforcing backend ownership scopes between synthetic personas (PR #14).
- 2026-07-25: Make month-scoped multi-select the default Review experience and
  move one-at-a-time processing into an accessible Focus dialog (PR #17).

## Open questions

- None for weekend POC scope.
