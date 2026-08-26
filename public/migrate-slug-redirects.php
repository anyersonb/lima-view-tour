<?php
// ============================================================================
// Script de UN SOLO USO — lote "slug editable" (2026-08-26).
// Aplica la migración 2026_08_25_100000_create_slug_redirects_table.
//
// Producción no tiene consola: este es el mismo mecanismo que se usó el
// 2026-08-20 (public/migrate-once.php). SOLO ADITIVO: crea una tabla nueva, no
// toca ninguna existente. BORRAR de public/ inmediatamente después de correrlo.
//
// Uso: https://limaviewtours.com/migrate-slug-redirects.php?t=anyerson-2026-08-26-slug-redirects
// ============================================================================
if (($_GET['t'] ?? '') !== 'anyerson-2026-08-26-slug-redirects') { http_response_code(404); exit('404'); }
header('Content-Type: text/plain; charset=utf-8');

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== ESTADO PREVIO ===\n";
echo 'slug_redirects existe: '.(Schema::hasTable('slug_redirects') ? 'SI (nada que hacer)' : 'NO')."\n";
echo 'tours: '.DB::table('tours')->count()." filas\n";

echo "\n=== MIGRACIONES PENDIENTES ===\n";
Artisan::call('migrate:status');
echo Artisan::output();

echo "\n=== EJECUTANDO migrate --force ===\n";
try {
    Artisan::call('migrate', ['--force' => true]);
    echo Artisan::output();
    echo "\nRESULTADO: OK\n";
} catch (\Throwable $e) {
    echo "\nRESULTADO: FALLO\n".$e->getMessage()."\n";
    http_response_code(500);
    exit;
}

echo "\n=== VERIFICACION ===\n";

if (! Schema::hasTable('slug_redirects')) {
    echo "FALTA la tabla slug_redirects — NO subir el código todavía.\n";
    http_response_code(500);
    exit;
}

foreach (['redirectable_type', 'redirectable_id', 'locale', 'old_slug', 'created_at', 'updated_at'] as $col) {
    echo str_pad("slug_redirects.{$col}", 34).(Schema::hasColumn('slug_redirects', $col) ? 'OK' : 'FALTA')."\n";
}

$idx = array_unique(array_map(
    fn ($i) => $i->Key_name,
    DB::select('SHOW INDEX FROM slug_redirects')
));
echo "\nindices: ".implode(', ', $idx)."\n";

// La consulta real que hace el código en cada 404 y en cada render del campo
// de slug del panel. Si esto responde, el código nuevo no va a dar 500.
$probe = DB::table('slug_redirects')
    ->where('redirectable_type', 'App\\Models\\Tour')
    ->where('locale', 'es')
    ->where('old_slug', 'sonda-de-verificacion')
    ->value('redirectable_id');

echo 'consulta de resolución: OK (devolvió '.var_export($probe, true).")\n";
echo "\nLISTO. Subir el código y BORRAR este archivo.\n";
