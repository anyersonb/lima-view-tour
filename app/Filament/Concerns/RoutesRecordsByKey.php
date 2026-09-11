<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Completa la otra mitad de $recordRouteKeyName (incidente 30/08/2026).
 *
 * El commit 7791956 añadió `protected static ?string $recordRouteKeyName =
 * 'id'` a TourResource y PageResource para que renombrar el slug no invalide
 * la URL de administración. Pero en Filament 3.2 esa propiedad solo está
 * cableada en la RESOLUCIÓN de rutas:
 *
 *   Resource::resolveRecordRouteBinding() → getRecordRouteKeyName() → busca
 *   por 'id'.
 *
 * La GENERACIÓN de la URL (Resource::getUrl()) nunca la consulta: arma la
 * ruta con route(..., ['record' => $model]) y deja que Laravel llame a
 * $model->getRouteKey(), que en Tour y Page devuelve el slug (su
 * getRouteKeyName() de Eloquent, ajeno a Filament).
 *
 * Sin este trait: el panel pinta /admin/tours/{slug}/edit y la resolución
 * busca ese slug por 'id' → no existe → 404 en el 100% de tours y páginas.
 *
 * Los 5 sitios de Filament que arman esa URL (ListRecords::configureEditAction,
 * InteractsWithRecord::getBreadcrumbs, CreateRecord::getRedirectUrl,
 * ViewRecord::configureEditAction, Resource::getGlobalSearchResultUrl) llaman
 * todos a `static::getUrl()` / `$resource::getUrl()` con enlace tardío:
 * overridear el método acá basta, no hace falta tocar ninguno de vendor.
 */
trait RoutesRecordsByKey
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(string $name = 'index', array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        $routeKeyName = static::getRecordRouteKeyName();

        if ($routeKeyName !== null && ($parameters['record'] ?? null) instanceof Model) {
            $parameters['record'] = $parameters['record']->{$routeKeyName};
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant);
    }
}
