<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Support\DepartureTime;
use App\Support\TourListing;
use Illuminate\Console\Command;

/**
 * Activa la reserva del MISMO DÍA en los tours cortos de Lima y carga los
 * horarios del City Tour Lima + Catacumbas. IDEMPOTENTE: correrlo otra vez no
 * cambia nada (solo informa). Sirve igual en local y en producción.
 *
 *   php artisan lvt:same-day-tours --dry     (solo lista lo que haría)
 *   php artisan lvt:same-day-tours           (aplica)
 *
 * Regla de "tour corto en Lima": región Lima (slug `lima`) y duración de 6
 * horas o menos ("4 horas", "Half Day"...). "Full Day", "2 Días" o texto
 * ilegible NO califican. Nunca APAGA un toggle que ya estaba encendido.
 */
class SameDayToursCommand extends Command
{
    protected $signature = 'lvt:same-day-tours
                            {--dry : Muestra qué cambiaría sin tocar la BD}
                            {--times=09:00,14:30 : Horarios del City Tour Lima + Catacumbas (HH:MM separados por coma)}';

    protected $description = 'Activa "reserva el mismo día" en tours cortos de Lima (≤ 6 h) y carga los horarios del City Tour Lima + Catacumbas';

    private const MAX_HOURS = 6;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $times = DepartureTime::normalizeList((string) $this->option('times'));

        if ($times === []) {
            $this->error('--times no tiene ningún horario válido (usa HH:MM, por ejemplo 09:00,14:30).');

            return self::FAILURE;
        }

        $this->line($dry ? 'MODO PRUEBA (no se guarda nada)' : 'Aplicando cambios');

        $changed = 0;

        $tours = Tour::query()->with('region:id,name_es,slug')->orderBy('id')->get();

        foreach ($tours as $tour) {
            $notes = [];
            $modified = false;
            $isLima = $tour->region && (strtolower((string) $tour->region->slug) === 'lima' || strtolower((string) $tour->region->name_es) === 'lima');
            $weight = TourListing::classifyDuration($tour->duration)['weight'] ?? null;
            $isShort = $weight !== null && $weight <= self::MAX_HOURS;

            if ($isLima && $isShort && ! $tour->allows_same_day) {
                $tour->allows_same_day = true;
                $notes[] = 'mismo día ACTIVADO';
                $modified = true;
            } elseif ($isLima && $isShort) {
                $notes[] = 'mismo día ya estaba activo';
            }

            if ($this->isCityTourCatacumbas($tour) && DepartureTime::normalizeList($tour->departure_times) !== $times) {
                $tour->departure_times = $times;
                $notes[] = 'horarios '.implode(' y ', $times);
                $modified = true;

                if (DepartureTime::normalize($tour->departure_time) === null) {
                    $tour->departure_time = $times[0];
                }
            }

            if ($tour->isDirty() && ! $dry) {
                $tour->save();
            }

            if ($notes !== []) {
                $changed += $modified ? 1 : 0;
                $this->line(sprintf('  #%d %s [%s] -> %s', $tour->id, $tour->title_es, $tour->duration, implode('; ', $notes)));
            }
        }

        $this->info($changed === 0 ? 'Nada que cambiar (ya estaba aplicado).' : "Tours afectados: {$changed}.");

        return self::SUCCESS;
    }

    private function isCityTourCatacumbas(Tour $tour): bool
    {
        $title = mb_strtolower((string) $tour->title_es);

        return str_contains($title, 'city tour lima') && str_contains($title, 'catacumbas');
    }
}
