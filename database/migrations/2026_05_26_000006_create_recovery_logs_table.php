<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recovery_id', 36)->index();
            $table->string('strategy', 100)->index();
            $table->string('status', 30)->index();
            $table->string('component', 100)->nullable()->index();
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('executed_at')->index();
            $table->timestamps();

            $table->index(['strategy', 'status']);
            $table->index(['status', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_logs');
    }
};
