<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_site_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('provisioned_by')->constrained('users');
            $table->string('mobile', 20);
            $table->string('agent_password_enc')->nullable();
            $table->unsignedBigInteger('customer_site_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('last_access_logged_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_site_agents');
    }
};
