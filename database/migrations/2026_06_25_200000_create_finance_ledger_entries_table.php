<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_ledger_entries', function (Blueprint $table) {
            $table->id();

            // Core classification
            $table->enum('type', ['income', 'expense'])->index();
            $table->string('category', 50)->index();           // theme_sale, bundle_sale, hosting_renewal, ai_cost, …
            $table->enum('source', ['automatic', 'manual'])->default('automatic');

            // Financial data — all amounts in IRR (Rial)
            $table->unsignedBigInteger('amount');
            $table->text('description')->nullable();

            // Source reference for automatic entries (polymorphic-style)
            $table->string('reference_type', 100)->nullable()->index();  // 'theme_purchase_orders', …
            $table->unsignedBigInteger('reference_id')->nullable()->index();

            // Optional links
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_ref', 255)->nullable();
            $table->string('invoice_number', 100)->nullable();

            // Manual entry extras
            $table->string('attachment_path', 500)->nullable();
            $table->text('notes')->nullable();

            // Timing
            $table->date('entry_date')->index();
            $table->timestamp('transaction_at')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();

            // Prevent double-recording of the same source record
            $table->unique(['reference_type', 'reference_id'], 'finance_ledger_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_ledger_entries');
    }
};
