<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Incidente 30/08/2026 (ver RecordRouteKeyNameTest, mismo directorio):
 * $recordRouteKeyName en Filament 3.2 solo está cableada en la mitad de
 * RESOLUCIÓN de rutas (Resource::resolveRecordRouteBinding()); la mitad de
 * GENERACIÓN (Resource::getUrl()) arma la URL con route(..., ['record' =>
 * $model]) y deja que Laravel llame a $model->getRouteKey(), ajeno a
 * Filament. TourResource y PageResource ya llevan el fix (trait
 * RoutesRecordsByKey en app/Filament/Concerns) y RecordRouteKeyNameTest fija
 * ESOS DOS casos.
 *
 * Este test cubre la CLASE del defecto, no los dos casos conocidos: recorre
 * TODOS los Resources de app/Filament/Resources (descubiertos por archivo,
 * nunca listados a mano — un Resource nuevo entra solo) y, para cada uno con
 * página 'edit' y un modelo con Factory, comprueba que la clave con la que
 * se GENERA la URL de edición es la misma con la que se RESUELVE el
 * registro. Si un Resource futuro pisa el slug como route key en un lado y
 * no en el otro, este test lo revienta antes de que llegue a producción.
 *
 * Cada Resource es un caso independiente de un @dataProvider: los que no
 * aplican (sin página 'edit', o sin Factory para crear un registro de
 * prueba) se saltan con markTestSkipped() y el motivo queda en el nombre del
 * dataset — un salto silencioso sería un check que no puede fallar.
 *
 * Excepción, y es la que de verdad importa: un Resource que declara
 * $recordRouteKeyName (hoy Tour y Page) está, por definición, en riesgo de
 * este bug — es justo la señal de que alguien ya pisó la clave de ruta en un
 * lado. Para ESOS no basta con saltar por falta de Factory: el test debe
 * FALLAR, no saltar, porque un salto ahí sería reproducir en silencio el
 * mismo agujero que dejó a Tours y Páginas en 404 el 30/08/2026 sin que
 * nada lo detectara antes de producción.
 */
class ResourceEditUrlRouteKeyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sin helpers de Laravel (app_path(), base_path()): los @dataProvider de
     * PHPUnit se evalúan ANTES de que exista el contenedor de la aplicación,
     * así que cualquier helper que dependa de él fallaría acá. __DIR__ y
     * glob() son PHP puro.
     *
     * @return array<int, class-string<resource>>
     */
    private static function discoverResourceClasses(): array
    {
        $resourcesDir = dirname(__DIR__, 3).'/app/Filament/Resources';
        $classes = [];

        foreach (glob($resourcesDir.'/*Resource.php') ?: [] as $file) {
            $class = 'App\\Filament\\Resources\\'.basename($file, '.php');

            if (class_exists($class) && is_subclass_of($class, Resource::class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return array<string, array{0: class-string<resource>}>
     */
    public static function resourceProvider(): array
    {
        $datasets = [];

        foreach (self::discoverResourceClasses() as $class) {
            $datasets[$class] = [$class];
        }

        return $datasets;
    }

    /**
     * Contraprueba del propio descubrimiento: si dirname(__DIR__, 3) o el
     * patrón *Resource.php se rompieran, el @dataProvider de abajo correría
     * con MENOS casos y ninguno fallaría por eso — un salto silencioso del
     * test entero. Este test aparte lo hace explícito y ruidoso.
     */
    /** @test */
    public function it_discovers_all_thirteen_filament_resources(): void
    {
        $resources = self::discoverResourceClasses();

        $this->assertGreaterThanOrEqual(
            13,
            count($resources),
            'El descubrimiento dinámico de app/Filament/Resources encontró solo '
                .count($resources).' clases; se esperaban al menos 13. Revisar '
                ."dirname(__DIR__, 3) o el patrón '*Resource.php' en discoverResourceClasses()."
        );
    }

    /**
     * @dataProvider resourceProvider
     *
     * @param  class-string<resource>  $resourceClass
     */
    public function test_edit_url_is_generated_with_the_key_its_route_binding_resolves(string $resourceClass): void
    {
        $this->actingAs(User::factory()->create());

        if (! $resourceClass::hasPage('edit')) {
            $this->markTestSkipped("{$resourceClass}: no declara página 'edit' en getPages().");
        }

        $modelClass = $resourceClass::getModel();

        try {
            $record = $modelClass::factory()->create();
        } catch (\Throwable $e) {
            if ($resourceClass::getRecordRouteKeyName() !== null) {
                // Este Resource pisa la clave de ruta: por definición está en
                // riesgo del bug de 404 (generación vía getUrl() vs.
                // resolución vía resolveRecordRouteBinding() pueden divergir).
                // No hay salto seguro para este caso — que falte una Factory
                // no es una razón válida para dejarlo sin probar.
                $this->fail(
                    "{$resourceClass} declara \$recordRouteKeyName pero {$modelClass} no tiene Factory (".
                        $e->getMessage().'). Este Resource está en riesgo del bug de 404 de getUrl()/'.
                        'resolveRecordRouteBinding() (incidente 30/08/2026, ver RecordRouteKeyNameTest) y no '.
                        'puede quedar sin cubrir: agregar una Factory mínima en database/factories/.'
                );
            }

            $this->markTestSkipped(
                "{$resourceClass}: su modelo ({$modelClass}) no tiene Factory para crear un registro de prueba (".$e->getMessage().').'
            );
        }

        // 1. GENERACIÓN: la URL real que el panel pinta para "Editar".
        $url = $resourceClass::getUrl('edit', ['record' => $record]);

        // 2. El parámetro que esa URL realmente lleva, leído contra la tabla
        //    de rutas real — no asumimos que se llame "record", es lo mismo
        //    que Filament hace al recibir la petición HTTP.
        $route = app('router')->getRoutes()->match(Request::create($url, 'GET'));
        $routeParameters = $route->parameters();
        unset($routeParameters['tenant']);

        $this->assertCount(
            1,
            $routeParameters,
            "{$resourceClass}: la ruta 'edit' no expone exactamente un parámetro de registro (URL: {$url})."
        );

        $routeKeyValue = reset($routeParameters);

        // 3. RESOLUCIÓN: lo que Filament hace con ese parámetro al abrir la URL.
        $resolved = $resourceClass::resolveRecordRouteBinding($routeKeyValue);

        $this->assertNotNull(
            $resolved,
            "{$resourceClass}: getUrl('edit') generó la clave '{$routeKeyValue}', pero ".
                "resolveRecordRouteBinding('{$routeKeyValue}') no encontró ningún registro. Mismo bug que ".
                'Tour/Page (ver RecordRouteKeyNameTest, incidente 30/08/2026): la generación y la resolución de '.
                "rutas no usan la misma clave — revisar \$recordRouteKeyName y RoutesRecordsByKey en {$resourceClass}."
        );

        $this->assertTrue(
            $resolved->is($record),
            "{$resourceClass}: resolveRecordRouteBinding('{$routeKeyValue}') resolvió un registro distinto del ".
                'que generó la URL.'
        );
    }
}
