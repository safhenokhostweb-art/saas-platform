<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('theme_id');
            $table->unsignedBigInteger('amount_rial');
            $table->string('status', 20)->default('pending');
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_authority', 100)->nullable();
            $table->string('gateway_ref', 100)->nullable();
            $table->string('redirect_back_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'theme_id']);
            $table->index('status');
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_purchase_orders');
    }
};
