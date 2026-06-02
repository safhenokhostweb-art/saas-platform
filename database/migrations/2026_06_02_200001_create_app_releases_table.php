<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);           // android | windows | macos | linux
            $table->string('version', 30);             // e.g. 1.0.0
            $table->unsignedInteger('build_number')->default(1);
            $table->string('file_path');               // private disk path
            $table->unsignedBigInteger('file_size');   // bytes
            $table->string('file_name');               // original file name
            $table->string('mime_type', 100);
            $table->text('release_notes')->nullable();
            $table->boolean('is_latest')->default(false);
            $table->unsignedInteger('download_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['platform', 'is_latest']);
            $table->index(['platform', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
