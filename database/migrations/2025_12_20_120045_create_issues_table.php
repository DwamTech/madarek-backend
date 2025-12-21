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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->integer('issue_number');
            $table->string('cover_image')->nullable();
            $table->string('cover_image_alt')->nullable();
            $table->string('pdf_file')->nullable();
            $table->string('hijri_date')->nullable();
            $table->string('gregorian_date')->nullable();
            $table->integer('views_count')->default(0);
            $table->enum('status', [
                env('ISSUE_STATUS_DRAFT', 'draft'),
                env('ISSUE_STATUS_PUBLISHED', 'published'),
                env('ISSUE_STATUS_ARCHIVED', 'archived'),
            ])->default(env('ISSUE_STATUS_DRAFT', 'draft'));
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
