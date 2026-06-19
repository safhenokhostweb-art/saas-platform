<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_bandwidth_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');  // 1–12

            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('request_count')->default(0);

            $table->timestamps();

            $table->unique(['site_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_bandwidth_usage');
    }
};
