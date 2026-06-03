<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // site_id is REQUIRED — no cross-tenant bookmark leakage possible
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedBigInteger('bookmarkable_id');
            $table->string('bookmarkable_type', 80);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'site_id', 'bookmarkable_id', 'bookmarkable_type'], 'edu_bookmark_unique');
            $table->index(['user_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_bookmarks');
    }
};
