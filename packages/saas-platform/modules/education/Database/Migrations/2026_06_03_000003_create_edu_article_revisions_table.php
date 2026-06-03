<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edu_article_id')->constrained('edu_articles')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->string('change_summary', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['edu_article_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_article_revisions');
    }
};
