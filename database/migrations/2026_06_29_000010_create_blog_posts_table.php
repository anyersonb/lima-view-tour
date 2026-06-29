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
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('author_name')->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('reading_minutes')->nullable();

            // Translatable content — Spanish
            $table->string('title_es');
            $table->text('excerpt_es');
            $table->longText('body_es');

            // Translatable content — English
            $table->string('title_en');
            $table->text('excerpt_en');
            $table->longText('body_en');

            // Translatable content — Portuguese
            $table->string('title_pt');
            $table->text('excerpt_pt');
            $table->longText('body_pt');

            // SEO meta — Spanish
            $table->string('meta_title_es')->nullable();
            $table->text('meta_description_es')->nullable();

            // SEO meta — English
            $table->string('meta_title_en')->nullable();
            $table->text('meta_description_en')->nullable();

            // SEO meta — Portuguese
            $table->string('meta_title_pt')->nullable();
            $table->text('meta_description_pt')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['is_published', 'published_at']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
