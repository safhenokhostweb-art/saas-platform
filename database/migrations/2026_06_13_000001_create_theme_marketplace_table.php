<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_marketplace', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('theme_id')->unique();
            $table->text('marketplace_description')->nullable();
            $table->json('screenshots')->nullable();
            $table->json('requirements')->nullable();
            $table->string('version_notes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sales_count')->default(0);
            $table->timestamps();

            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_marketplace');
    }
};
