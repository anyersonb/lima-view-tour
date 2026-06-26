<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Subfolder deployment under https://limaviewtours.com/limaprogramacion/.
//
// Default strategy: strip the "/limaprogramacion" prefix from REQUEST_URI so
// Laravel routes (registered without the prefix) match. AppServiceProvider reads
// LIMA_SUBFOLDER to force the root URL and patch Livewire's asset URI. This is
// REQUIRED: Livewire builds its update endpoint via route('livewire.update', [], false)
// (a relative URL), which only emits the "/limaprogramacion" prefix while the
// request runs under this stripped baseUrl="" mode. (A global baseUrl approach
// breaks that — verified: it regressed the admin to /livewire/update -> 404.)
//
// EXCEPTION — Livewire signed file routes (upload-file / preview-file): these are
// validated with request()->hasValidSignature(), which recomputes the HMAC over
// $request->url(). The signed URL was generated WITH the prefix (forceRootUrl), so
// under the stripped mode url() omits the prefix and the signature never matches
// -> 401, breaking ALL image uploads in Filament. For these two routes ONLY we use
// the Symfony baseUrl approach instead (present the front controller as
// "<prefix>/index.php", keep REQUEST_URI intact): Symfony derives
// baseUrl="/limaprogramacion", routes still match on the stripped pathInfo, AND
// url() includes the prefix -> the signature validates. These routes return file
// responses (no Livewire component render), so the route(...,false) caveat above
// does not apply to them. (Mechanics verified in database/scripts/test_subfolder_signature.php.)
if (! empty($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/limaprogramacion')) {
    $limaPath = strtok($_SERVER['REQUEST_URI'], '?');
    $isLivewireFileRoute = (bool) preg_match('#^/limaprogramacion/livewire/(upload-file|preview-file)(/|$)#', $limaPath);

    if ($isLivewireFileRoute) {
        $_SERVER['SCRIPT_NAME'] = '/limaprogramacion/index.php';
        $_SERVER['PHP_SELF']    = '/limaprogramacion/index.php';
    } else {
        $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen('/limaprogramacion')) ?: '/';
    }

    $_SERVER['LIMA_SUBFOLDER'] = true;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
