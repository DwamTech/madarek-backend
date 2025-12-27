<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            try {
                $table->dropUnique('articles_slug_unique');
            } catch (\Throwable $e) {
            }

            try {
                $table->unique(['issue_id', 'slug'], 'articles_issue_id_slug_unique');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            try {
                $table->dropUnique('articles_issue_id_slug_unique');
            } catch (\Throwable $e) {
            }

            try {
                $table->unique('slug', 'articles_slug_unique');
            } catch (\Throwable $e) {
            }
        });
    }
};
