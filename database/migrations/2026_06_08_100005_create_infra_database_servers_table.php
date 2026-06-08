<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_database_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // descriptive label
            $table->foreignId('server_id')->nullable()->constrained('infra_servers')->nullOnDelete();
            $table->string('engine'); // mysql, pgsql, sqlite, redis, mongodb
            $table->string('version')->nullable(); // 8.0.35, 16.2, etc.
            $table->string('host')->nullable(); // actual connection host (not hardcoded)
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('environment')->default('production');
            $table->bigInteger('size_mb')->nullable();
            $table->bigInteger('size_mb_prev_month')->nullable(); // growth tracking
            $table->integer('active_connections')->nullable();
            $table->integer('max_connections')->nullable();
            $table->string('status')->default('online'); // online, offline, degraded, maintenance
            $table->timestamp('last_backup_at')->nullable();
            $table->string('backup_storage')->nullable(); // where backups go
            $table->string('backup_frequency')->nullable(); // daily, hourly, etc.
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('replication_enabled')->default(false);
            $table->string('replication_role')->nullable(); // primary, replica
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('engine');
            $table->index('status');
            $table->index('environment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_database_servers');
    }
};
