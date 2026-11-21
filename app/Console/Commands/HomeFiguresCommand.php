<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Cifras de confianza del home TAL CUAL (autorizadas por el cliente el 21-nov):
 * "+13.000 viajeros" y "+10 años". Idempotente: se puede correr N veces con el
 * mismo resultado. NO se ejecuta en local/CI: se corre a mano en producción
 * (`php artisan lvt:home-figures`) cuando se despliega el home.
 *
 * Etiquetas: el sitio ya trae las etiquetas traducidas ES/EN/PT en lang/ui.php
 * ("Viajeros satisfechos", "De experiencia"...). Una etiqueta guardada en
 * Ajustes pisa la traducción en los 3 idiomas, por eso este comando NO las
 * escribe; con --clear-labels borra las etiquetas sueltas de las 3 cifras
 * para que vuelvan a usarse las traducidas.
 */
class HomeFiguresCommand extends Command
{
    protected $signature = 'lvt:home-figures
        {--clear-labels : Borra las etiquetas guardadas de las cifras (usa las traducidas de lang/)}
        {--dry-run : Solo muestra qué cambiaría, sin guardar}';

    protected $description = 'Carga las cifras del home (+13.000 viajeros, +10 años). Idempotente.';

    /** @var array<string, string> */
    public const FIGURES = [
        'home_stat_travelers' => '+13.000',
        'stats_years' => '+10',
    ];

    private const LABEL_KEYS = ['home_stat_travelers_label', 'home_stat_years_label', 'home_stat_rating_label'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        foreach (self::FIGURES as $key => $value) {
            $current = Setting::where('key', $key)->value('value');

            if ($current === $value) {
                $this->line("= {$key} ya vale {$value}");

                continue;
            }

            $this->info(($dry ? '[dry-run] ' : '')."{$key}: ".($current === null || $current === '' ? '(vacío)' : $current)." -> {$value}");

            if (! $dry) {
                Setting::set($key, $value);
            }
        }

        foreach (self::LABEL_KEYS as $key) {
            $current = Setting::where('key', $key)->value('value');

            if ($current === null || trim((string) $current) === '') {
                continue;
            }

            if ($this->option('clear-labels')) {
                $this->warn(($dry ? '[dry-run] ' : '')."{$key}: se borra «{$current}» (vuelve la etiqueta traducida)");
                if (! $dry) {
                    Setting::where('key', $key)->get()->each->delete();
                }
            } else {
                $this->warn("{$key} tiene «{$current}» y pisa la etiqueta traducida en los 3 idiomas (usa --clear-labels para quitarla).");
            }
        }

        $this->info($dry ? 'Dry-run: nada se guardó.' : 'Listo.');

        return self::SUCCESS;
    }
}
