<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_uuid', 36)->unique();
            $table->string('trace_id', 36)->nullable()->index();
            $table->string('request_id', 36)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('module', 64)->index();
            $table->string('environment', 32);
            $table->string('release_id', 36)->nullable()->index();
            $table->string('severity', 16)->index();
            $table->string('event', 128)->index();
            $table->json('runtime_context')->nullable();
            $table->json('data')->nullable();
            $table->string('timestamp', 32);
            $table->timestamps();

            $table->index(['tenant_id', 'severity']);
            $table->index(['module', 'severity']);
            $table->index(['severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_events');
    }
};
