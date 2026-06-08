<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('ipv6_address')->nullable();
            $table->string('environment')->default('production'); // production, staging, development
            $table->json('roles')->nullable(); // ['application','database','storage','cache','queue']
            $table->string('provider')->nullable(); // Hetzner, DigitalOcean, OVH, etc.
            $table->string('datacenter')->nullable(); // FSN1, AMS3, etc.
            $table->integer('cpu_cores')->nullable();
            $table->integer('ram_gb')->nullable();
            $table->integer('disk_gb')->nullable();
            $table->decimal('monthly_cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('IRR');
            $table->string('os')->nullable(); // Ubuntu 22.04, Debian 12, etc.
            $table->string('status')->default('unknown'); // online, offline, degraded, maintenance, unknown
            $table->timestamp('last_checked_at')->nullable();
            $table->integer('uptime_percent')->nullable(); // 0-100
            $table->timestamp('last_backup_at')->nullable();
            $table->timestamp('last_deployment_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('environment');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_servers');
    }
};
