<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloque comparativo "Tour convencional VS Nuestra experiencia premium".
     * Todo el contenido (multilenguaje + listas) vive en una sola columna JSON
     * para no ensuciar la tabla con ~30 columnas. Estructura:
     *   {
     *     "enabled": bool,
     *     "color": "teal"|"orange",
     *     "badge_es|en|pt": string,
     *     "title_es|en|pt": string, "title_hl_es|en|pt": string,
     *     "intro_es|en|pt": string,
     *     "conv_title_es|en|pt": string, "prem_title_es|en|pt": string,
     *     "conv_es|en|pt": string[], "prem_es|en|pt": string[],
     *     "footer_es|en|pt": string
     *   }
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->json('comparison')->nullable()->after('gallery');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('comparison');
        });
    }
};
