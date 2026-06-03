<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_release_notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->string('version_number', 30);
            $table->string('release_type', 20)->default('feature'); // feature | fix | improvement | breaking | security
            $table->string('module_slug', 60)->nullable();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            // Links to the specific article revision that introduced this change (Correction 5)
            $table->unsignedBigInteger('revision_id')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('version_number');
            $table->index('module_slug');
            $table->index(['site_id', 'is_published']);
            $table->index('revision_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_release_notes');
    }
};
