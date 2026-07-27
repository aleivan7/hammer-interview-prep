<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->after('merchant_contains')->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->after('target_subcategory')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
