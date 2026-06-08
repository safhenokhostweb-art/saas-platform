<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_storage_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Primary MinIO", "Backup S3"
            $table->string('driver'); // local, s3, minio, ftp, sftp
            $table->string('endpoint')->nullable(); // for MinIO / S3-compatible
            $table->string('bucket')->nullable();
            $table->string('region')->nullable();
            $table->string('disk_name')->nullable(); // Laravel disk name (filesystems.php)
            $table->string('purpose'); // primary, backup, cdn, tenant-uploads, exports
            $table->bigInteger('capacity_gb')->nullable();
            $table->bigInteger('used_gb')->nullable();
            $table->bigInteger('used_gb_prev_month')->nullable(); // for growth tracking
            $table->decimal('monthly_cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('IRR');
            $table->string('status')->default('active'); // active, degraded, full, inactive
            $table->timestamp('last_measured_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('driver');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_storage_providers');
    }
};
