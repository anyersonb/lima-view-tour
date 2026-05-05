<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title_es');
            $table->string('title_en')->nullable();
            $table->string('subtitle_es')->nullable();
            $table->string('subtitle_en')->nullable();

            $table->text('description_es')->nullable();
            $table->text('description_en')->nullable();

            $table->json('itinerary_es')->nullable();
            $table->json('itinerary_en')->nullable();
            $table->json('includes_es')->nullable();
            $table->json('includes_en')->nullable();
            $table->json('excludes_es')->nullable();
            $table->json('excludes_en')->nullable();
            $table->text('recommendations_es')->nullable();
            $table->text('recommendations_en')->nullable();
            $table->text('notes_es')->nullable();
            $table->text('notes_en')->nullable();

            $table->decimal('price', 10, 2);
            $table->decimal('price_before', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            $table->string('duration')->nullable();
            $table->string('language')->default('Español / Inglés');
            $table->string('group_type')->default('Grupal');
            $table->string('departure_time')->nullable();
            $table->string('return_time')->nullable();
            $table->unsignedInteger('max_capacity')->nullable();

            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();

            $table->string('badge_text')->nullable();
            $table->string('badge_type')->nullable();

            $table->decimal('rating', 3, 1)->default(4.8);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('order')->default(0);

            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('seo_image')->nullable();
            $table->json('seo_keywords')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'is_featured', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
