<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_es');
            $table->string('title_en')->nullable();
            $table->longText('content_es')->nullable();
            $table->longText('content_en')->nullable();
            $table->string('hero_image')->nullable();
            $table->json('blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('seo_image')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_sitemap')->default(true);
            $table->string('sitemap_priority')->default('0.5');
            $table->string('sitemap_changefreq')->default('monthly');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
