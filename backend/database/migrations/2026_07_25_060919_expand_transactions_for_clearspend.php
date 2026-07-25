<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->integer('amount_cents')->default(0)->after('merchant');
            $table->string('kind')->default('expense')->after('amount_cents');
            $table->string('bucket')->nullable()->after('kind');
            $table->string('subcategory')->nullable()->after('bucket');
            $table->string('review_source')->nullable()->after('reviewed_at');
            $table->unsignedTinyInteger('confidence')->nullable()->after('review_source');
            $table->text('review_explanation')->nullable()->after('confidence');
            $table->text('notes')->nullable()->after('review_explanation');
            $table->string('idempotency_key')->nullable()->unique()->after('notes');
        });

        $rows = DB::table('transactions')->select('id', 'amount', 'category')->get();

        foreach ($rows as $row) {
            $bucket = match ($row->category) {
                'debt_savings' => 'savings',
                'need', 'want' => $row->category,
                default => $row->category,
            };

            DB::table('transactions')->where('id', $row->id)->update([
                'amount_cents' => (int) round(((float) $row->amount) * 100),
                'bucket' => $bucket,
                'kind' => 'expense',
            ]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['amount', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->default(0)->after('merchant');
            $table->string('category')->nullable()->after('amount');
        });

        $rows = DB::table('transactions')->select('id', 'amount_cents', 'bucket')->get();

        foreach ($rows as $row) {
            $category = match ($row->bucket) {
                'savings' => 'debt_savings',
                default => $row->bucket,
            };

            DB::table('transactions')->where('id', $row->id)->update([
                'amount' => number_format(((int) $row->amount_cents) / 100, 2, '.', ''),
                'category' => $category,
            ]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn([
                'amount_cents',
                'kind',
                'bucket',
                'subcategory',
                'review_source',
                'confidence',
                'review_explanation',
                'notes',
                'idempotency_key',
            ]);
        });
    }
};
