<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home "Tours de un día desde Lima": selección propia por tour (opt-in,
 * default false). Antes esa sección reusaba la misma lista de "Más vendidos"
 * y duplicaba los tours. Sin ningún tour marcado, el home usa tours de la
 * región Lima que no estén en "Más vendidos". Guarded: re-ejecutable desde
 * Mantenimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tours', 'show_in_day_trips')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->boolean('show_in_day_trips')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tours', 'show_in_day_trips')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->dropColumn('show_in_day_trips');
            });
        }
    }
};
