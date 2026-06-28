<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_remote_servers', function (Blueprint $table) {
            $table->unsignedBigInteger('ssh_key_id')->nullable()->after('key_fingerprint');
            $table->timestamp('key_installed_at')->nullable()->after('ssh_key_id');
            $table->timestamp('api_token_created_at')->nullable()->after('key_installed_at');
            $table->timestamp('api_token_last_used_at')->nullable()->after('api_token_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_remote_servers', function (Blueprint $table) {
            $table->dropColumn(['ssh_key_id', 'key_installed_at', 'api_token_created_at', 'api_token_last_used_at']);
        });
    }
};
