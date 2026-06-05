<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_walkthroughs', function (Blueprint $table) {
            $table->string('feature_key', 120)->nullable()->after('module_slug')->index();
            $table->boolean('auto_generated')->default(false)->after('feature_key')->index();
            $table->unsignedSmallInteger('documentation_version')->default(1)->after('auto_generated');
            $table->char('documentation_hash', 64)->nullable()->after('documentation_version');
            $table->timestamp('last_generated_at')->nullable()->after('documentation_hash');
            $table->timestamp('last_synced_at')->nullable()->after('last_generated_at');
            $table->boolean('needs_regeneration')->default(false)->after('last_synced_at')->index();
        });

        Schema::table('edu_learning_paths', function (Blueprint $table) {
            $table->string('feature_key', 120)->nullable()->after('module_slug')->index();
            $table->boolean('auto_generated')->default(false)->after('feature_key')->index();
            $table->unsignedSmallInteger('documentation_version')->default(1)->after('auto_generated');
            $table->char('documentation_hash', 64)->nullable()->after('documentation_version');
            $table->timestamp('last_generated_at')->nullable()->after('documentation_hash');
            $table->timestamp('last_synced_at')->nullable()->after('last_generated_at');
            $table->boolean('needs_regeneration')->default(false)->after('last_synced_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('edu_walkthroughs', function (Blueprint $table) {
            $table->dropColumn([
                'feature_key', 'auto_generated', 'documentation_version',
                'documentation_hash', 'last_generated_at', 'last_synced_at', 'needs_regeneration',
            ]);
        });

        Schema::table('edu_learning_paths', function (Blueprint $table) {
            $table->dropColumn([
                'feature_key', 'auto_generated', 'documentation_version',
                'documentation_hash', 'last_generated_at', 'last_synced_at', 'needs_regeneration',
            ]);
        });
    }
};
