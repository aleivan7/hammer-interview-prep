<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('persona_type')->nullable()->after('email');
            $table->string('persona_label')->nullable()->after('persona_type');
            $table->text('description')->nullable()->after('persona_label');
            $table->date('member_since')->nullable()->after('description');
            $table->string('avatar_initials', 8)->nullable()->after('member_since');
            $table->index('persona_type');
        });

        Schema::table('financial_plans', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'sort_order']);
        });

        Schema::table('planned_cash_flows', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'due_on']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'reviewed_at']);
        });

        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'priority']);
        });

        $legacyUserId = null;

        if (
            DB::table('financial_plans')->exists()
            || DB::table('accounts')->exists()
            || DB::table('planned_cash_flows')->exists()
            || DB::table('transactions')->exists()
            || DB::table('categorization_rules')->exists()
        ) {
            $legacyEmail = 'jordan.lee@clearspend.demo';
            $legacyUserId = DB::table('users')->where('email', $legacyEmail)->value('id');

            if ($legacyUserId === null) {
                $legacyUserId = DB::table('users')->insertGetId([
                    'name' => 'Jordan Lee',
                    'email' => $legacyEmail,
                    'password' => null,
                    'persona_type' => 'average',
                    'persona_label' => 'Average Spender',
                    'description' => 'Legacy single-tenant ClearSpend dataset migrated under Jordan Lee.',
                    'member_since' => '2026-01-01',
                    'avatar_initials' => 'JL',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (['financial_plans', 'accounts', 'planned_cash_flows', 'transactions', 'categorization_rules'] as $table) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $legacyUserId]);
            }
        }

        Schema::table('financial_plans', function (Blueprint $table) {
            $table->unique('user_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('planned_cash_flows', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->unique(['user_id', 'idempotency_key']);
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        // financial_plans.user_id stays unique and non-null via the unique index + future writes.
        // Existing nullable unique constraint after backfill: enforce non-null.
        Schema::table('financial_plans', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'priority']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropIndex(['user_id', 'transaction_date']);
            $table->dropIndex(['user_id', 'reviewed_at']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('idempotency_key');
        });

        Schema::table('planned_cash_flows', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'due_on']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'sort_order']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('financial_plans', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropConstrainedForeignId('user_id');
        });

        // Demo personas (and the legacy Jordan backfill) use null passwords.
        // Remove them before restoring the non-null password column.
        DB::table('users')->whereNull('password')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['persona_type']);
            $table->dropColumn([
                'persona_type',
                'persona_label',
                'description',
                'member_since',
                'avatar_initials',
            ]);
            $table->string('password')->nullable(false)->change();
        });
    }
};
