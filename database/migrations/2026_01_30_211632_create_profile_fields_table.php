<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profile_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('type')->default('text');   // اضافه شد
            $table->boolean('enabled')->default(true);
            $table->boolean('required')->default(false);
            $table->integer('position')->default(0);   // اضافه شد
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_fields');
    }
};
