<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_captcha_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('captcha_enabled')->default(false);
            $table->string('captcha_type', 30)->default('persian_number'); // persian_number, normal, hcaptcha
            $table->string('hcaptcha_site_key')->nullable();
            $table->string('hcaptcha_secret_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_captcha_settings');
    }
};
