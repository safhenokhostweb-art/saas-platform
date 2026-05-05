<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name_fa');
            $table->string('name_en')->nullable();
            $table->string('type', 32); // license_template | module_addon | support_plan
            $table->unsignedBigInteger('price_irr')->default(0);
            $table->boolean('is_one_time')->default(true);
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('support_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
