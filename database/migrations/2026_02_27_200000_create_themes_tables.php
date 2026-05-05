<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('type', 32)->default('full'); // full, header, footer, layout
            $table->unsignedBigInteger('site_id')->nullable()->comment('null = platform template');
            $table->json('header_blocks')->nullable();
            $table->json('footer_blocks')->nullable();
            $table->json('global_styles')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('draft'); // draft, published
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            $table->index(['site_id', 'status']);
            $table->index('type');
        });

        Schema::create('theme_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('label', 100)->nullable();
            $table->json('snapshot');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['theme_id', 'version']);
        });

        if (!Schema::hasColumn('sites', 'theme_id')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sites', 'theme_id')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropForeign(['theme_id']);
            });
        }
        Schema::dropIfExists('theme_revisions');
        Schema::dropIfExists('themes');
    }
};
