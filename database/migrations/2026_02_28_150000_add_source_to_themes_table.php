<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('themes', 'source')) {
            Schema::table('themes', function (Blueprint $table) {
                $table->string('source', 32)->nullable()->after('type')
                    ->comment('manual=user-created, generator=theme generator, seeder=legacy seeder');
                $table->index('source');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('themes', 'source')) {
            Schema::table('themes', function (Blueprint $table) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            });
        }
    }
};
