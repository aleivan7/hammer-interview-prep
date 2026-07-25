<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('source');
            $table->string('bucket')->nullable();
            $table->string('subcategory')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->text('explanation')->nullable();
            $table->json('previous_state')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_audits');
    }
};
