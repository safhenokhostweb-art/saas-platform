<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canvas_asset_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('asset_kind', 32);
            $table->string('asset_key', 100);
            $table->string('asset_version', 32)->default('1.0.0');
            $table->unsignedBigInteger('amount_toman')->default(0);
            $table->unsignedBigInteger('amount_rial')->default(0);
            $table->string('status', 20)->default('pending'); // pending, paid, failed, cancelled
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_authority', 100)->nullable();
            $table->string('gateway_ref', 100)->nullable();
            $table->string('redirect_back_url', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'asset_kind', 'asset_key'], 'capo_site_asset_idx');
            $table->index('status', 'capo_status_idx');
            $table->index('gateway_authority', 'capo_authority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canvas_asset_purchase_orders');
    }
};
