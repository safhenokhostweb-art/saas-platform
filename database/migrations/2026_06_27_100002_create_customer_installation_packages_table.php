<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_installation_packages', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->string('arvan_key');
            $table->string('sha256', 64);
            $table->string('manifest_checksum', 64)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('released_at');
            $table->boolean('is_active')->default(false);
            $table->text('release_notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('released_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_installation_packages');
    }
};
