<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_automations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('form_id');
            $table->string('name');
            $table->string('type'); // email, webhook, slack, telegram, autoresponder
            $table->json('config');
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('form_id')->references('id')->on('forms')->onDelete('cascade');
            $table->index(['form_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_automations');
    }
};
