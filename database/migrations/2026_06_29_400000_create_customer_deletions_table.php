<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_deletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installation_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->unsignedInteger('initiated_by');
            $table->string('policy', 50)->default('never_delete_backup');
            $table->string('status', 30)->default('pending');
            $table->string('current_stage', 100)->nullable();
            $table->json('completed_stages')->nullable();
            $table->json('audit_snapshot')->nullable();
            $table->json('verify_report')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('installation_id')
                  ->references('id')
                  ->on('customer_installations')
                  ->onDelete('restrict');

            $table->index('installation_id');
            $table->index('status');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_deletions');
    }
};
