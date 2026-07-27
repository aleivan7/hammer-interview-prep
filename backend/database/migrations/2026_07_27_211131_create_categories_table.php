<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('bucket');
            $table->string('name');
            $table->string('normalized_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'bucket', 'archived_at']);
            $table->index(['bucket', 'sort_order', 'id']);
        });

        // SQLite treats NULL as distinct in unique indexes, so system and user
        // uniqueness must use partial indexes.
        DB::statement('CREATE UNIQUE INDEX categories_system_bucket_normalized_unique ON categories (bucket, normalized_name) WHERE user_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX categories_user_bucket_normalized_unique ON categories (user_id, bucket, normalized_name) WHERE user_id IS NOT NULL AND archived_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
