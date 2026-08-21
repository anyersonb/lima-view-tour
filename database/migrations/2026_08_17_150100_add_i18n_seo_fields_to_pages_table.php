<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mismo criterio que en tours (ver 2026_08_17_150000). `slug_en`/`slug_pt`
 * hoy NO alimentan ninguna ruta pública real: /contacto, /nosotros,
 * /terminos, /privacidad son paths fijos en routes/web.php que no leen
 * `slug` como segmento de URL, solo lo usan como identificador interno para
 * buscar el registro (Page::where('slug', 'contacto')). Se agregan de todos
 * modos para tener paridad de esquema y quedar listos si en el futuro se
 * agrega una ruta genérica /{locale}/pagina/{slug}. Documentado en el
 * reporte de la tarea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('slug_en', 60)->nullable()->unique()->after('slug');
            $table->string('slug_pt', 60)->nullable()->unique()->after('slug_en');

            $table->string('meta_title_es', 70)->nullable()->after('seo_image');
            $table->string('meta_title_en', 70)->nullable()->after('meta_title_es');
            $table->string('meta_title_pt', 70)->nullable()->after('meta_title_en');

            $table->string('meta_description_es', 160)->nullable()->after('meta_title_pt');
            $table->string('meta_description_en', 160)->nullable()->after('meta_description_es');
            $table->string('meta_description_pt', 160)->nullable()->after('meta_description_en');

            $table->text('schema_jsonld_es')->nullable()->after('meta_description_pt');
            $table->text('schema_jsonld_en')->nullable()->after('schema_jsonld_es');
            $table->text('schema_jsonld_pt')->nullable()->after('schema_jsonld_en');
        });

        DB::table('pages')
            ->whereNotNull('seo_title')
            ->update(['meta_title_es' => DB::raw('SUBSTR(seo_title, 1, 70)')]);

        DB::table('pages')
            ->whereNotNull('seo_description')
            ->update(['meta_description_es' => DB::raw('SUBSTR(seo_description, 1, 160)')]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn([
                'slug_en', 'slug_pt',
                'meta_title_es', 'meta_title_en', 'meta_title_pt',
                'meta_description_es', 'meta_description_en', 'meta_description_pt',
                'schema_jsonld_es', 'schema_jsonld_en', 'schema_jsonld_pt',
            ]);
        });
    }
};
