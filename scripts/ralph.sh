#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SPEC=""
PLAN=""
MODEL=""
MAX_ITERATIONS=3
MAX_SECONDS=3600
MAX_DIFF_LINES=800
CHECKPOINT_COMMITS=false
DRY_RUN=false

usage() {
    cat <<'EOF'
Usage:
  ./scripts/ralph.sh --spec PATH --plan PATH [options]

Required:
  --spec PATH                 Approved feature spec under docs/specs/
  --plan PATH                 Durable implementation plan under docs/specs/

Bounds:
  --max-iterations N          Fresh agent processes; default 3, maximum 3
  --max-seconds N             Total wall-clock budget; default 3600
  --max-diff-lines N          Stop when an iteration exceeds this; default 800
  --model MODEL               Optional Cursor model ID

Safety:
  --checkpoint-commits        Explicitly allow local checkpoint commits
  --dry-run                   Validate and print controls without starting Agent
  -h, --help                  Show this help

The controller never pushes, merges, or deploys. Each process handles one task.
EOF
}

fail() {
    echo "ralph: $*" >&2
    exit 1
}

require_value() {
    [[ $# -ge 2 && -n "$2" ]] || fail "$1 requires a value"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --spec)
            require_value "$@"
            SPEC="$2"
            shift 2
            ;;
        --plan)
            require_value "$@"
            PLAN="$2"
            shift 2
            ;;
        --model)
            require_value "$@"
            MODEL="$2"
            shift 2
            ;;
        --max-iterations)
            require_value "$@"
            MAX_ITERATIONS="$2"
            shift 2
            ;;
        --max-seconds)
            require_value "$@"
            MAX_SECONDS="$2"
            shift 2
            ;;
        --max-diff-lines)
            require_value "$@"
            MAX_DIFF_LINES="$2"
            shift 2
            ;;
        --checkpoint-commits)
            CHECKPOINT_COMMITS=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            fail "unknown option: $1"
            ;;
    esac
done

[[ -n "$SPEC" ]] || fail "--spec is required"
[[ -n "$PLAN" ]] || fail "--plan is required"
[[ "$MAX_ITERATIONS" =~ ^[1-3]$ ]] || fail "--max-iterations must be 1, 2, or 3"
[[ "$MAX_SECONDS" =~ ^[1-9][0-9]*$ ]] || fail "--max-seconds must be positive"
[[ "$MAX_DIFF_LINES" =~ ^[1-9][0-9]*$ ]] || fail "--max-diff-lines must be positive"

