<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->bigInteger('price'); // ماهانه
            $table->integer('duration_days'); // مثلاً 30، 90
            $table->boolean('has_ticket')->default(true);
            $table->boolean('has_sms')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_plans');
    }
};
