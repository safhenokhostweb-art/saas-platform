<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('type', 32)->default('direct');   // direct|group|support|ticket_linked
            $table->string('status', 32)->default('active'); // active|archived|closed
            $table->string('title', 255)->nullable();
            $table->uuid('created_by');
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
            $table->index('last_message_at');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_conversations');
    }
};
