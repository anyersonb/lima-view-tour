<?php

// Reset temporal de OPcache (2026-07-16). Protegido por token. BORRAR tras usar.
// Uso: https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026
if (($_GET['key'] ?? '') !== 'lvt-mail-diag-2026') {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: application/json');
$out = [
    'opcache_enabled' => function_exists('opcache_get_status'),
    'reset' => function_exists('opcache_reset') ? opcache_reset() : 'no_opcache',
    'time' => date('c'),
];
// Además limpia el realpath cache
clearstatcache(true);
echo json_encode($out, JSON_PRETTY_PRINT);