absolute_path() {
    local path="$1"

    if [[ "$path" = /* ]]; then
        realpath -m "$path"
    else
        realpath -m "$ROOT/$path"
    fi
}

SPEC="$(absolute_path "$SPEC")"
PLAN="$(absolute_path "$PLAN")"
SPECS_ROOT="$(realpath -m "$ROOT/docs/specs")"

[[ -f "$SPEC" ]] || fail "spec does not exist: $SPEC"
[[ -f "$PLAN" ]] || fail "plan does not exist: $PLAN"
[[ "$SPEC" == "$SPECS_ROOT/"* ]] || fail "spec must be under docs/specs/"
[[ "$PLAN" == "$SPECS_ROOT/"* ]] || fail "plan must be under docs/specs/"
rg -q '^Status: Approved$|^Status: Complete$' "$SPEC" ||
    fail "spec must have Status: Approved or Complete"

command -v git >/dev/null || fail "git is required"
command -v rg >/dev/null || fail "ripgrep is required"
command -v timeout >/dev/null || fail "GNU timeout is required"
command -v agent >/dev/null || fail "Cursor Agent CLI is required"
command -v composer >/dev/null || fail "Composer is required"
command -v npm >/dev/null || fail "npm is required"
[[ -x "$ROOT/scripts/verify.sh" ]] || fail "scripts/verify.sh must be executable"
[[ -f "$ROOT/.cursor/cli.json" ]] || fail ".cursor/cli.json is required"
[[ -f "$ROOT/.cursor/hooks.json" ]] || fail ".cursor/hooks.json is required"

PROMPT="$(cat <<EOF
Explicitly invoke the ralph-iteration project skill.

Approved feature spec: ${SPEC#"$ROOT/"}
Durable implementation plan: ${PLAN#"$ROOT/"}

Perform exactly one highest-priority ready task. Inspect existing code first.
Update task status and verification evidence in the plan. Do not use Git or gh;
the external controller owns diff inspection and any explicitly allowed
checkpoint commit. Never push, merge, deploy, install tools, access production,
or expand scope. Stop after this one task.
EOF
)"

if [[ "$DRY_RUN" == true ]]; then
    cat <<EOF
Ralph controller dry run
  spec: ${SPEC#"$ROOT/"}
  plan: ${PLAN#"$ROOT/"}
  max iterations: $MAX_ITERATIONS
  max seconds: $MAX_SECONDS
  max diff lines: $MAX_DIFF_LINES
  checkpoint commits: $CHECKPOINT_COMMITS
  model: ${MODEL:-account default}
  sandbox: enabled
  git/gh/deploy commands: denied by .cursor/cli.json and hooks
  one fresh Agent process per iteration: enabled

No agent process, worktree, commit, or external write was created.
EOF
    exit 0
fi

cd "$ROOT"

[[ -z "$(git status --porcelain)" ]] ||
    fail "baseline must be clean; review and commit or stash current changes first"
[[ "$(git branch --show-current)" != "main" && "$(git branch --show-current)" != "master" ]] ||
    echo "ralph: source branch is protected; isolated worktrees will use ralph/* branches"

echo "==> Verifying clean baseline"
"$ROOT/scripts/verify.sh"

agent status >/dev/null || fail "Agent CLI is not authenticated; run: agent login"

STARTED_AT="$(date +%s)"
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
LOG_DIR="$ROOT/.ralph/logs/$RUN_ID"
WORKTREE="$ROOT/.ralph/worktrees/$RUN_ID"
BRANCH="ralph/$RUN_ID"
mkdir -p "$LOG_DIR"

git worktree add -b "$BRANCH" "$WORKTREE" HEAD
[[ "$(git -C "$WORKTREE" branch --show-current)" == "$BRANCH" ]] ||
    fail "failed to create isolated branch $BRANCH"

echo "==> Preparing isolated worktree dependencies"
composer --working-dir="$WORKTREE/backend" install \
    --no-interaction --prefer-dist --no-progress
npm --prefix "$WORKTREE/frontend" ci

changed_line_count() {
    local worktree="$1"
    local tracked_lines
    local untracked_lines=0

    tracked_lines="$(
        git -C "$worktree" diff --numstat HEAD |
            awk '{ additions += $1; deletions += $2 } END { print additions + deletions + 0 }'
    )"

    while IFS= read -r path; do
        [[ -n "$path" ]] || continue
        untracked_lines=$((untracked_lines + $(wc -l <"$worktree/$path")))
    done < <(git -C "$worktree" ls-files --others --exclude-standard)

    echo $((tracked_lines + untracked_lines))
}

state_fingerprint() {
    local worktree="$1"

    {
        git -C "$worktree" status --porcelain
        git -C "$worktree" diff --no-ext-diff HEAD
        while IFS= read -r path; do
            [[ -n "$path" ]] || continue
            sha256sum "$worktree/$path"
        done < <(git -C "$worktree" ls-files --others --exclude-standard)
    } | sha256sum | awk '{ print $1 }'
}

for ((iteration = 1; iteration <= MAX_ITERATIONS; iteration++)); do
    elapsed=$(($(date +%s) - STARTED_AT))
    remaining=$((MAX_SECONDS - elapsed))
    ((remaining > 0)) || fail "total time budget exhausted"

    log_file="$LOG_DIR/iteration-$iteration.json"
    before_fingerprint="$(state_fingerprint "$WORKTREE")"

    echo "==> Iteration $iteration of $MAX_ITERATIONS"

    agent_command=(
        agent
        --print
        --trust
        --sandbox enabled
        --workspace "$WORKTREE"
        --output-format json
    )

    if [[ -n "$MODEL" ]]; then
        agent_command+=(--model "$MODEL")
    fi

    iteration_limit=$remaining
    if ((iteration_limit > 1200)); then
        iteration_limit=1200
    fi

    if ! timeout --signal=TERM "${iteration_limit}s" \
        "${agent_command[@]}" "$PROMPT" >"$log_file" 2>"$log_file.stderr"; then
        fail "iteration $iteration failed; inspect $log_file.stderr"
    fi

    after_fingerprint="$(state_fingerprint "$WORKTREE")"
    [[ "$after_fingerprint" != "$before_fingerprint" ]] ||
        fail "iteration $iteration made no detectable progress"

    diff_lines="$(changed_line_count "$WORKTREE")"
    ((diff_lines <= MAX_DIFF_LINES)) ||
        fail "iteration $iteration changed $diff_lines lines (limit: $MAX_DIFF_LINES)"

    echo "==> External verification for iteration $iteration"
    if ! (cd "$WORKTREE" && ./scripts/verify.sh); then
        fail "iteration $iteration failed external verification"
    fi

    {
        echo "branch: $BRANCH"
        echo "changed lines: $diff_lines"
        git -C "$WORKTREE" status --short
        git -C "$WORKTREE" diff --stat HEAD
    } >"$LOG_DIR/iteration-$iteration-summary.txt"

    if [[ "$CHECKPOINT_COMMITS" == true ]]; then
        git -C "$WORKTREE" add -A
        git -C "$WORKTREE" commit -m "chore(ralph): checkpoint iteration $iteration"
    fi

    if ! rg -q '^Status: ready$' "$WORKTREE/${PLAN#"$ROOT/"}"; then
        echo "==> No ready tasks remain"
        break
    fi

    if ((iteration == MAX_ITERATIONS)); then
        echo "==> Iteration limit reached with ready tasks remaining"
    fi
done

echo
echo "Ralph run stopped for human review."
echo "Logs: $LOG_DIR"
echo "Worktree: $WORKTREE"
echo "Branch: $BRANCH"
echo "No push, merge, or deployment was performed."
