# Hammer Media Interview Prep — Transaction Review

One focused feature: review and categorize financial transactions.

Stack:
- Frontend: Vue 3.5 + Vite + TypeScript
- Backend: Laravel 13 API + SQLite

## 1. Required software

- PHP 8.3+ (this machine has 8.5) with the SQLite extensions:
  - `pdo_sqlite`
  - `sqlite3`
- Composer 2
- Node.js 20+ and npm

On Arch Linux:

```bash
sudo pacman -S php php-sqlite composer nodejs npm
php -m | grep -i sqlite
```

You should see `pdo_sqlite` and `sqlite3`.

If the system SQLite extensions are not installed yet, this repo also includes a local helper that loads project-local modules for Artisan commands:

```bash
./scripts/php-with-sqlite.sh artisan migrate:fresh --seed
./scripts/php-with-sqlite.sh artisan test
./scripts/php-with-sqlite.sh artisan serve
```

Prefer installing the system `php-sqlite` package when you can.
## 2. Install frontend dependencies

```bash
cd frontend
npm install
```

## 3. Install backend dependencies

```bash
cd backend
composer install
```

## 4. Create or configure the SQLite database

Laravel is already configured for SQLite in `.env` (`DB_CONNECTION=sqlite`).

Create the empty database file if it does not exist:

```bash
cd backend
touch database/database.sqlite
```

If `.env` is missing:

```bash
cp .env.example .env
php artisan key:generate
```

## 5. Run migrations and seeders

```bash
cd backend
php artisan migrate:fresh --seed
```

This creates tables and seeds at least five realistic transactions.

## 6. Start Laravel

```bash
cd backend
php artisan serve
```

Laravel listens on `http://127.0.0.1:8000`.

Useful checks:

```bash
php artisan route:list
php artisan tinker
```

## 7. Start Vue

In a second terminal:

```bash
cd frontend
npm run dev
```

Vite proxies `/api` requests to `http://127.0.0.1:8000`.

## 8. Run Laravel tests

```bash
cd backend
php artisan test
```

The feature tests describe the finished API. They fail until the backend TODOs are completed.

## 9. Alejandro’s Backend Tasks

Complete these four learning items yourself:

1. `Transaction` model `$fillable` and casts
2. Validation rules in `UpdateTransactionRequest`
3. The query in `TransactionController@index`
4. The update logic in `TransactionController@update`

Do not look for finished solutions elsewhere in this repo — they are intentionally omitted.

## 10. Exact backend files containing TODOs

- `backend/app/Models/Transaction.php`
- `backend/app/Http/Requests/UpdateTransactionRequest.php`
- `backend/app/Http/Controllers/TransactionController.php`

## 11. Expected request flow (Vue → Laravel)

1. Vue loads and calls `GET /api/transactions`.
2. Vite forwards that request to Laravel at `http://127.0.0.1:8000/api/transactions`.
3. `TransactionController@index` should return unreviewed transactions as a resource collection:
   `{ "data": [ ... ] }`.
4. The UI shows one transaction at a time (merchant, amount, date, current category).
5. When you click Need / Want / Debt / Savings, Vue sends:

   `PATCH /api/transactions/{id}`

   ```json
   {
     "category": "need",
     "reviewed": true
   }
   ```

6. Laravel validates the body, updates the row, and returns:

   ```json
   {
     "data": {
       "id": 1,
       "merchant": "HEB",
       "amount": "84.23",
       "category": "need",
       "transaction_date": "2026-07-20",
       "reviewed": true
     }
   }
   ```

7. Vue advances to the next unreviewed transaction, or shows a completion state.

Allowed stored category values:

- `need`
- `want`
- `debt_savings`

## 12. Common debugging commands

```bash
# List registered routes (confirm /api/transactions exists)
cd backend && php artisan route:list

# Rebuild database and re-seed sample transactions
cd backend && php artisan migrate:fresh --seed

# Run the feature tests
cd backend && php artisan test

# Inspect data interactively
cd backend && php artisan tinker
```

In Tinker, useful checks after seeding:

```php
\App\Models\Transaction::count();
\App\Models\Transaction::all(['id', 'merchant', 'reviewed_at']);
```

Frontend type check / production build:

```bash
cd frontend
npm run build
```
