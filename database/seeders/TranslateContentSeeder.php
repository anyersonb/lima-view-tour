<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Ejecuta todos los fragmentos de traduccion ES->EN/PT generados en
 * database/seeders/AutoTrans/ (un archivo por tour + Taxonomy).
 * Idempotente: cada fragmento solo escribe los campos que tradujo.
 */
class TranslateContentSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('seeders/AutoTrans');
        if (! is_dir($dir)) {
            $this->command?->warn('No existe la carpeta AutoTrans; nada que ejecutar.');
            return;
        }

        $ran = 0;
        foreach (glob($dir . '/*.php') as $file) {
            require_once $file;
            $class = 'Database\\Seeders\\AutoTrans\\' . pathinfo($file, PATHINFO_FILENAME);
            if (class_exists($class)) {
                (new $class())->run();
                $ran++;
            }
        }

        $this->command?->info("TranslateContentSeeder: ejecutados {$ran} fragmentos.");
    }
}
