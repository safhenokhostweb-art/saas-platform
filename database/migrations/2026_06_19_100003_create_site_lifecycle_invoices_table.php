<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_lifecycle_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('invoice_number', 50)->unique();
            $table->string('type', 30);
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 200)->nullable();
            $table->json('line_items')->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->index(['site_id', 'status'], 'lifecycle_invoices_site_status_idx');
            $table->index('status', 'lifecycle_invoices_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_lifecycle_invoices');
    }
};
