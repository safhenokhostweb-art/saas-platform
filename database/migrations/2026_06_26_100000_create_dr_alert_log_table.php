<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dr_alert_log', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type', 20);
            $table->string('severity', 10);
            $table->text('message');
            $table->json('context')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['alert_type', 'resolved']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dr_alert_log');
    }
};
