# Merged change history

This file is the durable, version-controlled narrative for merged pull requests.
Git and GitHub remain the source of truth for exact diffs, reviews, and checks;
this history records what changed, why it changed, and the decision context that
would otherwise be lost in commit subjects.

## Recording policy

- Add one entry for every pull request before it merges, newest first.
- Copy or faithfully condense the rationale from the PR, linked spec, issue, or
  an explicit human decision. Never invent reasoning that was not recorded.
- Include meaningful tradeoffs and non-goals when they explain the chosen
  approach.
- Link architectural or product decisions to the relevant spec's `Decisions`
  section.
- Use the merge commit as the stable code reference after squash merge.

## Entry template

```markdown
## YYYY-MM-DD — [PR #N: title](https://github.com/OWNER/REPO/pull/N)

- **Merge:** `SHA`
- **Changed:** What became different.
- **Why:** The recorded user, product, or engineering rationale.
- **Decisions and tradeoffs:** Important choices, constraints, and non-goals.
- **Sources:** PR summary plus issue/spec/decision links.
```

## 2026-07-25 — [PR #17: Month-scoped Review with Focus mode](https://github.com/aleivan7/hammer-interview-prep/pull/17)

- **Merge:** `9fee9e6`
- **Changed:** Review now opens as a month-scoped, newest-first multi-select
  queue. One-at-a-time categorization moved into an accessible Focus dialog
  with consistent swipe, button, and keyboard transitions.
- **Why:** Make the default review experience easier to scan and organize by
  month while preserving a focused workflow for processing one transaction at
  a time.
- **Decisions and tradeoffs:** Multi-select replaced the old mode toggle as the
  default. Focus mode is entered from either the month toolbar or a row and
  returns to the same month on exit.
- **Sources:** PR summary and test plan; `docs/specs/clearspend.md`.

## 2026-07-25 — [PR #15: Cover demo persona isolation and ownership edges](https://github.com/aleivan7/hammer-interview-prep/pull/15)

- **Merge:** `5ec29f9`
- **Changed:** Added backend and frontend regression coverage for demo-user
  resolution, cross-user ownership, foreign account references, session
  clearing, profile caching, resets, and request races.
- **Why:** Persona selection acts as a demo tenancy boundary. Regressions could
  expose or mutate another persona's financial records or leave stale client
  state after an invalid selection.
- **Decisions and tradeoffs:** Kept the change test-focused; production behavior
  remained unchanged apart from a deterministic Vitest reset helper.
- **Sources:** PR summary and its “Why these tests materially reduce regression
  risk” section; PR #14.

## 2026-07-25 — [PR #14: Add demo persona selection with isolated datasets](https://github.com/aleivan7/hammer-interview-prep/pull/14)

- **Merge:** `631cba5`
- **Changed:** Added three selectable demo personas, `X-Demo-User` request
  context, per-persona datasets, ownership-scoped financial APIs, profile
  switching, and persona-specific reset behavior.
- **Why:** Let interviewers explore materially different financial situations
  without sharing records between personas.
- **Decisions and tradeoffs:** The selector is intentionally public demo context,
  not production authentication. `localStorage` holds the selection while the
  backend remains authoritative for ownership and financial calculations.
- **Sources:** PR summary; `docs/specs/clearspend.md`; security review recorded
  on the PR.

## 2026-07-25 — [PR #12: Align agent rules with Cursor Automations](https://github.com/aleivan7/hammer-interview-prep/pull/12)

- **Merge:** `b68f579`
- **Changed:** Authorized named test and documentation automations to create
  scoped branches, commits, pushes, and PRs, and synchronized repository rules
  and templates with that workflow.
- **Why:** Allow useful automation output to reach review without granting broad
  repository or production authority.
- **Decisions and tradeoffs:** Authorization is least-privilege. Merge, deploy,
  production access, force-push, secret commits, and hook bypass remain
  human-controlled; the daily summary automation stays read-only.
- **Sources:** PR summary, scope, and security review; `AGENTS.md`;
  `docs/ai-workflow.md`.

## 2026-07-25 — [PR #11: Polish ClearSpend UI to a production dark finance aesthetic](https://github.com/aleivan7/hammer-interview-prep/pull/11)

- **Merge:** `159b1aa`
- **Changed:** Rebuilt the dark visual system, application shell, shared UI
  primitives, and the Overview, Activity, Review, and Rules layouts.
- **Why:** Present the interview POC as a coherent, production-quality finance
  experience rather than a minimally styled prototype.
- **Decisions and tradeoffs:** Retained existing application behavior and test
  coverage while adopting a near-black and emerald visual direction. No new UI
  framework was introduced.
- **Sources:** PR summary and test plan; `docs/specs/clearspend.md`.

## 2026-07-25 — [PR #10: Add readable descriptions to backend and frontend tests](https://github.com/aleivan7/hammer-interview-prep/pull/10)

- **Merge:** `ffd3ce1`
- **Changed:** Added PHPUnit TestDox labels, backend test-class summaries, and
  short overview comments for frontend Vitest suites.
- **Why:** Make the test suite communicate its behavior and intent to reviewers
  and interviewers without changing runtime behavior.
- **Decisions and tradeoffs:** Documentation and comments only; no production
  behavior changed.
- **Sources:** PR summary and scope.

## 2026-07-25 — [PR #9: Implement the ClearSpend financial planning POC](https://github.com/aleivan7/hammer-interview-prep/pull/9)

- **Merge:** `d436c33`
- **Changed:** Added the Overview, Activity, Review, and Rules application;
  Laravel APIs and domain services; cent-accurate safe-to-spend calculations;
  Smart Review; synthetic data; tests; and durable product specs.
- **Why:** Deliver the approved interview POC as an explainable, testable
  financial-planning workflow with server-authoritative money calculations.
- **Decisions and tradeoffs:** Integer cents and server-side totals are
  invariants. Production authentication, real bank integrations, hosted AI,
  and deployment were intentionally excluded. Several known POC issues were
  explicitly dispositioned as follow-ups in the PR.
- **Sources:** PR summary, non-goals, invariants, and review dispositions;
  `docs/specs/clearspend.md`; `docs/specs/clearspend-plan.md`.

## 2026-07-25 — [PR #1: Establish the AI delivery foundation and GitHub workflow](https://github.com/aleivan7/hammer-interview-prep/pull/1)

- **Merge:** `49e0cc4`
- **Changed:** Established the verified interview baseline, transaction-review
  coverage, full verification script, Cursor/Ralph delivery tooling, durable
  spec templates, GitHub Flow, CI, CodeQL, dependency review, and repository
  safety documentation.
- **Why:** Create a repeatable, reviewable delivery foundation before expanding
  the ClearSpend product.
- **Decisions and tradeoffs:** `main` is the only long-lived branch; work lands
  through short-lived PRs and squash merges. Product UI, external integrations,
  authentication, hosted AI, and deployment were deferred.
- **Sources:** PR summary, scope, and post-merge actions; `CONTRIBUTING.md`;
  `docs/ai-workflow.md`.
