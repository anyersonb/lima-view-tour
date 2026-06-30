<?php

namespace Database\Seeders\AutoTrans;

use Illuminate\Support\Facades\DB;

/**
 * Corrige title_en que el seeder original dejo en espanol (copia de title_es).
 * Sobrescribe SIEMPRE title_en con la traduccion EN. title_pt ya fue traducido
 * por los fragmentos por-tour. El tour 2 ya estaba bien y no se toca.
 */
class TitlesFix
{
    public function run(): void
    {
        $en = [
            1  => 'FULL-DAY TOUR TO THE HUACACHINA OASIS + BALLESTAS ISLANDS IN PARACAS',
            3  => 'Full Day Lima: Historic Center, Pachacamac, Miraflores and the Magic Water Circuit',
            4  => 'AFTERNOON CITY TOUR + MAGIC WATER CIRCUIT',
            5  => 'FULL-DAY TOUR TO THE HUACACHINA OASIS WITH PRIVATE BUGGY (CANAM) + BALLESTAS ISLANDS IN PARACAS',
            6  => 'CUSCO CITY TOUR',
            7  => 'SACRED VALLEY OF THE INCAS',
            8  => '7-COLOR RAINBOW MOUNTAIN',
            9  => 'HUMANTAY LAKE',
            10 => 'MARAS, MORAY AND SALT MINES + ATV QUAD BIKES',
            11 => 'MARAS, MORAY + SALT MINES',
            12 => 'VISIT MACHU PICCHU IF YOU DO NOT HAVE A TICKET',
            13 => 'PRIVATE 2-DAY TOUR TO MACHU PICCHU (ALL INCLUSIVE)',
            14 => 'NAZCA LINES TOUR + HUACACHINA OASIS TOUR WITH BUGGY AND SANDBOARDING',
            15 => 'Excursion to the Pachacámac Sanctuary + Museum with Hotel Pickup',
            16 => 'Cusco 4-Day Package with Machu Picchu, Humantay and 7-Color Mountain',
        ];

        foreach ($en as $id => $title) {
            DB::table('tours')->where('id', $id)->update(['title_en' => $title]);
        }
    }
}
