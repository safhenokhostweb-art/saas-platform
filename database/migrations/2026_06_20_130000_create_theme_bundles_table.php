<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->json('theme_ids');                        // array of theme IDs in bundle
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        // Track bundle purchases (so we know which sites bought which bundle)
        Schema::create('theme_bundle_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->unsignedBigInteger('bundle_id')->index();
            $table->unsignedInteger('amount_rial')->default(0);
            $table->string('status', 20)->default('pending');  // pending, paid, failed
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_authority', 100)->nullable();
            $table->string('redirect_back_url', 500)->nullable();
            $table->timestamps();
            $table->foreign('bundle_id')->references('id')->on('theme_bundles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_bundle_orders');
        Schema::dropIfExists('theme_bundles');
    }
};
