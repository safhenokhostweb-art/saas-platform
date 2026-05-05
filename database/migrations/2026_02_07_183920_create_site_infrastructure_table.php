<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_infrastructure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('hosting_company')->nullable();
            $table->string('hosting_plan')->nullable();
            $table->string('hosting_url')->nullable();
            $table->string('domain_provider')->nullable();
            $table->string('database_name')->nullable();
            $table->string('database_username')->nullable();
            $table->text('database_password')->nullable();
            $table->string('server_ip')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_infrastructure');
    }
};
