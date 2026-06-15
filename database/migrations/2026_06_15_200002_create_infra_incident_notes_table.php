<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_incident_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('note');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('infra_incidents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_incident_notes');
    }
};
