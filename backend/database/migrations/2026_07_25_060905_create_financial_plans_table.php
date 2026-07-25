<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('needs_percent')->default(50);
            $table->unsignedTinyInteger('wants_percent')->default(30);
            $table->unsignedTinyInteger('savings_percent')->default(20);
            $table->integer('safety_buffer_cents')->default(0);
            $table->integer('monthly_income_cents')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_plans');
    }
};
