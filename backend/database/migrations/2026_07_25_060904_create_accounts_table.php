<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name');
            $table->string('name');
            $table->string('mask', 4)->nullable();
            $table->string('type');
            $table->integer('balance_cents')->default(0);
            $table->string('sync_status')->default('healthy');
            $table->string('logo_key')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
