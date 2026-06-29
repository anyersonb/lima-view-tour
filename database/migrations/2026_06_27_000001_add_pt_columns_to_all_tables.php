<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── tours ─────────────────────────────────────────────────────────────
        Schema::table('tours', function (Blueprint $table) {
            $table->string('title_pt')->nullable()->after('title_en');
            $table->string('subtitle_pt')->nullable()->after('subtitle_en');
            $table->text('description_pt')->nullable()->after('description_en');
            $table->json('itinerary_pt')->nullable()->after('itinerary_en');
            $table->json('includes_pt')->nullable()->after('includes_en');
            $table->json('excludes_pt')->nullable()->after('excludes_en');
            $table->text('recommendations_pt')->nullable()->after('recommendations_en');
            $table->text('notes_pt')->nullable()->after('notes_en');
        });

        // ── testimonials ──────────────────────────────────────────────────────
        Schema::table('testimonials', function (Blueprint $table) {
            $table->text('quote_pt')->nullable()->after('quote_en');
        });

        // ── regions ───────────────────────────────────────────────────────────
        Schema::table('regions', function (Blueprint $table) {
            $table->string('name_pt')->nullable()->after('name_en');
            $table->text('description_pt')->nullable()->after('description_en');
            $table->string('eyebrow_pt')->nullable()->after('eyebrow_en');
        });

        // ── categories ────────────────────────────────────────────────────────
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_pt')->nullable()->after('name_en');
            $table->text('description_pt')->nullable()->after('description_en');
        });

        // ── pages ─────────────────────────────────────────────────────────────
        Schema::table('pages', function (Blueprint $table) {
            $table->string('title_pt')->nullable()->after('title_en');
            $table->longText('content_pt')->nullable()->after('content_en');
        });

        // ── offers ────────────────────────────────────────────────────────────
        Schema::table('offers', function (Blueprint $table) {
            $table->string('title_pt')->nullable()->after('title_en');
            $table->text('description_pt')->nullable()->after('description_en');
            $table->string('cta_label_pt')->nullable()->after('cta_label_en');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn([
                'title_pt', 'subtitle_pt', 'description_pt',
                'itinerary_pt', 'includes_pt', 'excludes_pt',
                'recommendations_pt', 'notes_pt',
            ]);
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('quote_pt');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['name_pt', 'description_pt', 'eyebrow_pt']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_pt', 'description_pt']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['title_pt', 'content_pt']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['title_pt', 'description_pt', 'cta_label_pt']);
        });
    }
};
