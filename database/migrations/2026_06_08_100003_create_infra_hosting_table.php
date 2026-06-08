<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_hosting', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // descriptive name for this hosting account
            $table->foreignId('server_id')->nullable()->constrained('infra_servers')->nullOnDelete();
            $table->string('provider'); // Hetzner, DigitalOcean, OVH, ArvanCloud, etc.
            $table->string('plan')->nullable(); // CX31, Basic Droplet, etc.
            $table->string('account_email')->nullable();
            $table->string('panel_url')->nullable();
            $table->string('environment')->default('production'); // production, staging, development
            $table->integer('cpu_cores_allocated')->nullable();
            $table->integer('ram_gb_allocated')->nullable();
            $table->integer('disk_gb_allocated')->nullable();
            $table->integer('bandwidth_gb_allocated')->nullable();
            $table->decimal('monthly_cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('IRR');
            $table->date('billing_cycle_start')->nullable();
            $table->date('billing_cycle_end')->nullable();
            $table->date('next_renewal_at')->nullable();
            $table->string('status')->default('active'); // active, suspended, cancelled, pending
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider');
            $table->index('status');
            $table->index('next_renewal_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_hosting');
    }
};
