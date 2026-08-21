<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Propuesta técnica ESPASEO (2026-08-17): slugs traducidos por idioma + meta
 * por idioma + JSON-LD editable por idioma.
 *
 * IMPORTANTE: la columna `slug` existente NO se toca — sigue siendo el slug
 * en español, ya indexado y con enlaces compartidos. `slug_en`/`slug_pt` son
 * nuevas y nullable: si vienen vacías, el resolver de la app usa `slug` (ES)
 * como fallback, así que ningún tour queda en 404 por falta de traducción.
 *
 * Las columnas legacy `seo_title`/`seo_description` NO se eliminan (se
 * quedan huérfanas a propósito — limpieza posterior, ver reporte). Se
 * hace un backfill de una sola vía (ES) para no perder contenido ya cargado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('slug_en', 60)->nullable()->unique()->after('slug');
            $table->string('slug_pt', 60)->nullable()->unique()->after('slug_en');

            $table->string('meta_title_es', 70)->nullable()->after('seo_keywords');
            $table->string('meta_title_en', 70)->nullable()->after('meta_title_es');
            $table->string('meta_title_pt', 70)->nullable()->after('meta_title_en');

            $table->string('meta_description_es', 160)->nullable()->after('meta_title_pt');
            $table->string('meta_description_en', 160)->nullable()->after('meta_description_es');
            $table->string('meta_description_pt', 160)->nullable()->after('meta_description_en');

            $table->text('schema_jsonld_es')->nullable()->after('meta_description_pt');
            $table->text('schema_jsonld_en')->nullable()->after('schema_jsonld_es');
            $table->text('schema_jsonld_pt')->nullable()->after('schema_jsonld_en');
        });

        // Backfill: no perder lo ya cargado en el tab SEO global viejo.
        DB::table('tours')
            ->whereNotNull('seo_title')
            ->update(['meta_title_es' => DB::raw('SUBSTR(seo_title, 1, 70)')]);

        // SUBSTR (no LEFT) porque los tests corren sobre SQLite en memoria,
        // que no tiene la función LEFT() de MySQL.
        DB::table('tours')
            ->whereNotNull('seo_description')
            ->update(['meta_description_es' => DB::raw('SUBSTR(seo_description, 1, 160)')]);
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn([
                'slug_en', 'slug_pt',
                'meta_title_es', 'meta_title_en', 'meta_title_pt',
                'meta_description_es', 'meta_description_en', 'meta_description_pt',
                'schema_jsonld_es', 'schema_jsonld_en', 'schema_jsonld_pt',
            ]);
        });
    }
};
