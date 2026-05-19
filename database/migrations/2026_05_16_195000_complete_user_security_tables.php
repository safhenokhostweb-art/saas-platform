<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Complete user_groups table (add missing columns) ---
        Schema::table('user_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('user_groups', 'name')) {
                $table->string('name', 100)->unique();
                $table->string('slug', 120)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->softDeletes();
            }
        });

        // --- Complete password_resets table ---
        Schema::table('password_resets', function (Blueprint $table) {
            if (!Schema::hasColumn('password_resets', 'token')) {
                $table->string('mobile', 15);
                $table->string('token', 100);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('used_at')->nullable();
            }
        });

        // --- Complete login_attempts table ---
        Schema::table('login_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('login_attempts', 'mobile')) {
                $table->string('mobile', 15)->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->boolean('is_successful')->default(false);
                $table->string('failure_reason', 50)->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        // --- Create user_group_user pivot table ---
        if (!Schema::hasTable('user_group_user')) {
            Schema::create('user_group_user', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['user_id', 'user_group_id']);
            });
        }

        // --- Create taggables polymorphic pivot table ---
        if (!Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table) {
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->morphs('taggable');
                $table->timestamps();
                $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
            });
        }
    }

    public function down(): void
    {
        // Only drop tables we created; leave column drops for original migrations
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('user_group_user');
    }
};