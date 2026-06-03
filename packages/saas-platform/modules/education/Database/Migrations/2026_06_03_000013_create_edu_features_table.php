<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('module_slug', 100)->index();
            $table->string('feature_slug', 100)->index();
            $table->string('feature_name', 255);
            $table->string('route_name', 255)->nullable()->index();
            $table->json('permissions_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();

            $table->unique(['module_slug', 'feature_slug', 'site_id'], 'edu_features_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_features');
    }
};
