<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('raw_merchant_descriptor')->nullable()->after('merchant');
            $table->foreignId('merchant_id')->nullable()->after('raw_merchant_descriptor')->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->after('subcategory')->constrained()->nullOnDelete();

            $table->index('raw_merchant_descriptor');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropIndex(['raw_merchant_descriptor']);
            $table->dropColumn('raw_merchant_descriptor');
        });
    }
};
