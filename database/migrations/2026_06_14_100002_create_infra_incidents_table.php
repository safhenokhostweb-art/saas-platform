<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('severity', 20)->default('warning');         // warning / critical
            $table->string('type', 60)->default('general');             // site_down / db_error / redis_error / queue_dead / ssl_expiring / deploy_failure
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();               // throttle: one SMS per 30 min per incident
            $table->timestamps();

            $table->index('severity');
            $table->index('resolved_at');
            $table->index(['site_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_incidents');
    }
};
