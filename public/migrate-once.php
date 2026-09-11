<?php

// Script de un solo uso: aplica las migraciones del lote SEO i18n + indices.
// SOLO ADITIVO (ADD COLUMN / ADD INDEX). Se borra inmediatamente despues.
// Proteccion: exige token en la query string.
if (($_GET['t'] ?? '') !== 'anyerson-2026-08-20-seo-i18n') {
    http_response_code(404);
    exit('404');
}
header('Content-Type: text/plain; charset=utf-8');

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ESQUEMA PREVIO (respaldo) ===\n";
foreach (['tours', 'pages', 'blog_posts', 'bookings', 'abandoned_carts'] as $t) {
    $cols = Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM `{$t}`");
    echo "{$t}: ".count($cols)." columnas\n";
}

echo "\n=== MIGRACIONES PENDIENTES ===\n";
Illuminate\Support\Facades\Artisan::call('migrate:status');
echo Illuminate\Support\Facades\Artisan::output();

echo "\n=== EJECUTANDO migrate --force ===\n";
try {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo Illuminate\Support\Facades\Artisan::output();
    echo "\nRESULTADO: OK\n";
} catch (\Throwable $e) {
    echo "\nRESULTADO: FALLO\n".$e->getMessage()."\n";
    http_response_code(500);
    exit;
}

echo "\n=== VERIFICACION ===\n";
$checks = [
    'tours.slug_en' => ['tours', 'slug_en'],
    'tours.meta_title_es' => ['tours', 'meta_title_es'],
    'tours.schema_jsonld_es' => ['tours', 'schema_jsonld_es'],
    'pages.slug_en' => ['pages', 'slug_en'],
    'pages.meta_title_es' => ['pages', 'meta_title_es'],
    'blog_posts.slug_en' => ['blog_posts', 'slug_en'],
    'blog_posts.schema_jsonld_es' => ['blog_posts', 'schema_jsonld_es'],
];
foreach ($checks as $label => [$tbl,$col]) {
    $ok = Illuminate\Support\Facades\Schema::hasColumn($tbl, $col);
    echo str_pad($label, 32).($ok ? 'OK' : 'FALTA')."\n";
}
$idx = Illuminate\Support\Facades\DB::select('SHOW INDEX FROM bookings');
$names = array_unique(array_map(fn ($i) => $i->Key_name, $idx));
echo "\nindices en bookings: ".implode(', ', $names)."\n";

echo "\n=== BACKFILL (cuantos quedaron con meta) ===\n";
echo 'tours con meta_title_es: '.Illuminate\Support\Facades\DB::table('tours')->whereNotNull('meta_title_es')->count()."\n";
echo 'pages con meta_title_es: '.Illuminate\Support\Facades\DB::table('pages')->whereNotNull('meta_title_es')->count()."\n";
