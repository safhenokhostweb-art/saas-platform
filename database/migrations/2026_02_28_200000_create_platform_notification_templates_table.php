<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('type')->default('info');
            $table->string('link')->nullable();
            $table->string('link_text')->nullable();
            $table->string('image_path')->nullable();
            $table->string('position')->default('top');
            $table->string('image_position')->default('right');
            $table->string('audience')->default('admin'); // admin | public
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notification_templates');
    }
};
