<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_walkthroughs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('trigger_route', 120)->nullable();
            $table->string('trigger_selector', 120)->nullable();
            $table->string('trigger_condition', 20)->default('once'); // always | once | manual
            $table->json('target_roles')->nullable();
            $table->string('module_slug', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'is_active']);
            $table->index('trigger_route');
            $table->index('module_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_walkthroughs');
    }
};
