<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_walkthrough_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edu_walkthrough_id')->constrained('edu_walkthroughs')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number')->default(1);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('target_selector', 200)->nullable();
            $table->string('placement', 20)->default('bottom'); // top | bottom | left | right | center
            $table->string('action', 20)->default('next'); // click | input | observe | next
            $table->unsignedBigInteger('media_id')->nullable();
            $table->timestamps();

            $table->index(['edu_walkthrough_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_walkthrough_steps');
    }
};
