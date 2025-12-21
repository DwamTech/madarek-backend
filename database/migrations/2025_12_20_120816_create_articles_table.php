<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->foreignId('issue_id')->constrained('issues')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('author_name');
            $table->string('featured_image')->nullable();
            $table->date('gregorian_date')->nullable();
            $table->string('hijri_date')->nullable();
            $table->text('references')->nullable();
            $table->enum('status', [
                env('ARTICLE_STATUS_DRAFT', 'draft'),
                env('ARTICLE_STATUS_PUBLISHED', 'published'),
                env('ARTICLE_STATUS_ARCHIVED', 'archived'),
            ])->default(env('ARTICLE_STATUS_DRAFT', 'draft'));
            $table->timestamp('published_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
