<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_id', 36)->unique();
            $table->string('type', 100)->index();
            $table->string('severity', 30)->index();
            $table->string('status', 30)->index();
            $table->json('context')->nullable();
            $table->text('resolution')->nullable();
            $table->boolean('auto_resolved')->default(false);
            $table->string('escalation_reason')->nullable();
            $table->timestamp('opened_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['severity', 'status']);
            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_incidents');
    }
};
