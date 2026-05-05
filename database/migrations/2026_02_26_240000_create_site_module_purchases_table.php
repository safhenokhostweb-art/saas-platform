<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ردیابی خرید ماژول‌های PAID برای هر سایت.
 * خرید یک‌باره (one-time)؛ هر خرید یک رکورد.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_module_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->unsignedBigInteger('amount_irr')->default(0);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('purchased_at');
            $table->timestamps();

            $table->unique(['site_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_module_purchases');
    }
};
