<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_support_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_support_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_feature_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_at_purchase')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_support_features');
    }
};
