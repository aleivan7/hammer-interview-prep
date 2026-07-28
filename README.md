# ClearSpend — Dollarwise-inspired weekend POC

ClearSpend is a desktop-first personal finance POC inspired by Dollarwise-style
safe-to-spend forecasting and transaction review.

A demo persona-selection flow with isolated synthetic financial datasets lets
interviewers and testers choose between three seeded fictional users:

1. **Alex Rivera** — Reckless Spender
2. **Jordan Lee** — Average Spender
3. **Morgan Chen** — High-Net-Worth Individual

After selecting a persona, that user can:

- See an explainable **safe-to-spend** forecast with breakdown, 50/30/20 progress,
  cash flows, account sync health, and recent activity
- **Review** unreviewed transactions into Needs / Wants / Savings (drag, keyboard,
  buttons, undo)
- Run **Smart Review** — rules + local heuristics with an AI-ready categorizer
  seam (not a hosted LLM)
- Manage **Activity** in a scannable table (search/filters, create/edit)
- Maintain practical **categorization rules** for future Smart Review batches
- Open a **Profile** screen to switch personas or reset only that persona’s demo data

Stack:

- Frontend: Vue 3.5 + Vite + TypeScript + Vue Router
- Backend: Laravel 13 JSON API + SQLite

Money is stored as integer cents. Laravel is authoritative for financial totals;
Vue formats and presents only. There is **no production authentication** — the
app uses a lightweight demo header (`X-Demo-User`) and browser `localStorage`
selection. This is intentionally not secure auth.

## Routes (Vue)

| Path | Purpose |
|------|---------|
| `/login` | Demo persona selection (public) |
| `/` | Overview / safe-to-spend |
| `/activity` | Transaction table |
| `/review` | Categorization queue |
| `/rules` | Rules CRUD |
| `/profile` | Selected demo user profile, switch, reset |

Protected routes require a selected demo user id in `localStorage`
(`clearspend_demo_user_id`).

## API surface

Public:

- `GET /api/demo-users` — three persona cards (no financial detail beyond summary)

Protected (require `X-Demo-User: <user id>`):

- `GET /api/profile` — selected user identity and profile summary
- `POST /api/profile/reset` — restore only the selected user’s seeded financial data
- `GET /api/dashboard` — persona, safe-to-spend, plan, cash flows, accounts, recent txs
- `GET /api/accounts`
- `GET|POST /api/transactions`, `PATCH /api/transactions/{id}`
- `POST /api/transactions/{id}/undo`, `GET /api/transactions/{id}/suggestion`
- `POST /api/smart-review` — optional `{ "batch_key": "..." }` (retry-safe)
- `GET|POST /api/rules`, `PATCH|DELETE /api/rules/{id}`

Responses use Laravel Resource envelopes: `{ "data": ... }`.

Buckets: `need`, `want`, `savings` (legacy `debt_savings` / `category` aliases still accepted on patch).

The `X-Demo-User` header is a demo tenancy selector, not a security boundary.

## 1. Required software

- PHP 8.3+ with `pdo_sqlite` and `sqlite3`
- Composer 2
- Node.js 20+ and npm

On Arch Linux:

```bash
sudo pacman -S php php-sqlite composer nodejs npm
php -m | grep -i sqlite
```

Local helper if system SQLite modules are missing:

```bash
./scripts/php-with-sqlite.sh artisan migrate:fresh --seed
./scripts/php-with-sqlite.sh artisan serve
```

## 2. Install

```bash
cd frontend && npm install
cd ../backend && composer install
```

## 3. Database

```bash
cd backend
touch database/database.sqlite   # if needed
cp .env.example .env             # if needed
php artisan key:generate         # if needed
php artisan migrate:fresh --seed
```

Seeds three isolated demo personas with distinct plans, accounts, cash flows,
rules, and reviewed/unreviewed transactions.

## 4. Run locally

```bash
# terminal 1
cd backend && php artisan serve

# terminal 2
cd frontend && npm run dev
```

Vite proxies `/api` to `http://127.0.0.1:8000`. Open the Vite URL (usually `http://127.0.0.1:5173`).
Choose a demo profile on `/login`, then explore Overview / Activity / Review / Rules / Profile.

## 5. Quality gates

```bash
./scripts/verify.sh
```

Or individually:

```bash
cd backend && php artisan test && vendor/bin/pint --test
cd frontend && npm run typecheck && npm run test && npm run build
```

## 6. Smart Review (honest scope)

Smart Review auto-applies **high-confidence** matches from:

1. Enabled DB rules for the selected user (lower `priority` wins)
2. Local merchant/kind heuristics

Uncertain merchants stay in the review queue with explanations. The
`TransactionCategorizer` contract is the AI-ready seam for a future model —
this POC does **not** call a hosted LLM.

## 7. Specs and workflow

- Feature spec: [`docs/specs/clearspend.md`](docs/specs/clearspend.md)
- Implementation plan: [`docs/specs/clearspend-plan.md`](docs/specs/clearspend-plan.md)
- AI delivery workflow: [`docs/ai-workflow.md`](docs/ai-workflow.md)
- Rules overhaul spec: [`docs/specs/rules-overhaul.md`](docs/specs/rules-overhaul.md)
- Rules overhaul task plan and evidence:
  [`docs/specs/rules-overhaul-plan.md`](docs/specs/rules-overhaul-plan.md)
- Rules overhaul case study:
  [`docs/rules-overhaul-case-study.md`](docs/rules-overhaul-case-study.md)

Interactive development: commits, pushes, merges, and deploys require an
explicit human decision. Repository-approved Cursor Automations may push
branches and open PRs as scoped in [`AGENTS.md`](AGENTS.md); merge and deploy
still require explicit human approval.
