<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 64)->index(); // auth.login, customer.created, site.updated, ...
            $table->string('description')->nullable(); // توضیح قابل خواندن
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 128)->nullable(); // App\Models\Customer, ...
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable(); // داده اضافی (قدیم/جدید، IP و...)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
