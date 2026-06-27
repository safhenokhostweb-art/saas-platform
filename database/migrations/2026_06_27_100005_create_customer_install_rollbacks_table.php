<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_install_rollbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installation_id');
            $table->string('stage', 60);
            $table->json('snapshot');
            $table->timestamp('executed_at')->nullable()->comment('Null = not yet rolled back');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['installation_id', 'stage']);
            $table->index('executed_at');

            $table->foreign('installation_id')
                  ->references('id')
                  ->on('customer_installations')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_install_rollbacks');
    }
};
