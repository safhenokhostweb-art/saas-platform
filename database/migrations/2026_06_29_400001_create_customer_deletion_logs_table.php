<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deletion_id');
            $table->string('stage', 100);
            $table->string('event', 50); // started|completed|failed|skipped|verified
            $table->text('message');
            $table->json('output')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at');

            $table->foreign('deletion_id')
                  ->references('id')
                  ->on('customer_deletions')
                  ->onDelete('cascade');

            $table->index(['deletion_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_deletion_logs');
    }
};
