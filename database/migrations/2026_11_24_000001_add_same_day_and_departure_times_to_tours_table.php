<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reserva el mismo día y varios horarios por tour (2026-11-24). Solo aditiva.
     *
     *   allows_same_day      el tour admite reservas para HOY (solo tours cortos en Lima)
     *   same_day_min_hours   margen propio en horas (null = el global de config/booking.php)
     *   departure_times      lista JSON de "HH:MM"; null/vacía = se usa departure_time
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            if (! Schema::hasColumn('tours', 'allows_same_day')) {
                $table->boolean('allows_same_day')->default(false);
            }
            if (! Schema::hasColumn('tours', 'same_day_min_hours')) {
                $table->unsignedTinyInteger('same_day_min_hours')->nullable();
            }
            if (! Schema::hasColumn('tours', 'departure_times')) {
                $table->json('departure_times')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            foreach (['allows_same_day', 'same_day_min_hours', 'departure_times'] as $col) {
                if (Schema::hasColumn('tours', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
