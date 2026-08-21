<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices aditivos para el panel de Carritos Abandonados (Filament).
 *
 * El listado ahora ofrece filtros "con teléfono / sin teléfono" (whereNull /
 * whereNotNull sobre `phone`) y por rango de `total`, ambas columnas sin
 * índice hasta ahora. Con miles de carritos, un scan completo en cada
 * carga del panel es evitable con un índice simple.
 *
 * `reminders_sent` (tinyint 0-2) y `email` (ya indexado desde la migración
 * original) no se tocan aquí: baja cardinalidad / ya cubierto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->index('phone');
            $table->index('total');
        });
    }

    public function down(): void
    {
        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['total']);
        });
    }
};
