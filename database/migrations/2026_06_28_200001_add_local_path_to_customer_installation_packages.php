<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_installation_packages', function (Blueprint $table) {
            $table->string('local_path')->nullable()->after('arvan_key')
                ->comment('Absolute path on the platform server when not using Arvan cloud');
        });
    }

    public function down(): void
    {
        Schema::table('customer_installation_packages', function (Blueprint $table) {
            $table->dropColumn('local_path');
        });
    }
};
