<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('base_url', 512)->comment('HTTPS endpoint of the agent on tenant server');
            $table->string('secret_key', 128)->comment('HMAC-SHA256 shared secret (stored encrypted)');
            $table->string('environment', 32)->default('production'); // production | staging
            $table->boolean('is_active')->default(true)->index();
            $table->string('last_health_status', 32)->nullable(); // healthy | degraded | offline | unknown
            $table->timestamp('last_health_checked_at')->nullable();
            $table->json('last_health_data')->nullable();
            $table->unsignedBigInteger('server_id')->nullable()->index(); // FK to infra_servers (optional)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_health_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_agents');
    }
};
