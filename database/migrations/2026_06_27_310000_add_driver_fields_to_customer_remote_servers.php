<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * PHASE-003D.5 — Add driver-aware fields to customer_remote_servers.
 * Adds: api_token_encrypted, path, domain.
 * Expands: auth_method enum to include api_token values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_remote_servers', function (Blueprint $table) {
            $table->text('api_token_encrypted')->nullable()->after('password_encrypted');
            $table->string('path')->nullable()->after('username');    // web root on remote
            $table->string('domain')->nullable()->after('path');     // primary domain
        });

        // Expand auth_method enum to include api-token variants.
        // MySQL requires raw SQL to modify enum values.
        DB::statement("ALTER TABLE customer_remote_servers MODIFY auth_method ENUM('key','password','api_token','sftp_password') NOT NULL DEFAULT 'key'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE customer_remote_servers MODIFY auth_method ENUM('key','password') NOT NULL DEFAULT 'key'");

        Schema::table('customer_remote_servers', function (Blueprint $table) {
            $table->dropColumn(['api_token_encrypted', 'path', 'domain']);
        });
    }
};
