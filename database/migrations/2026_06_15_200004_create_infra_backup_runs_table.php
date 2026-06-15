<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('backup_type', 30)->default('database'); // database / files
            $table->unsignedBigInteger('site_id')->nullable();      // NULL = platform backup
            $table->string('status', 20)->default('running');       // running / success / failed
            $table->string('file_path', 500)->nullable();
            $table->decimal('file_size_mb', 10, 2)->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('site_id');
            $table->index('started_at');
            $table->index(['backup_type', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_backup_runs');
    }
};
