<?php

namespace Database\Seeders;

use App\Enums\AccountSyncStatus;
use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\FinancialPlan;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class ClearSpendSeeder extends Seeder
{
    public function run(): void
    {
        FinancialPlan::query()->create([
            'needs_percent' => 50,
            'wants_percent' => 30,
            'savings_percent' => 20,
            'safety_buffer_cents' => 25_000,
            'monthly_income_cents' => 520_000,
        ]);

        $checking = Account::query()->create([
            'institution_name' => 'First Horizon',
            'name' => 'Everyday Checking',
            'mask' => '4821',
            'type' => 'checking',
            'balance_cents' => 184_350,
            'sync_status' => AccountSyncStatus::Healthy,
            'logo_key' => 'first-horizon',
            'sort_order' => 1,
        ]);

        $savings = Account::query()->create([
            'institution_name' => 'SoFi',
            'name' => 'Emergency Fund',
            'mask' => '9033',
            'type' => 'savings',
            'balance_cents' => 320_000,
            'sync_status' => AccountSyncStatus::Healthy,
            'logo_key' => 'sofi',
            'sort_order' => 2,
        ]);

        $credit = Account::query()->create([
            'institution_name' => 'Capital One',
            'name' => 'Rewards Card',
            'mask' => '7710',
            'type' => 'credit',
            'balance_cents' => -62_400,
            'sync_status' => AccountSyncStatus::Error,
            'logo_key' => 'capital-one',
            'sort_order' => 3,
        ]);

        PlannedCashFlow::query()->insert([
            [
                'name' => 'Acme Corp paycheck',
                'amount_cents' => 260_000,
                'kind' => 'income',
                'due_on' => '2026-07-31',
                'is_essential' => false,
                'bucket' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Rent',
                'amount_cents' => 165_000,
                'kind' => 'bill',
                'due_on' => '2026-07-28',
                'is_essential' => true,
                'bucket' => Bucket::Need->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Electric',
                'amount_cents' => 12_400,
                'kind' => 'bill',
                'due_on' => '2026-07-29',
                'is_essential' => true,
                'bucket' => Bucket::Need->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        CategorizationRule::query()->create([
            'name' => 'Netflix is entertainment',
            'merchant_contains' => 'netflix',
            'target_bucket' => Bucket::Want,
            'target_subcategory' => 'entertainment',
            'priority' => 10,
            'enabled' => true,
            'auto_review' => true,
        ]);

        CategorizationRule::query()->create([
            'name' => 'Exact rent amount is a need',
            'merchant_contains' => 'landlord',
            'amount_cents_min' => 165_000,
            'amount_cents_max' => 165_000,
            'target_bucket' => Bucket::Need,
            'target_subcategory' => 'housing',
            'priority' => 5,
            'enabled' => true,
            'auto_review' => true,
        ]);

        $reviewed = [
            [$checking->id, 'HEB', 8423, TransactionKind::Expense, Bucket::Need, 'groceries', '2026-07-03'],
            [$checking->id, 'Shell Gas', 4250, TransactionKind::Expense, Bucket::Need, 'transportation', '2026-07-05'],
            [$checking->id, 'Payroll Acme', 260000, TransactionKind::Income, Bucket::Savings, 'income', '2026-07-01'],
            [$checking->id, 'Spotify', 1099, TransactionKind::Expense, Bucket::Want, 'entertainment', '2026-07-06'],
            [$savings->id, 'To Emergency Fund', 5000, TransactionKind::Transfer, Bucket::Savings, 'transfer', '2026-07-07'],
        ];

        foreach ($reviewed as [$accountId, $merchant, $cents, $kind, $bucket, $sub, $date]) {
            Transaction::query()->create([
                'account_id' => $accountId,
                'merchant' => $merchant,
                'amount_cents' => $cents,
                'kind' => $kind,
                'bucket' => $bucket,
                'subcategory' => $sub,
                'transaction_date' => $date,
                'reviewed_at' => now()->subDays(2),
                'review_source' => ReviewSource::Manual,
                'confidence' => 100,
                'review_explanation' => 'Seeded reviewed transaction.',
            ]);
        }

        $unreviewed = [
            [$checking->id, 'Netflix', 1599, TransactionKind::Expense, '2026-07-10'],
            [$checking->id, 'Chipotle', 1345, TransactionKind::Expense, '2026-07-12'],
            [$credit->id, 'Target', 11216, TransactionKind::Expense, '2026-07-14'],
            [$checking->id, 'Capital One Payment', 20000, TransactionKind::Expense, '2026-07-15'],
            [$checking->id, 'Landlord LLC', 165000, TransactionKind::Expense, '2026-07-16'],
            [$checking->id, 'Unknown Vendor XYZ', 4200, TransactionKind::Expense, '2026-07-18'],
            [$checking->id, 'Amazon', 5899, TransactionKind::Expense, '2026-07-19'],
            [$checking->id, 'Rocket Mortgage', 312976, TransactionKind::Expense, '2026-07-20'],
            [$checking->id, 'Refund Apple', 2164, TransactionKind::Refund, '2026-07-21'],
            [$checking->id, 'Instill Coffee', 2300, TransactionKind::Expense, '2026-07-22'],
        ];

        foreach ($unreviewed as [$accountId, $merchant, $cents, $kind, $date]) {
            Transaction::query()->create([
                'account_id' => $accountId,
                'merchant' => $merchant,
                'amount_cents' => $cents,
                'kind' => $kind,
                'bucket' => null,
                'subcategory' => null,
                'transaction_date' => $date,
                'reviewed_at' => null,
            ]);
        }
    }
}
