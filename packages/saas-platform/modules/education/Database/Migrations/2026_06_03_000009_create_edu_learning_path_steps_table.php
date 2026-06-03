<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_learning_path_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edu_learning_path_id')->constrained('edu_learning_paths')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number')->default(1);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('step_type', 30)->default('article'); // article | walkthrough | task | external_link
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['edu_learning_path_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_learning_path_steps');
    }
};
