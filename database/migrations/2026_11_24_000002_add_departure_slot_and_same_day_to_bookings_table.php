<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horario elegido y marca "mismo día" en la reserva (2026-11-24). Solo aditiva.
     *
     *   departure_slot  "HH:MM" (hora de Lima) del horario elegido; null = el único/por defecto del tour
     *   same_day        la reserva se hizo el mismo día de la salida (aviso al operador)
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'departure_slot')) {
                $table->string('departure_slot', 5)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'same_day')) {
                $table->boolean('same_day')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'same_day')) {
                $table->dropIndex(['same_day']);
                $table->dropColumn('same_day');
            }
            if (Schema::hasColumn('bookings', 'departure_slot')) {
                $table->dropColumn('departure_slot');
            }
        });
    }
};
