<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لایسنس ماژول برای هر tenant (سایت).
 * ماژول فقط در صورت داشتن لایسنس معتبر + چک‌باکس فعال، فعال محسوب می‌شود.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('module_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('license_type', 32)->default('one_time'); // one_time | subscription
            $table->timestamp('purchased_at');
            $table->timestamp('expires_at')->nullable(); // null = perpetual (one-time)
            $table->string('status', 32)->default('active'); // active | expired | revoked
            $table->boolean('support_eligibility')->default(true);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->foreignId('module_purchase_id')->nullable()->constrained('site_module_purchases')->nullOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'module_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_licenses');
    }
};
