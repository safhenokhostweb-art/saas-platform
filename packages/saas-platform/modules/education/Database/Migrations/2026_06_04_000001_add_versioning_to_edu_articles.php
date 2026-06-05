<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_articles', function (Blueprint $table) {
            $table->string('feature_key', 120)->nullable()->after('route_pattern')->index();
            $table->unsignedSmallInteger('documentation_version')->default(1)->after('feature_key');
            $table->char('documentation_hash', 64)->nullable()->after('documentation_version')->index();
            $table->timestamp('last_generated_at')->nullable()->after('documentation_hash');
            $table->timestamp('last_synced_at')->nullable()->after('last_generated_at');
            $table->boolean('needs_regeneration')->default(false)->after('last_synced_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('edu_articles', function (Blueprint $table) {
            $table->dropIndex(['feature_key']);
            $table->dropIndex(['documentation_hash']);
            $table->dropIndex(['needs_regeneration']);
            $table->dropColumn([
                'feature_key', 'documentation_version', 'documentation_hash',
                'last_generated_at', 'last_synced_at', 'needs_regeneration',
            ]);
        });
    }
};
