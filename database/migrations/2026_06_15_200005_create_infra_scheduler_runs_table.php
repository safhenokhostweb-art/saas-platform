<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_scheduler_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 200)->index();
            $table->string('status', 20)->default('running'); // running / success / failed / timeout
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('started_at');
            $table->index(['command', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_scheduler_runs');
    }
};
