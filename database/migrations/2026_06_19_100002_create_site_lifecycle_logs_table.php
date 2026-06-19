<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_lifecycle_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->boolean('is_system')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->index(['site_id', 'created_at'], 'lifecycle_logs_site_date_idx');
            $table->index('to_status', 'lifecycle_logs_to_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_lifecycle_logs');
    }
};
