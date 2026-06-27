<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_installation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installation_id');
            $table->string('stage', 60);
            $table->enum('event', ['started', 'completed', 'failed', 'rolled_back', 'checkpoint', 'warning']);
            $table->text('message');
            $table->json('output')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('installation_id');
            $table->index(['installation_id', 'stage']);
            $table->index('event');

            $table->foreign('installation_id')
                  ->references('id')
                  ->on('customer_installations')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_installation_logs');
    }
};
