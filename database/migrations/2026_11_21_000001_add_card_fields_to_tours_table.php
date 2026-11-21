<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home TAL CUAL (21-nov): campos propios de la TARJETA del tour en "Elige tu
 * experiencia", por idioma y todos opcionales (vacío = la tarjeta usa lo de
 * siempre: título del tour, chips derivados, nombre de la región):
 *  - card_title_*    título corto ("City Tour Lima + Catacumbas")
 *  - card_chips_*    chips curados, JSON [{text, accent}] (máx. 4)
 *  - card_location_* etiqueta de ubicación ("ICA + PARACAS", "PACHACÁMAC")
 *
 * ADITIVA y re-ejecutable (cada columna se comprueba antes de crearla):
 *   php artisan migrate --path=database/migrations/2026_11_21_000001_add_card_fields_to_tours_table.php
 */
return new class extends Migration
{
    private const LOCALES = ['es', 'en', 'pt'];

    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            foreach (self::LOCALES as $l) {
                if (! Schema::hasColumn('tours', "card_title_{$l}")) {
                    $table->string("card_title_{$l}", 90)->nullable();
                }
                if (! Schema::hasColumn('tours', "card_chips_{$l}")) {
                    $table->json("card_chips_{$l}")->nullable();
                }
                if (! Schema::hasColumn('tours', "card_location_{$l}")) {
                    $table->string("card_location_{$l}", 60)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        $drop = [];
        foreach (self::LOCALES as $l) {
            foreach (["card_title_{$l}", "card_chips_{$l}", "card_location_{$l}"] as $col) {
                if (Schema::hasColumn('tours', $col)) {
                    $drop[] = $col;
                }
            }
        }

        if ($drop !== []) {
            Schema::table('tours', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }
};
