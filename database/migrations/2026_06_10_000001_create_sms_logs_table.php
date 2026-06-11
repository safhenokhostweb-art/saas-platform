<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();          // primary / secondary / failover
            $table->string('recipient', 20)->index();
            $table->text('message');
            $table->boolean('success')->default(false)->index();
            $table->string('error_message', 512)->nullable();
            $table->unsignedSmallInt('response_time_ms')->nullable();
            $table->timestamps();

            $table->index(['provider', 'success']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
