<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motion_graphics', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->longText('source_definition_json');
            $table->longText('editable_schema_json');
            $table->longText('ai_metadata_json')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by_platform_admin')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('motion_graphic_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('motion_graphic_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('source_snapshot');
            $table->json('compiled_assets');
            $table->string('status', 24)->default('draft');
            $table->text('change_description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['motion_graphic_id', 'version']);
        });

        Schema::create('motion_graphic_deployments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('motion_graphic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
            $table->unique(['motion_graphic_id', 'site_id']);
            // Explicit short name — the default auto-generated name
            // (motion_graphic_deployments_site_id_motion_graphic_id_version_index,
            // 68 chars) exceeds MySQL's 64-character identifier limit.
            $table->index(['site_id', 'motion_graphic_id', 'version'], 'motion_graphic_deployments_site_mg_version_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motion_graphic_deployments');
        Schema::dropIfExists('motion_graphic_versions');
        Schema::dropIfExists('motion_graphics');
    }
};
