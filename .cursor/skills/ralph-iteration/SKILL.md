---
name: ralph-iteration
description: Runs one bounded implementation iteration from an approved project spec and task plan, records verification evidence, then stops for review. Use only when the user explicitly invokes the Ralph workflow.
disable-model-invocation: true
---

# Ralph Iteration

Complete exactly one ready task from an approved durable spec.

## Required inputs

- Feature spec under `docs/specs/`
- Implementation plan with task statuses and acceptance criteria
- A clean passing baseline, or a documented pre-existing failure

If an input is missing or the next task requires a product decision, stop and
ask the user. Do not invent requirements.

## One-iteration workflow

1. Read `AGENTS.md`, project rules, the approved spec, and its plan.
2. Inspect the current code and Git diff. Do not assume plan work is missing.
3. Select exactly one highest-priority task whose dependencies are complete.
4. Mark only that task in progress in its durable plan.
5. Implement the smallest change that satisfies that task's acceptance
   criteria. Do not perform adjacent cleanup.
6. Run the narrow relevant checks while iterating.
7. Run `./scripts/verify.sh` when the task affects executable behavior or the
   shared quality gate.
8. Update the task status and verification evidence with commands and outcomes.
9. Review the diff for scope, secrets, generated files, and unintended changes.
10. Stop and hand the diff to the user for review.

## Hard boundaries

- One task per invocation.
- Never push, merge, deploy, access production, or force a Git operation.
- Never create a commit unless the user explicitly allowed checkpoint commits
  for this run.
- Never install or authenticate tools without explicit approval.
- Never edit acceptance criteria merely to make failing work appear complete.
- Stop on repeated failure, no progress, unclear requirements, verification
  failure outside the task, or a safety-hook denial.
