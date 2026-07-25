<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorization_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('merchant_contains');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('amount_cents_min')->nullable();
            $table->integer('amount_cents_max')->nullable();
            $table->string('target_bucket');
            $table->string('target_subcategory')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(true);
            $table->boolean('auto_review')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorization_rules');
    }
};
