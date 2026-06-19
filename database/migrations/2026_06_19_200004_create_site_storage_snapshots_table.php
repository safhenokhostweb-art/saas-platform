<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_storage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->date('snapshot_date');

            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->unsignedBigInteger('db_bytes')->default(0);
            $table->unsignedBigInteger('backup_bytes')->default(0);
            $table->unsignedBigInteger('archive_bytes')->default(0);
            $table->unsignedInteger('file_count')->default(0);

            $table->timestamps();

            $table->unique(['site_id', 'snapshot_date']);
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_storage_snapshots');
    }
};
