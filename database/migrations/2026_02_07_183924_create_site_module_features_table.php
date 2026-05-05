<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_module_features', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_module_id')
                ->constrained('site_modules')
                ->cascadeOnDelete();

            $table->foreignId('module_feature_id')
                ->constrained('module_features')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_module_features');
    }
};
