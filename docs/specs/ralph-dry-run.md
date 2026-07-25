# Feature: Ralph documentation dry run

Status: Approved
Owner: Alejandro
Last updated: 2026-07-24

## Goal

Prove that a supervised Ralph iteration can select one documentation task,
record evidence, and stop without touching application behavior.

## Behavior

- Given the approved plan, one iteration creates only the requested result
  section and records its evidence.
- A later iteration selects the next ready task rather than expanding the first.

## Constraints

- Documentation changes only.
- No commit, push, merge, deployment, dependency change, or application edit.

## Non-goals

- Running an unattended coding loop.
- Changing the transaction review feature.

## Acceptance criteria

- [x] Two one-task supervised iterations are represented in the result.
- [x] Each task has explicit status and evidence.
- [x] The application verification gate remains unchanged.

## Decisions

- 2026-07-24: Keep this record as a harmless workflow example.

## Open questions

- None.
