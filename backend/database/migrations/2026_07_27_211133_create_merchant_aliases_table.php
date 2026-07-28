<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');
            $table->string('normalized_pattern');
            $table->string('match_strategy');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['merchant_id', 'normalized_pattern', 'match_strategy'],
                'merchant_aliases_merchant_pattern_strategy_unique',
            );
            $table->index(['enabled', 'match_strategy', 'priority']);
            $table->index('normalized_pattern');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_aliases');
    }
};
