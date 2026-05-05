<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاریخچهٔ نسخه‌های ماژول برای patch-based updates.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('module_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('release_type', 16)->default('patch');
            $table->text('changelog')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['module_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_releases');
    }
};
