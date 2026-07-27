<?php

namespace App\Services;

use App\Enums\AccountSyncStatus;
use App\Enums\Bucket;
use App\Enums\PersonaType;
use App\Enums\ReviewSource;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\CategorizationRule;
use App\Models\Category;
use App\Models\FinancialPlan;
use App\Models\Merchant;
use App\Models\PlannedCashFlow;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CatalogNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DemoPersonaDataService
{
    /**
     * @return list<User>
     */
    public function seedAllPersonas(?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::parse('2026-07-25')->startOfDay();

        return [
            $this->seedPersona(PersonaType::Reckless, $asOf),
            $this->seedPersona(PersonaType::Average, $asOf),
            $this->seedPersona(PersonaType::HighNetWorth, $asOf),
        ];
    }

    public function seedPersona(PersonaType $type, ?Carbon $asOf = null): User
    {
        $asOf ??= Carbon::parse('2026-07-25')->startOfDay();
        $definition = $this->definitionFor($type);

        return DB::transaction(function () use ($definition, $asOf) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => null,
                    'persona_type' => $definition['persona_type'],
                    'persona_label' => $definition['persona_label'],
                    'description' => $definition['description'],
                    'member_since' => $definition['member_since'],
                    'avatar_initials' => $definition['avatar_initials'],
                ],
            );

            $this->resetFinancialData($user, $asOf);

            return $user->fresh([
                'financialPlan',
                'accounts',
                'plannedCashFlows',
                'transactions',
                'categorizationRules',
            ]) ?? $user;
        });
    }

    public function resetFinancialData(User $user, ?Carbon $asOf = null): User
    {
        $asOf ??= Carbon::parse('2026-07-25')->startOfDay();
        $type = $user->persona_type ?? PersonaType::Average;
        $definition = $this->definitionFor($type);

        return DB::transaction(function () use ($user, $definition, $asOf) {
            Transaction::query()->where('user_id', $user->id)->delete();
            CategorizationRule::query()->where('user_id', $user->id)->delete();
            Category::query()->where('user_id', $user->id)->delete();
            Account::query()->where('user_id', $user->id)->delete();
            PlannedCashFlow::query()->where('user_id', $user->id)->delete();
            FinancialPlan::query()->where('user_id', $user->id)->delete();

            $this->createFinancialData($user, $definition, $asOf);

            return $user->fresh([
                'financialPlan',
                'accounts',
                'plannedCashFlows',
                'transactions',
                'categorizationRules',
            ]) ?? $user;
        });
    }

    /**
     * @return array{
     *   name: string,
     *   email: string,
     *   persona_type: PersonaType,
     *   persona_label: string,
     *   description: string,
     *   member_since: string,
     *   avatar_initials: string,
     *   plan: array<string, int>,
     *   accounts: list<array<string, mixed>>,
     *   cash_flows: list<array<string, mixed>>,
     *   rules: list<array<string, mixed>>,
     *   reviewed: list<array<string, mixed>>,
     *   unreviewed: list<array<string, mixed>>
     * }
     */
    private function definitionFor(PersonaType $type): array
    {
        return match ($type) {
            PersonaType::Reckless => $this->recklessDefinition(),
            PersonaType::Average => $this->averageDefinition(),
            PersonaType::HighNetWorth => $this->highNetWorthDefinition(),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function createFinancialData(User $user, array $definition, Carbon $asOf): void
    {
        FinancialPlan::query()->create([
            'user_id' => $user->id,
            ...$definition['plan'],
        ]);

        $accountsByKey = [];

        foreach ($definition['accounts'] as $index => $accountData) {
            $key = $accountData['key'];
            unset($accountData['key']);

            $accountsByKey[$key] = Account::query()->create([
                'user_id' => $user->id,
                'sort_order' => $index + 1,
                ...$accountData,
            ]);
        }

        foreach ($definition['cash_flows'] as $cashFlow) {
            PlannedCashFlow::query()->create([
                'user_id' => $user->id,
                'due_on' => $asOf->copy()->day(min((int) $cashFlow['due_day'], $asOf->daysInMonth))->toDateString(),
                'name' => $cashFlow['name'],
                'amount_cents' => $cashFlow['amount_cents'],
                'kind' => $cashFlow['kind'],
                'is_essential' => $cashFlow['is_essential'],
                'bucket' => $cashFlow['bucket'] ?? null,
            ]);
        }

        foreach ($definition['rules'] as $rule) {
            $accountKey = $rule['account_key'] ?? null;
            unset($rule['account_key']);

            $merchantContains = (string) ($rule['merchant_contains'] ?? '');
            $targetBucket = $rule['target_bucket'] ?? null;
            $targetSubcategory = (string) ($rule['target_subcategory'] ?? '');

            CategorizationRule::query()->create([
                'user_id' => $user->id,
                'account_id' => $accountKey !== null ? $accountsByKey[$accountKey]->id : null,
                ...$rule,
                'merchant_id' => $this->merchantIdFor($merchantContains),
                'category_id' => $targetBucket !== null && $targetSubcategory !== ''
                    ? $this->systemCategoryIdFor($targetBucket, $targetSubcategory)
                    : null,
            ]);
        }

        foreach ($definition['reviewed'] as $row) {
            $categoryId = $this->systemCategoryIdFor($row['bucket'], $row['subcategory']);

            Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $accountsByKey[$row['account_key']]->id,
                'merchant' => $row['merchant'],
                'raw_merchant_descriptor' => $row['merchant'],
                'amount_cents' => $row['amount_cents'],
                'kind' => $row['kind'],
                'bucket' => $row['bucket'],
                'subcategory' => $row['subcategory'],
                'category_id' => $categoryId,
                'transaction_date' => $asOf->copy()->day(min((int) $row['day'], $asOf->daysInMonth))->toDateString(),
                'reviewed_at' => $asOf->copy()->subDays(2),
                'review_source' => ReviewSource::Manual,
                'confidence' => 100,
                'review_explanation' => 'Seeded reviewed transaction.',
            ]);
        }

        foreach ($definition['unreviewed'] as $row) {
            Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $accountsByKey[$row['account_key']]->id,
                'merchant' => $row['merchant'],
                'raw_merchant_descriptor' => $row['merchant'],
                'amount_cents' => $row['amount_cents'],
                'kind' => $row['kind'],
                'bucket' => null,
                'subcategory' => null,
                'category_id' => null,
                'transaction_date' => $asOf->copy()->day(min((int) $row['day'], $asOf->daysInMonth))->toDateString(),
                'reviewed_at' => null,
            ]);
        }
    }

    private function systemCategoryIdFor(Bucket|string $bucket, string $subcategory): ?int
    {
        $bucketValue = $bucket instanceof Bucket ? $bucket->value : $bucket;

        return Category::query()
            ->system()
            ->active()
            ->where('bucket', $bucketValue)
            ->where('normalized_name', CatalogNormalizer::name($subcategory))
            ->value('id');
    }

    private function merchantIdFor(string $merchantContains): ?int
    {
        $needle = trim($merchantContains);

        if ($needle === '') {
            return null;
        }

        $normalized = CatalogNormalizer::name($needle);

        $exactId = Merchant::query()
            ->where('normalized_name', $normalized)
            ->value('id');

        if ($exactId !== null) {
            return (int) $exactId;
        }

        $aliasMatchId = Merchant::query()
            ->whereHas('aliases', function ($query) use ($normalized): void {
                $query->where('normalized_pattern', CatalogNormalizer::descriptor($normalized))
                    ->orWhereRaw('lower(pattern) = ?', [$normalized]);
            })
            ->value('id');

        if ($aliasMatchId !== null) {
            return (int) $aliasMatchId;
        }

        return app(MerchantResolver::class)->resolve($needle)?->merchant->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function recklessDefinition(): array
    {
        return [
            'name' => 'Alex Rivera',
            'email' => 'alex.rivera@clearspend.demo',
            'persona_type' => PersonaType::Reckless,
            'persona_label' => PersonaType::Reckless->label(),
            'description' => 'Impulse purchases, stacked subscriptions, and credit-card pressure leave little safe-to-spend room.',
            'member_since' => '2025-11-12',
            'avatar_initials' => 'AR',
            'plan' => [
                'needs_percent' => 50,
                'wants_percent' => 40,
                'savings_percent' => 10,
                'safety_buffer_cents' => 15_000,
                'monthly_income_cents' => 450_000,
            ],
            'accounts' => [
                [
                    'key' => 'checking',
                    'institution_name' => 'Chase',
                    'name' => 'Everyday Checking',
                    'mask' => '1190',
                    'type' => 'checking',
                    'balance_cents' => 42_150,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'chase',
                ],
                [
                    'key' => 'savings',
                    'institution_name' => 'Ally',
                    'name' => 'Rainy Day Savings',
                    'mask' => '4412',
                    'type' => 'savings',
                    'balance_cents' => 18_000,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'ally',
                ],
                [
                    'key' => 'credit',
                    'institution_name' => 'Citi',
                    'name' => 'Double Cash Card',
                    'mask' => '8834',
                    'type' => 'credit',
                    'balance_cents' => -285_400,
                    'sync_status' => AccountSyncStatus::Error,
                    'logo_key' => 'citi',
                ],
            ],
            'cash_flows' => [
                [
                    'name' => 'Retail paycheck',
                    'amount_cents' => 225_000,
                    'kind' => 'income',
                    'due_day' => 31,
                    'is_essential' => false,
                    'bucket' => null,
                ],
                [
                    'name' => 'Rent',
                    'amount_cents' => 145_000,
                    'kind' => 'bill',
                    'due_day' => 28,
                    'is_essential' => true,
                    'bucket' => Bucket::Need,
                ],
                [
                    'name' => 'Phone & internet',
                    'amount_cents' => 18_500,
                    'kind' => 'bill',
                    'due_day' => 29,
                    'is_essential' => true,
                    'bucket' => Bucket::Need,
                ],
                [
                    'name' => 'Minimum credit payment',
                    'amount_cents' => 35_000,
                    'kind' => 'bill',
                    'due_day' => 27,
                    'is_essential' => true,
                    'bucket' => Bucket::Savings,
                ],
            ],
            'rules' => [
                [
                    'name' => 'DoorDash is dining',
                    'merchant_contains' => 'doordash',
                    'target_bucket' => Bucket::Want,
                    'target_subcategory' => 'dining',
                    'priority' => 20,
                    'enabled' => true,
                    'auto_review' => true,
                ],
            ],
            'reviewed' => [
                ['account_key' => 'checking', 'merchant' => 'Payroll RetailCo', 'amount_cents' => 225_000, 'kind' => TransactionKind::Income, 'bucket' => Bucket::Savings, 'subcategory' => 'income', 'day' => 1],
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 687, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 1],
                ['account_key' => 'credit', 'merchant' => 'Uber Eats', 'amount_cents' => 3_245, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 1],
                ['account_key' => 'checking', 'merchant' => '7-Eleven', 'amount_cents' => 1_248, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 1],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 4_860, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'transportation', 'day' => 2],
                ['account_key' => 'credit', 'merchant' => 'DoorDash', 'amount_cents' => 4_812, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 2],
                ['account_key' => 'checking', 'merchant' => 'CVS', 'amount_cents' => 2_314, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'health', 'day' => 2],
                ['account_key' => 'checking', 'merchant' => 'DoorDash', 'amount_cents' => 2_980, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 3],
                ['account_key' => 'credit', 'merchant' => 'Amazon', 'amount_cents' => 7_499, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 3],
                ['account_key' => 'checking', 'merchant' => 'Instill Coffee', 'amount_cents' => 615, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 3],
                ['account_key' => 'credit', 'merchant' => 'Sephora', 'amount_cents' => 18_640, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 4],
                ['account_key' => 'checking', 'merchant' => 'Chipotle', 'amount_cents' => 1_485, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 4],
                ['account_key' => 'credit', 'merchant' => 'Uber', 'amount_cents' => 2_240, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'transportation', 'day' => 4],
                ['account_key' => 'credit', 'merchant' => 'Uber', 'amount_cents' => 3_420, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'transportation', 'day' => 5],
                ['account_key' => 'checking', 'merchant' => 'McDonald\'s', 'amount_cents' => 1_128, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 5],
                ['account_key' => 'credit', 'merchant' => 'Steam Games', 'amount_cents' => 5_999, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'entertainment', 'day' => 5],
                ['account_key' => 'checking', 'merchant' => 'Hulu', 'amount_cents' => 1_799, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'entertainment', 'day' => 6],
                ['account_key' => 'checking', 'merchant' => 'Netflix', 'amount_cents' => 1_549, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'entertainment', 'day' => 6],
                ['account_key' => 'credit', 'merchant' => 'Target', 'amount_cents' => 8_742, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 6],
                ['account_key' => 'checking', 'merchant' => 'DoorDash', 'amount_cents' => 4_210, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 6],
                ['account_key' => 'credit', 'merchant' => 'Nike Store', 'amount_cents' => 14_250, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 7],
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 742, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 7],
                ['account_key' => 'credit', 'merchant' => 'AMC Theatres', 'amount_cents' => 3_860, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'entertainment', 'day' => 7],
                ['account_key' => 'checking', 'merchant' => 'Walmart', 'amount_cents' => 6_318, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 8],
                ['account_key' => 'credit', 'merchant' => 'DoorDash', 'amount_cents' => 5_640, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 8],
                ['account_key' => 'checking', 'merchant' => 'Lyft', 'amount_cents' => 2_890, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'transportation', 'day' => 8],
            ],
            'unreviewed' => [
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 655, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'checking', 'merchant' => 'DoorDash', 'amount_cents' => 3_915, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'credit', 'merchant' => 'Amazon', 'amount_cents' => 4_299, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 5_120, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'credit', 'merchant' => 'Chipotle', 'amount_cents' => 1_620, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'checking', 'merchant' => 'Venmo Friends', 'amount_cents' => 4_000, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'credit', 'merchant' => 'Ticketmaster', 'amount_cents' => 24_800, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'checking', 'merchant' => 'Uber Eats', 'amount_cents' => 3_480, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'credit', 'merchant' => 'Urban Outfitters', 'amount_cents' => 9_870, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'checking', 'merchant' => 'Instill Coffee', 'amount_cents' => 580, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'credit', 'merchant' => 'DoorDash', 'amount_cents' => 4_105, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'checking', 'merchant' => 'CVS', 'amount_cents' => 1_899, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'credit', 'merchant' => 'Best Buy', 'amount_cents' => 89_999, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'checking', 'merchant' => 'DoorDash', 'amount_cents' => 2_760, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'credit', 'merchant' => 'Uber', 'amount_cents' => 1_940, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'checking', 'merchant' => 'Spotify Duo', 'amount_cents' => 1_699, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'checking', 'merchant' => 'Apple Music', 'amount_cents' => 1_099, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'credit', 'merchant' => 'Sephora', 'amount_cents' => 6_420, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'checking', 'merchant' => 'Disney+', 'amount_cents' => 1_599, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'checking', 'merchant' => 'HBO Max', 'amount_cents' => 1_699, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'credit', 'merchant' => 'DoorDash', 'amount_cents' => 5_230, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'checking', 'merchant' => 'Walgreens', 'amount_cents' => 2_450, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'credit', 'merchant' => 'Uber Eats', 'amount_cents' => 3_870, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 4_540, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'credit', 'merchant' => 'Zara', 'amount_cents' => 12_450, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 712, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'credit', 'merchant' => 'DoorDash', 'amount_cents' => 4_680, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'checking', 'merchant' => 'Unknown Merchant 77', 'amount_cents' => 6_400, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'credit', 'merchant' => 'Target', 'amount_cents' => 15_280, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'checking', 'merchant' => 'DoorDash', 'amount_cents' => 3_210, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'credit', 'merchant' => 'Apple Store', 'amount_cents' => 129_900, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'checking', 'merchant' => 'Uber', 'amount_cents' => 2_680, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'credit', 'merchant' => 'Happy Hour Bar', 'amount_cents' => 7_840, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'checking', 'merchant' => 'Instacart', 'amount_cents' => 9_870, 'kind' => TransactionKind::Expense, 'day' => 21],
                ['account_key' => 'credit', 'merchant' => 'DoorDash', 'amount_cents' => 4_020, 'kind' => TransactionKind::Expense, 'day' => 21],
                ['account_key' => 'checking', 'merchant' => 'ATM Withdrawal', 'amount_cents' => 6_000, 'kind' => TransactionKind::Expense, 'day' => 21],
                ['account_key' => 'checking', 'merchant' => 'Landlord LLC', 'amount_cents' => 145_000, 'kind' => TransactionKind::Expense, 'day' => 22],
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 690, 'kind' => TransactionKind::Expense, 'day' => 22],
                ['account_key' => 'credit', 'merchant' => 'DoorDash', 'amount_cents' => 3_550, 'kind' => TransactionKind::Expense, 'day' => 22],
                ['account_key' => 'checking', 'merchant' => 'Venmo Rent Split', 'amount_cents' => 2_500, 'kind' => TransactionKind::Expense, 'day' => 22],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function averageDefinition(): array
    {
        return [
            'name' => 'Jordan Lee',
            'email' => 'jordan.lee@clearspend.demo',
            'persona_type' => PersonaType::Average,
            'persona_label' => PersonaType::Average->label(),
            'description' => 'Balanced paycheck budgeting with rent, groceries, moderate wants, and a practical emergency fund.',
            'member_since' => '2026-01-01',
            'avatar_initials' => 'JL',
            'plan' => [
                'needs_percent' => 50,
                'wants_percent' => 30,
                'savings_percent' => 20,
                'safety_buffer_cents' => 25_000,
                'monthly_income_cents' => 520_000,
            ],
            'accounts' => [
                [
                    'key' => 'checking',
                    'institution_name' => 'First Horizon',
                    'name' => 'Everyday Checking',
                    'mask' => '4821',
                    'type' => 'checking',
                    'balance_cents' => 184_350,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'first-horizon',
                ],
                [
                    'key' => 'savings',
                    'institution_name' => 'SoFi',
                    'name' => 'Emergency Fund',
                    'mask' => '9033',
                    'type' => 'savings',
                    'balance_cents' => 320_000,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'sofi',
                ],
                [
                    'key' => 'credit',
                    'institution_name' => 'Capital One',
                    'name' => 'Rewards Card',
                    'mask' => '7710',
                    'type' => 'credit',
                    'balance_cents' => -62_400,
                    'sync_status' => AccountSyncStatus::Error,
                    'logo_key' => 'capital-one',
                ],
            ],
            'cash_flows' => [
                [
                    'name' => 'Acme Corp paycheck',
                    'amount_cents' => 260_000,
                    'kind' => 'income',
                    'due_day' => 31,
                    'is_essential' => false,
                    'bucket' => null,
                ],
                [
                    'name' => 'Rent',
                    'amount_cents' => 165_000,
                    'kind' => 'bill',
                    'due_day' => 28,
                    'is_essential' => true,
                    'bucket' => Bucket::Need,
                ],
                [
                    'name' => 'Electric',
                    'amount_cents' => 12_400,
                    'kind' => 'bill',
                    'due_day' => 29,
                    'is_essential' => true,
                    'bucket' => Bucket::Need,
                ],
            ],
            'rules' => [
                [
                    'name' => 'Netflix is entertainment',
                    'merchant_contains' => 'netflix',
                    'target_bucket' => Bucket::Want,
                    'target_subcategory' => 'entertainment',
                    'priority' => 10,
                    'enabled' => true,
                    'auto_review' => true,
                ],
                [
                    'name' => 'Exact rent amount is a need',
                    'merchant_contains' => 'landlord',
                    'amount_cents_min' => 165_000,
                    'amount_cents_max' => 165_000,
                    'target_bucket' => Bucket::Need,
                    'target_subcategory' => 'housing',
                    'priority' => 5,
                    'enabled' => true,
                    'auto_review' => true,
                ],
            ],
            'reviewed' => [
                ['account_key' => 'checking', 'merchant' => 'Payroll Acme', 'amount_cents' => 260_000, 'kind' => TransactionKind::Income, 'bucket' => Bucket::Savings, 'subcategory' => 'income', 'day' => 1],
                ['account_key' => 'checking', 'merchant' => 'Instill Coffee', 'amount_cents' => 540, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 1],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 6_812, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 1],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 3_980, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'transportation', 'day' => 2],
                ['account_key' => 'checking', 'merchant' => 'Chipotle', 'amount_cents' => 1_245, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 2],
                ['account_key' => 'credit', 'merchant' => 'Amazon', 'amount_cents' => 3_299, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 2],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 8_423, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 3],
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 612, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 3],
                ['account_key' => 'credit', 'merchant' => 'Uber', 'amount_cents' => 1_840, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'transportation', 'day' => 3],
                ['account_key' => 'checking', 'merchant' => 'City Metro Pass', 'amount_cents' => 7_500, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'transportation', 'day' => 4],
                ['account_key' => 'checking', 'merchant' => 'Sweetgreen', 'amount_cents' => 1_680, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 4],
                ['account_key' => 'checking', 'merchant' => 'Pharmacy CVS', 'amount_cents' => 2_145, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'health', 'day' => 4],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 4_250, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'transportation', 'day' => 5],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 4_120, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 5],
                ['account_key' => 'credit', 'merchant' => 'Target', 'amount_cents' => 5_680, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 5],
                ['account_key' => 'checking', 'merchant' => 'Spotify', 'amount_cents' => 1_099, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'entertainment', 'day' => 6],
                ['account_key' => 'checking', 'merchant' => 'Instill Coffee', 'amount_cents' => 580, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 6],
                ['account_key' => 'checking', 'merchant' => 'Trader Joe\'s', 'amount_cents' => 5_940, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 6],
                ['account_key' => 'savings', 'merchant' => 'To Emergency Fund', 'amount_cents' => 5_000, 'kind' => TransactionKind::Transfer, 'bucket' => Bucket::Savings, 'subcategory' => 'transfer', 'day' => 7],
                ['account_key' => 'checking', 'merchant' => 'Local Farmers Market', 'amount_cents' => 3_250, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 7],
                ['account_key' => 'credit', 'merchant' => 'Olive Garden', 'amount_cents' => 4_860, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 7],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 4_010, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'transportation', 'day' => 8],
                ['account_key' => 'checking', 'merchant' => 'Chipotle', 'amount_cents' => 1_310, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 8],
                ['account_key' => 'checking', 'merchant' => 'Internet Fiber Co', 'amount_cents' => 7_999, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'utilities', 'day' => 8],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 7_215, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 9],
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 595, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 9],
                ['account_key' => 'credit', 'merchant' => 'Amazon', 'amount_cents' => 2_499, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 9],
            ],
            'unreviewed' => [
                ['account_key' => 'checking', 'merchant' => 'Netflix', 'amount_cents' => 1_599, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'checking', 'merchant' => 'Instill Coffee', 'amount_cents' => 620, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 5_480, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 4_380, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'checking', 'merchant' => 'Sweetgreen', 'amount_cents' => 1_590, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'credit', 'merchant' => 'Amazon', 'amount_cents' => 4_120, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'checking', 'merchant' => 'Chipotle', 'amount_cents' => 1_345, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'checking', 'merchant' => 'Pharmacy CVS', 'amount_cents' => 1_875, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'credit', 'merchant' => 'Uber', 'amount_cents' => 2_160, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 9_140, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 640, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'checking', 'merchant' => 'City Parking', 'amount_cents' => 1_200, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'credit', 'merchant' => 'Target', 'amount_cents' => 11_216, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'checking', 'merchant' => 'Chipotle', 'amount_cents' => 1_410, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 4_090, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'checking', 'merchant' => 'Capital One Payment', 'amount_cents' => 20_000, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'checking', 'merchant' => 'Instill Coffee', 'amount_cents' => 560, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'credit', 'merchant' => 'Amazon', 'amount_cents' => 3_870, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'checking', 'merchant' => 'Landlord LLC', 'amount_cents' => 165_000, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 4_860, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'checking', 'merchant' => 'Starbucks', 'amount_cents' => 605, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'checking', 'merchant' => 'Electric Utility', 'amount_cents' => 12_400, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'checking', 'merchant' => 'Chipotle', 'amount_cents' => 1_290, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'credit', 'merchant' => 'Bookstore Indie', 'amount_cents' => 2_840, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'checking', 'merchant' => 'Unknown Vendor XYZ', 'amount_cents' => 4_200, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 6_320, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 4_470, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'checking', 'merchant' => 'Amazon', 'amount_cents' => 5_899, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'checking', 'merchant' => 'Instill Coffee', 'amount_cents' => 575, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'credit', 'merchant' => 'Target', 'amount_cents' => 3_640, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'checking', 'merchant' => 'Gym Membership', 'amount_cents' => 4_999, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'checking', 'merchant' => 'Trader Joe\'s', 'amount_cents' => 5_210, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'checking', 'merchant' => 'Uber', 'amount_cents' => 1_760, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'checking', 'merchant' => 'Refund Apple', 'amount_cents' => 2_164, 'kind' => TransactionKind::Refund, 'day' => 21],
                ['account_key' => 'checking', 'merchant' => 'HEB', 'amount_cents' => 7_880, 'kind' => TransactionKind::Expense, 'day' => 21],
                ['account_key' => 'checking', 'merchant' => 'Chipotle', 'amount_cents' => 1_380, 'kind' => TransactionKind::Expense, 'day' => 21],
                ['account_key' => 'checking', 'merchant' => 'Instill Coffee', 'amount_cents' => 2_300, 'kind' => TransactionKind::Expense, 'day' => 22],
                ['account_key' => 'checking', 'merchant' => 'Shell Gas', 'amount_cents' => 4_160, 'kind' => TransactionKind::Expense, 'day' => 22],
                ['account_key' => 'credit', 'merchant' => 'Local Pizza Night', 'amount_cents' => 3_420, 'kind' => TransactionKind::Expense, 'day' => 22],
                ['account_key' => 'checking', 'merchant' => 'Pharmacy CVS', 'amount_cents' => 1_560, 'kind' => TransactionKind::Expense, 'day' => 22],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function highNetWorthDefinition(): array
    {
        return [
            'name' => 'Morgan Chen',
            'email' => 'morgan.chen@clearspend.demo',
            'persona_type' => PersonaType::HighNetWorth,
            'persona_label' => PersonaType::HighNetWorth->label(),
            'description' => 'High income with brokerage and retirement contributions, travel, and large but planned housing costs.',
            'member_since' => '2024-03-18',
            'avatar_initials' => 'MC',
            'plan' => [
                'needs_percent' => 40,
                'wants_percent' => 20,
                'savings_percent' => 40,
                'safety_buffer_cents' => 150_000,
                'monthly_income_cents' => 1_850_000,
            ],
            'accounts' => [
                [
                    'key' => 'checking',
                    'institution_name' => 'JPMorgan Private',
                    'name' => 'Premium Checking',
                    'mask' => '2201',
                    'type' => 'checking',
                    'balance_cents' => 1_245_000,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'jpmorgan',
                ],
                [
                    'key' => 'savings',
                    'institution_name' => 'Vanguard',
                    'name' => 'High-Yield Cash',
                    'mask' => '5588',
                    'type' => 'savings',
                    'balance_cents' => 4_820_000,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'vanguard',
                ],
                [
                    'key' => 'brokerage',
                    'institution_name' => 'Fidelity',
                    'name' => 'Taxable Brokerage',
                    'mask' => '7742',
                    'type' => 'brokerage',
                    'balance_cents' => 18_450_000,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'fidelity',
                ],
                [
                    'key' => 'retirement',
                    'institution_name' => 'Fidelity',
                    'name' => 'Traditional IRA',
                    'mask' => '9901',
                    'type' => 'retirement',
                    'balance_cents' => 32_100_000,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'fidelity',
                ],
                [
                    'key' => 'credit',
                    'institution_name' => 'Amex',
                    'name' => 'Platinum Card',
                    'mask' => '1005',
                    'type' => 'credit',
                    'balance_cents' => -48_200,
                    'sync_status' => AccountSyncStatus::Healthy,
                    'logo_key' => 'amex',
                ],
            ],
            'cash_flows' => [
                [
                    'name' => 'Executive paycheck',
                    'amount_cents' => 925_000,
                    'kind' => 'income',
                    'due_day' => 31,
                    'is_essential' => false,
                    'bucket' => null,
                ],
                [
                    'name' => 'Mortgage',
                    'amount_cents' => 485_000,
                    'kind' => 'bill',
                    'due_day' => 28,
                    'is_essential' => true,
                    'bucket' => Bucket::Need,
                ],
                [
                    'name' => 'Property tax escrow',
                    'amount_cents' => 62_000,
                    'kind' => 'bill',
                    'due_day' => 29,
                    'is_essential' => true,
                    'bucket' => Bucket::Need,
                ],
            ],
            'rules' => [
                [
                    'name' => 'Delta travel is a want',
                    'merchant_contains' => 'delta',
                    'target_bucket' => Bucket::Want,
                    'target_subcategory' => 'travel',
                    'priority' => 8,
                    'enabled' => true,
                    'auto_review' => true,
                ],
                [
                    'name' => 'Vanguard contributions are savings',
                    'merchant_contains' => 'vanguard',
                    'target_bucket' => Bucket::Savings,
                    'target_subcategory' => 'investing',
                    'priority' => 5,
                    'enabled' => true,
                    'auto_review' => true,
                ],
                [
                    'name' => 'Mortgage servicer is housing',
                    'merchant_contains' => 'mortgage',
                    'target_bucket' => Bucket::Need,
                    'target_subcategory' => 'housing',
                    'priority' => 4,
                    'enabled' => true,
                    'auto_review' => true,
                ],
                [
                    'name' => 'Charitable giving',
                    'merchant_contains' => 'united way',
                    'target_bucket' => Bucket::Want,
                    'target_subcategory' => 'charity',
                    'priority' => 12,
                    'enabled' => true,
                    'auto_review' => false,
                ],
            ],
            'reviewed' => [
                ['account_key' => 'checking', 'merchant' => 'Payroll Apex Labs', 'amount_cents' => 925_000, 'kind' => TransactionKind::Income, 'bucket' => Bucket::Savings, 'subcategory' => 'income', 'day' => 1],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 840, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 1],
                ['account_key' => 'credit', 'merchant' => 'Uber Black', 'amount_cents' => 4_620, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'transportation', 'day' => 1],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 12_480, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 2],
                ['account_key' => 'credit', 'merchant' => 'Sweetgreen', 'amount_cents' => 2_140, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 2],
                ['account_key' => 'checking', 'merchant' => 'Cleaners Premium', 'amount_cents' => 8_500, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'services', 'day' => 2],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 18_420, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 3],
                ['account_key' => 'credit', 'merchant' => 'Equinox', 'amount_cents' => 28_000, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'health', 'day' => 3],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 780, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 3],
                ['account_key' => 'credit', 'merchant' => 'Nobu', 'amount_cents' => 24_860, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 4],
                ['account_key' => 'checking', 'merchant' => 'Uber Black', 'amount_cents' => 5_120, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'transportation', 'day' => 4],
                ['account_key' => 'checking', 'merchant' => 'Pharmacy Walgreens', 'amount_cents' => 3_240, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'health', 'day' => 4],
                ['account_key' => 'brokerage', 'merchant' => 'Vanguard Brokerage Transfer', 'amount_cents' => 250_000, 'kind' => TransactionKind::Transfer, 'bucket' => Bucket::Savings, 'subcategory' => 'investing', 'day' => 5],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 9_860, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 5],
                ['account_key' => 'credit', 'merchant' => 'Sweetgreen', 'amount_cents' => 1_980, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 5],
                ['account_key' => 'retirement', 'merchant' => 'IRA Contribution', 'amount_cents' => 150_000, 'kind' => TransactionKind::Transfer, 'bucket' => Bucket::Savings, 'subcategory' => 'retirement', 'day' => 6],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 820, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 6],
                ['account_key' => 'credit', 'merchant' => 'Apple Store', 'amount_cents' => 14_500, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'shopping', 'day' => 6],
                ['account_key' => 'checking', 'merchant' => 'Housekeeping Co', 'amount_cents' => 18_000, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'services', 'day' => 7],
                ['account_key' => 'credit', 'merchant' => 'Farmers Market Cafe', 'amount_cents' => 4_260, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 7],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 11_340, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Need, 'subcategory' => 'groceries', 'day' => 7],
                ['account_key' => 'credit', 'merchant' => 'Delta Air Lines', 'amount_cents' => 86_400, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'travel', 'day' => 8],
                ['account_key' => 'checking', 'merchant' => 'Uber Black', 'amount_cents' => 6_480, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'transportation', 'day' => 8],
                ['account_key' => 'credit', 'merchant' => 'Airport Lounge', 'amount_cents' => 7_500, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'travel', 'day' => 8],
                ['account_key' => 'checking', 'merchant' => 'United Way', 'amount_cents' => 50_000, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'charity', 'day' => 9],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 790, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 9],
                ['account_key' => 'credit', 'merchant' => 'Sweetgreen', 'amount_cents' => 2_050, 'kind' => TransactionKind::Expense, 'bucket' => Bucket::Want, 'subcategory' => 'dining', 'day' => 9],
            ],
            'unreviewed' => [
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 14_260, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'credit', 'merchant' => 'Uber Black', 'amount_cents' => 5_340, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 860, 'kind' => TransactionKind::Expense, 'day' => 10],
                ['account_key' => 'credit', 'merchant' => 'Dry Cleaning Atelier', 'amount_cents' => 12_400, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'checking', 'merchant' => 'Sweetgreen', 'amount_cents' => 2_180, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'credit', 'merchant' => 'Amazon', 'amount_cents' => 8_640, 'kind' => TransactionKind::Expense, 'day' => 11],
                ['account_key' => 'credit', 'merchant' => 'Four Seasons Hotel', 'amount_cents' => 142_500, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'credit', 'merchant' => 'Uber Black', 'amount_cents' => 7_820, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'checking', 'merchant' => 'Room Service Tip', 'amount_cents' => 4_000, 'kind' => TransactionKind::Expense, 'day' => 12],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 10_980, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'credit', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 920, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'checking', 'merchant' => 'Parking Garage Downtown', 'amount_cents' => 3_600, 'kind' => TransactionKind::Expense, 'day' => 13],
                ['account_key' => 'checking', 'merchant' => 'Mortgage Servicing Co', 'amount_cents' => 485_000, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 810, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'credit', 'merchant' => 'Sweetgreen', 'amount_cents' => 1_940, 'kind' => TransactionKind::Expense, 'day' => 14],
                ['account_key' => 'checking', 'merchant' => 'Vanguard Contribution', 'amount_cents' => 200_000, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 13_720, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'credit', 'merchant' => 'Uber Black', 'amount_cents' => 4_980, 'kind' => TransactionKind::Expense, 'day' => 15],
                ['account_key' => 'checking', 'merchant' => 'Property Manager HOA', 'amount_cents' => 42_000, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'credit', 'merchant' => 'Nobu', 'amount_cents' => 31_250, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 770, 'kind' => TransactionKind::Expense, 'day' => 16],
                ['account_key' => 'credit', 'merchant' => 'Delta Air Lines', 'amount_cents' => 64_200, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'checking', 'merchant' => 'Airport Parking', 'amount_cents' => 6_400, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'credit', 'merchant' => 'Uber Black', 'amount_cents' => 5_760, 'kind' => TransactionKind::Expense, 'day' => 17],
                ['account_key' => 'checking', 'merchant' => 'Tax Advisor LLC', 'amount_cents' => 35_000, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 11_540, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'credit', 'merchant' => 'Sweetgreen', 'amount_cents' => 2_090, 'kind' => TransactionKind::Expense, 'day' => 18],
                ['account_key' => 'credit', 'merchant' => 'Unknown Boutique', 'amount_cents' => 9_800, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 850, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'credit', 'merchant' => 'Uber Black', 'amount_cents' => 4_420, 'kind' => TransactionKind::Expense, 'day' => 19],
                ['account_key' => 'checking', 'merchant' => 'Legal Retainer Firm', 'amount_cents' => 75_000, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 15_360, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'credit', 'merchant' => 'Wine Shop Reserve', 'amount_cents' => 18_900, 'kind' => TransactionKind::Expense, 'day' => 20],
                ['account_key' => 'checking', 'merchant' => 'Charity Match Program', 'amount_cents' => 25_000, 'kind' => TransactionKind::Expense, 'day' => 21],
                ['account_key' => 'credit', 'merchant' => 'Sweetgreen', 'amount_cents' => 2_240, 'kind' => TransactionKind::Expense, 'day' => 21],
                ['account_key' => 'checking', 'merchant' => 'Blue Bottle Coffee', 'amount_cents' => 800, 'kind' => TransactionKind::Expense, 'day' => 21],
                ['account_key' => 'brokerage', 'merchant' => 'Dividend Reinvest', 'amount_cents' => 12_400, 'kind' => TransactionKind::Income, 'day' => 22],
                ['account_key' => 'checking', 'merchant' => 'Whole Foods', 'amount_cents' => 16_820, 'kind' => TransactionKind::Expense, 'day' => 22],
                ['account_key' => 'credit', 'merchant' => 'Uber Black', 'amount_cents' => 5_180, 'kind' => TransactionKind::Expense, 'day' => 22],
                ['account_key' => 'checking', 'merchant' => 'Concierge Services', 'amount_cents' => 12_000, 'kind' => TransactionKind::Expense, 'day' => 22],
            ],
        ];
    }
}
