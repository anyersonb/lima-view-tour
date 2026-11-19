<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fixes the typos in the Spanish title of the Nazca + Huacachina tour ("SANDBOARDDING",
 * "BUGGIE", "LINEAS" without accent).
 *
 * Uses the query builder on purpose (no model events). The slug is NOT touched: slug
 * generation only runs on create when the slug is empty (Tour::booted creating hook), and the
 * panel's slug field is a separate input, so a title change never regenerates it.
 * Only replaces the exact legacy title, so an edit made by hand is never overwritten.
 */
return new class extends Migration
{
    private const SLUG = 'las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina';

    private const OLD = 'TOUR A LAS LINEAS DE NAZCA + TOUR AL OASIS DE HUACACHINA CON BUGGIE Y SANDBOARDDING';

    private const NEW = 'Tour a las Líneas de Nazca + Oasis de Huacachina con Buggy y Sandboarding';

    public function up(): void
    {
        $updated = DB::table('tours')
            ->where('slug', self::SLUG)
            ->where('title_es', self::OLD)
            ->update(['title_es' => self::NEW]);

        Log::info('nazca title_es fix.', ['rows' => $updated]);
    }

    public function down(): void
    {
        DB::table('tours')
            ->where('slug', self::SLUG)
            ->where('title_es', self::NEW)
            ->update(['title_es' => self::OLD]);
    }
};
