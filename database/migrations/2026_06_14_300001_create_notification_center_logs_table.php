<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_center_logs', function (Blueprint $table) {
            $table->id();

            // Classification
            $table->string('type', 60)->default('system');
            // system / infrastructure / site_down / site_recovery / update / deploy
            // maintenance / purchase / renewal / support_ticket / custom

            $table->string('priority', 10)->default('medium');
            // critical / high / medium / low

            // Content
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->string('link', 500)->nullable();

            // Delivery
            $table->string('channel', 20)->default('in_app');
            // in_app / sms / both / silent

            // Audience
            $table->string('audience_type', 30)->default('platform_admin');
            // platform_admin / site_admin / all_site_admins / broadcast

            $table->unsignedBigInteger('site_id')->nullable();  // for site-specific
            $table->unsignedBigInteger('created_by')->nullable(); // admin user (null = system)

            // Delivery tracking
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('priority');
            $table->index('audience_type');
            $table->index('site_id');
            $table->index('read_at');
            $table->index(['audience_type', 'read_at']);
            $table->index(['priority', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_center_logs');
    }
};
