<?php
/**
 * Verifies the scoped subfolder strategy in public/index.php:
 *  - Livewire signed file routes (upload-file/preview-file): baseUrl mode ->
 *    $request->url() includes the prefix -> hasValidSignature() PASSES.
 *  - Any other route: strip mode -> pathInfo has no prefix (routes match), as before.
 *
 * Signature is built with the framework's exact formula + key resolver, exactly
 * as Livewire's URL::temporarySignedRoute emits it in prod (signed WITH prefix
 * because forceRootUrl injects it during the _startUpload Livewire action).
 *
 * Run: /g/laragon/bin/php/php8.2.1/php.exe database/scripts/test_subfolder_signature.php
 */

use Illuminate\Http\Request;

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$PREFIX = '/limaprogramacion';
$HOST   = 'limaviewtours.com';
$key    = config('app.key');

// Mirror of the branching logic in public/index.php.
$applyIndexLogic = function (array &$server) use ($PREFIX) {
    if (empty($server['REQUEST_URI']) || ! str_starts_with($server['REQUEST_URI'], $PREFIX)) return;
    $path = strtok($server['REQUEST_URI'], '?');
    $isFileRoute = (bool) preg_match('#^/limaprogramacion/livewire/(upload-file|preview-file)(/|$)#', $path);
    if ($isFileRoute) {
        $server['SCRIPT_NAME'] = $PREFIX . '/index.php';
        $server['PHP_SELF']    = $PREFIX . '/index.php';
    } else {
        $server['REQUEST_URI'] = substr($server['REQUEST_URI'], strlen($PREFIX)) ?: '/';
    }
    $server['LIMA_SUBFOLDER'] = true;
};

$makeRequest = function (string $requestUri, string $method) use ($applyIndexLogic, $HOST, $PREFIX) {
    $server = [
        'REQUEST_URI'     => $requestUri,
        'HTTP_HOST'       => $HOST,
        'HTTPS'           => 'on',
        'SERVER_PORT'     => 443,
        'SCRIPT_NAME'     => $PREFIX . '/public/index.php',   // what Apache sets pre-logic
        'PHP_SELF'        => $PREFIX . '/public/index.php',
        'SCRIPT_FILENAME' => '/home/limaview/public_html' . $PREFIX . '/public/index.php',
    ];
    $applyIndexLogic($server);
    $full = 'https://' . $HOST . $server['REQUEST_URI'];
    return Request::create($full, $method, [], [], [], $server);
};

$pass = true;

// --- Case 1 & 2: signed file routes must validate ---
foreach (['upload-file' => 'POST', 'preview-file' => 'GET'] as $route => $method) {
    $base      = "https://{$HOST}{$PREFIX}/livewire/{$route}";
    $expires   = now()->addMinutes(5)->getTimestamp();
    $withExp   = "{$base}?expires={$expires}";
    $signature = hash_hmac('sha256', $withExp, $key);                 // signed WITH prefix (forceRootUrl)
    $signedUri = "{$PREFIX}/livewire/{$route}?expires={$expires}&signature={$signature}";

    $r = $makeRequest($signedUri, $method);
    $okRoute = $r->getPathInfo() === "/livewire/{$route}";
    $okUrl   = $r->url() === $base;
    $okSig   = $r->hasValidSignature();
    $pass = $pass && $okRoute && $okUrl && $okSig;

    echo "--- {$route} (baseUrl mode) ---\n";
    echo "  baseUrl  = {$r->getBaseUrl()}\n";
    echo "  pathInfo = {$r->getPathInfo()}  " . ($okRoute ? 'PASS' : 'FAIL') . "\n";
    echo "  url()    = {$r->url()}  " . ($okUrl ? 'PASS' : 'FAIL') . "\n";
    echo "  signature: " . ($okSig ? 'PASS' : 'FAIL') . "\n\n";
}

// --- Case 3: a normal admin route stays in strip mode (routes still match) ---
$r = $makeRequest("{$PREFIX}/admin/tours/zztest-tour-qa/edit", 'GET');
$okStrip = $r->getPathInfo() === '/admin/tours/zztest-tour-qa/edit' && $r->getBaseUrl() === '';
$pass = $pass && $okStrip;
echo "--- normal admin route (strip mode) ---\n";
echo "  baseUrl  = '{$r->getBaseUrl()}' (expect empty)\n";
echo "  pathInfo = {$r->getPathInfo()}  " . ($okStrip ? 'PASS' : 'FAIL') . "\n\n";

echo "==================================================\n";
echo $pass ? "ALL PASS: file routes validate, normal routes unchanged.\n"
           : "FAIL: see above.\n";
