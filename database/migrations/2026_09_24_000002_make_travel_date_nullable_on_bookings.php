<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bookings.travel_date era NOT NULL: correcto para el checkout (siempre
 * pide una fecha), pero un PaymentLink puede crearse sin fecha fija ("se
 * coordinará después" — ver PaymentLink.travel_date, nullable por diseño).
 * Ensanchar la columna a nullable no cambia el comportamiento del checkout
 * (sigue exigiendo la fecha en su propia validación, antes de llegar aquí);
 * solo permite que PaymentLinkController pueda grabar una reserva sin fecha
 * cuando el link no la tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('travel_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('travel_date')->nullable(false)->change();
        });
    }
};
