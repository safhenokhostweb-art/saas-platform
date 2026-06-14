<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_site_health_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('domain')->nullable();
            $table->string('status', 20)->default('unknown');           // healthy / warning / critical / unknown
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('ssl_status', 20)->nullable();               // valid / expiring_soon / critical / invalid / unknown
            $table->date('ssl_expires_at')->nullable();
            $table->smallInteger('ssl_days_remaining')->nullable();
            $table->string('db_status', 20)->nullable();                // ok / error / unknown
            $table->string('redis_status', 20)->nullable();             // ok / error / unknown
            $table->string('queue_status', 20)->nullable();             // ok / idle / error / unknown
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index('status');
            $table->index('checked_at');
            $table->index(['site_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_site_health_logs');
    }
};
