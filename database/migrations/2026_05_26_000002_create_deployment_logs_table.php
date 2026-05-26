<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('deployment_uuid', 36)->index();
            $table->string('phase', 50);
            $table->string('status', 20)->comment('started, completed, failed, skipped, rolled_back');
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['deployment_uuid', 'phase']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_logs');
    }
};
