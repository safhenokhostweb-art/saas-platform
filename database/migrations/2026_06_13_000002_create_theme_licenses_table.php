<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_licenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('theme_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->string('license_key', 64)->unique();
            $table->boolean('is_permanent')->default(true);
            $table->timestamp('purchased_at');
            $table->timestamps();

            $table->unique(['site_id', 'theme_id']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_licenses');
    }
};
