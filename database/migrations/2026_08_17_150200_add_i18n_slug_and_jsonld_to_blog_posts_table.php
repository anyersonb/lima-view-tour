<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ampliación de alcance (2026-08-17): mismo criterio SEO i18n aplicado al
 * blog. A diferencia de tours/pages, `blog_posts` YA tenía
 * meta_title_es/en/pt y meta_description_es/en/pt desde la migración
 * 2026_06_29_000010 — no se duplican ni se tocan. Solo falta lo que no
 * existía: slug traducido por idioma y JSON-LD editable por idioma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('slug_en', 60)->nullable()->unique()->after('slug');
            $table->string('slug_pt', 60)->nullable()->unique()->after('slug_en');

            $table->text('schema_jsonld_es')->nullable()->after('meta_description_pt');
            $table->text('schema_jsonld_en')->nullable()->after('schema_jsonld_es');
            $table->text('schema_jsonld_pt')->nullable()->after('schema_jsonld_en');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['slug_en', 'slug_pt', 'schema_jsonld_es', 'schema_jsonld_en', 'schema_jsonld_pt']);
        });
    }
};
