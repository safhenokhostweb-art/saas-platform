<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expiry_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('type', 20); // support | host | domain
            $table->timestamp('sent_at');
            $table->timestamps();
        });
        Schema::table('expiry_reminder_logs', function (Blueprint $table) {
            $table->index(['site_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_reminder_logs');
    }
};
