<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dr_simulations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('operator_name', 150);
            $table->enum('result', ['PASS', 'FAIL']);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('estimated_restore_time', 100)->nullable();
            $table->string('estimated_rpo', 100)->nullable();
            $table->json('components')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('result');
            $table->index('operator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dr_simulations');
    }
};
