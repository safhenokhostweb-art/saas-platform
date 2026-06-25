<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_logs', function (Blueprint $table) {
            $table->id();
            $table->string('restore_type', 50); // tenant_database | tenant_storage | platform_database | platform_storage
            $table->unsignedBigInteger('site_id')->nullable();   // set for tenant-scoped restores
            $table->string('backup_file');                       // remote Arvan path
            $table->string('status', 30)->default('pending');   // pending | running | success | failed | dry_run_complete
            $table->boolean('dry_run')->default(false);
            $table->string('restore_token', 64)->unique()->nullable(); // Redis progress key suffix
            $table->text('validation_result')->nullable();       // JSON: {valid, checks, metadata}
            $table->longText('execution_log')->nullable();       // JSON array of progress events
            $table->string('rollback_snapshot_path')->nullable(); // /tmp/pre-restore-*.sql.gz
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['restore_type', 'status']);
            $table->index('site_id');
            $table->index('executed_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_logs');
    }
};
