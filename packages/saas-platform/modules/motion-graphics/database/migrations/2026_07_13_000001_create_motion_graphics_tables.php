<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motion_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique('motion_groups_slug_unique');
            $table->string('ownership_type', 24)->default('global');
            $table->string('owner_site_identifier', 191)->nullable();
            $table->json('search_metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['ownership_type', 'owner_site_identifier'], 'motion_groups_owner_index');
        });

        Schema::create('motion_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('motion_group_id')->constrained('motion_groups')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['motion_group_id', 'slug'], 'motion_categories_group_slug_unique');
        });

        Schema::create('motion_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique('motion_tags_slug_unique');
            $table->timestamps();
        });

        Schema::create('motion_graphics', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('motion_graphics_uuid_unique');
            $table->string('name', 160);
            $table->string('slug', 160)->unique('motion_graphics_slug_unique');
            $table->text('description')->nullable();
            $table->string('ownership_type', 24);
            $table->string('owner_site_identifier', 191)->nullable();
            $table->foreignId('group_id')->constrained('motion_groups')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('motion_categories')->nullOnDelete();
            $table->string('engine', 32);
            $table->string('asset_type', 32);
            $table->longText('source_definition_json');
            $table->longText('editable_schema_json');
            $table->longText('ai_metadata_json')->nullable();
            $table->json('runtime_configuration')->nullable();
            $table->json('dimensions')->nullable();
            $table->json('accessibility')->nullable();
            $table->unsignedInteger('latest_working_version')->default(1);
            $table->unsignedInteger('approved_version')->nullable();
            $table->unsignedInteger('published_version')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('created_by_actor_identifier', 191);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['ownership_type', 'owner_site_identifier'], 'motion_graphics_owner_index');
            $table->index(['group_id', 'category_id', 'status'], 'motion_graphics_class_status_index');
        });

        Schema::create('motion_graphic_tag', function (Blueprint $table): void {
            $table->foreignId('motion_graphic_id')->constrained('motion_graphics')->cascadeOnDelete();
            $table->foreignId('motion_tag_id')->constrained('motion_tags')->cascadeOnDelete();
            $table->primary(['motion_graphic_id', 'motion_tag_id'], 'motion_graphic_tag_primary');
            $table->index(['motion_tag_id', 'motion_graphic_id'], 'motion_graphic_tag_reverse_index');
        });

        Schema::create('motion_graphic_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('motion_graphic_id')->constrained('motion_graphics')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('parent_version')->nullable();
            $table->longText('source_snapshot');
            $table->longText('editable_schema_snapshot');
            $table->longText('ai_metadata_json')->nullable();
            $table->json('compiled_assets');
            $table->json('preview_metadata')->nullable();
            $table->char('source_checksum', 64);
            $table->string('status', 24)->default('draft');
            $table->text('change_description');
            $table->string('created_by_actor_identifier', 191);
            $table->string('approved_by_actor_identifier', 191)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('published_by_actor_identifier', 191)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['motion_graphic_id', 'version'], 'motion_versions_motion_version_unique');
            $table->index(['motion_graphic_id', 'parent_version'], 'motion_versions_parent_index');
            $table->index(['motion_graphic_id', 'status'], 'motion_versions_status_index');
        });

        Schema::create('motion_graphic_deployments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('motion_graphic_id')->constrained('motion_graphics')->cascadeOnDelete();
            $table->string('external_site_identifier', 191);
            $table->string('page_identifier', 120);
            $table->string('section_identifier', 120)->nullable();
            $table->string('placement_identifier', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('follow_latest_published')->default(true);
            $table->unsignedInteger('selected_version')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['motion_graphic_id', 'external_site_identifier', 'page_identifier', 'placement_identifier'],
                'motion_deployments_scope_unique'
            );
            $table->index(
                ['external_site_identifier', 'page_identifier', 'is_enabled', 'sort_order'],
                'motion_deployments_runtime_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motion_graphic_deployments');
        Schema::dropIfExists('motion_graphic_versions');
        Schema::dropIfExists('motion_graphic_tag');
        Schema::dropIfExists('motion_graphics');
        Schema::dropIfExists('motion_tags');
        Schema::dropIfExists('motion_categories');
        Schema::dropIfExists('motion_groups');
    }
};
