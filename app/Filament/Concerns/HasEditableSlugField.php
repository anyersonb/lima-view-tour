<?php

namespace App\Filament\Concerns;

use App\Support\RouteConflict;
use App\Support\RouteRegistry;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Campo de slug editable, estilo WordPress (2026-08-25).
 *
 * Lo que aporta sobre un TextInput pelado:
 *
 *  · Se ve la URL, no un identificador suelto. El prefijo del input muestra
 *    el dominio y la carpeta reales, igual que el editor de enlace permanente
 *    de WordPress.
 *  · Aviso EN VIVO del choque de rutas. Mientras el editor escribe se consulta
 *    RouteRegistry: si esa dirección ya la ocupa otro contenido, se dice cuál
 *    es, qué URL exacta se pisa y aparece un botón para abrir ese otro
 *    contenido y editarlo. No hay que adivinar quién está en el medio.
 *  · Bloqueo al guardar. El aviso en vivo es cortesía; la regla de validación
 *    es la que garantiza que no queden dos rutas iguales, y repite el mismo
 *    mensaje por si el editor llegó por otro camino (pegar, autocompletar).
 *  · Botón "Generar desde el título", que es como WordPress rellena el slug.
 *
 * Convive con los ->unique() de Filament, que siguen puestos: esos cubren la
 * columna, este cubre la RUTA (ver la cabecera de RouteRegistry para los tres
 * choques que la columna no ve).
 *
 * Los nombres de sus métodos NO pisan los de HasLocalizedSeoFields, con el que
 * convive en los mismos resources: dos traits que declaren el mismo método en
 * la misma clase es un error fatal de PHP.
 */
trait HasEditableSlugField
{
    /**
     * @param  string  $modelClass  Tour::class, BlogPost::class o Page::class
     * @param  string  $locale  Idioma del campo: es | en | pt
     * @param  string  $name  Columna: slug | slug_en | slug_pt
     * @param  string  $titleField  Campo del que se genera el slug
     */
    protected static function slugField(
        string $modelClass,
        string $locale,
        string $name,
        string $label,
        string $titleField = 'title_es',
        bool $required = false,
    ): Forms\Components\TextInput {
        // slug_en/slug_pt son varchar(60) en BD; el español es varchar(255) y
        // ya tiene cuatro fichas de hasta 95 caracteres en producción. Poner 60
        // acá dejaría esas cuatro imposibles de guardar.
        $maxLength = $locale === RouteRegistry::BASE_LOCALE ? 255 : 60;

        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->maxLength($maxLength)
            ->required($required)
            ->live(onBlur: true)
            ->prefix(static::slugUrlPrefix($modelClass, $locale))
            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set($name, static::slugSanitize($state, $maxLength)))
            ->dehydrateStateUsing(fn (?string $state) => static::slugSanitize($state, $maxLength))
            ->helperText(fn (Forms\Get $get, ?Model $record): Htmlable => static::slugHelperText($modelClass, $locale, $name, $get, $record))
            ->hintAction(static::slugConflictAction($modelClass, $locale, $name))
            ->suffixAction(static::slugGenerateAction($name, $titleField, $maxLength))
            ->rule(static::slugRouteRule($modelClass, $locale, $name, $maxLength));
    }

    /**
     * Sanea el slug al límite REAL de su columna, no al de una recomendación.
     *
     * No se reutiliza HasLocalizedSeoFields::seoSanitizeSlug() porque recorta a
     * 60 caracteres siempre. Con el campo español bloqueado eso nunca se
     * ejecutaba; al abrirlo, guardar cualquiera de los cuatro tours cuyo slug
     * ya pasa de 60 (el más largo tiene 95) lo habría decapitado en silencio
     * aunque el editor no lo hubiera tocado. Los 60 caracteres siguen vivos
     * como consejo en el contador de abajo, que es su lugar.
     */
    protected static function slugSanitize(?string $value, int $maxLength): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $slug = Str::slug($value);

        if ($slug === '') {
            return null;
        }

        return Str::limit($slug, $maxLength, '');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Estado
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Slugs de los tres idiomas tal como están en el formulario ahora mismo.
     * PageResource no ofrece slug_en/slug_pt: ahí devuelven null y el registro
     * de rutas trabaja solo con el español, que es lo correcto.
     *
     * @return array<string, string|null>
     */
    protected static function slugFormState(Forms\Get $get, ?string $overrideLocale = null, ?string $overrideValue = null): array
    {
        $slugs = [
            'es' => $get('slug'),
            'en' => $get('slug_en'),
            'pt' => $get('slug_pt'),
        ];

        if ($overrideLocale !== null) {
            $slugs[$overrideLocale] = $overrideValue;
        }

        return $slugs;
    }

    /**
     * @return array<int, RouteConflict>
     */
    protected static function slugConflicts(
        string $modelClass,
        string $locale,
        Forms\Get $get,
        ?Model $record,
        ?string $overrideValue = null,
        bool $hasOverride = false,
    ): array {
        $slugs = $hasOverride
            ? static::slugFormState($get, $locale, $overrideValue)
            : static::slugFormState($get);

        if (RouteRegistry::effectiveSlug($slugs, $locale) === null) {
            return [];
        }

        return RouteRegistry::conflictsFor($modelClass, $record?->getKey(), $slugs, $locale);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Presentación
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Prefijo dentro del input: solo el path. El dominio completo va en el
     * enlace de abajo — meterlo también acá deja el campo de escritura en una
     * rendija.
     */
    protected static function slugUrlPrefix(string $modelClass, string $locale): string
    {
        return rtrim(RouteRegistry::pathFor($modelClass, $locale, ''), '/').'/';
    }

    /**
     * Debajo del campo: o los choques detectados, o la URL que va a quedar.
     */
    protected static function slugHelperText(string $modelClass, string $locale, string $name, Forms\Get $get, ?Model $record): Htmlable
    {
        $conflicts = static::slugConflicts($modelClass, $locale, $get, $record);

        if ($conflicts !== []) {
            return new HtmlString(static::renderConflicts($conflicts));
        }

        $slugs = static::slugFormState($get);
        $slug = RouteRegistry::effectiveSlug($slugs, $locale);

        if ($slug === null) {
            return new HtmlString(
                $locale === RouteRegistry::BASE_LOCALE
                    ? 'Se genera del título al crear. Minúsculas, con guiones y sin tildes.'
                    : e('Vacío = se usa el slug en español como fallback (no genera 404).')
            );
        }

        $url = RouteRegistry::urlFor($modelClass, $locale, $slug);
        $len = mb_strlen($slug);
        $hint = $len > 60
            ? "<span style=\"color:#b45309\">{$len} caracteres · lo recomendable para SEO son 60 o menos.</span>"
            : "{$len}/60 caracteres.";

        $renamed = static::slugRenameNotice($locale, $name, $get, $record);

        return new HtmlString(
            '<span style="display:block">URL pública: <a href="'.e($url).'" target="_blank" rel="noopener" style="text-decoration:underline">'.e($url).'</a></span>'
            .'<span style="display:block">'.$hint.'</span>'
            .$renamed
        );
    }

    /**
     * Los choques, agrupados por ocupante.
     *
     * Un slug español chocando con otro tour genera TRES conflictos (uno por
     * idioma, porque el fallback los arrastra). Pintarlos como tres bloques
     * repite el mismo nombre y el mismo enlace tres veces y esconde el dato
     * útil, que son las URLs afectadas. Se agrupan: un aviso por ocupante, con
     * la lista de direcciones y UN enlace para ir a editarlo.
     *
     * @param  array<int, RouteConflict>  $conflicts
     */
    protected static function renderConflicts(array $conflicts): string
    {
        $groups = [];

        foreach ($conflicts as $conflict) {
            $key = $conflict->severity.'|'.$conflict->owner.'|'.($conflict->editUrl ?? '');

            $groups[$key] ??= ['conflict' => $conflict, 'paths' => []];
            $groups[$key]['paths'][] = $conflict->path;
        }

        $html = '';

        foreach ($groups as $group) {
            $conflict = $group['conflict'];
            $paths = array_values(array_unique($group['paths']));
            $blocking = $conflict->isBlocking();
            $color = $blocking ? '#b91c1c' : '#b45309';

            $urls = implode(', ', array_map(fn (string $p) => '<code>'.e($p).'</code>', $paths));

            $html .= '<span style="display:block;color:'.$color.'">'
                .($blocking ? '⛔' : '⚠').' '
                .(count($paths) > 1 ? 'Estas URLs ya están ocupadas' : 'La URL '.$urls.' ya está ocupada')
                .(count($paths) > 1 ? ' ('.$urls.')' : '')
                .' por '.e($conflict->owner).'. '.e($conflict->consequence)
                .'</span>';

            if ($conflict->editUrl !== null) {
                $html .= '<span style="display:block"><a href="'.e($conflict->editUrl).'" target="_blank" rel="noopener"'
                    .' style="color:'.$color.';text-decoration:underline;font-weight:600">'
                    .'Abrir '.e($conflict->owner).' para editar su slug →'
                    .'</a></span>';
            }
        }

        return $html;
    }

    /**
     * Aviso de que este cambio va a dejar un 301 detrás. Es la diferencia
     * entre "cambiar la URL" y "romper la URL", y el editor tiene que verla
     * ANTES de guardar, no enterarse después.
     */
    protected static function slugRenameNotice(string $locale, string $name, Forms\Get $get, ?Model $record): string
    {
        if ($record === null) {
            return '';
        }

        $original = RouteRegistry::effectiveSlug(RouteRegistry::slugsOf($record), $locale);
        $current = RouteRegistry::effectiveSlug(static::slugFormState($get), $locale);

        if ($original === null || $current === null || $original === $current) {
            return '';
        }

        return '<span style="display:block;color:#b45309">↪ Al guardar, la dirección anterior <code>'.e($original)
            .'</code> seguirá funcionando: redirige sola (301) a la nueva. No se pierden los enlaces ya compartidos ni el posicionamiento.</span>';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Acciones
    // ─────────────────────────────────────────────────────────────────────

    /** Botón que abre el contenido que está ocupando la ruta. */
    protected static function slugConflictAction(string $modelClass, string $locale, string $name): Action
    {
        return Action::make($name.'_conflicto')
            ->label('Ver la ruta en conflicto')
            ->icon('heroicon-m-exclamation-triangle')
            ->color('danger')
            ->visible(fn (Forms\Get $get, ?Model $record): bool => static::firstConflictWithUrl($modelClass, $locale, $get, $record) !== null)
            ->url(fn (Forms\Get $get, ?Model $record): ?string => static::firstConflictWithUrl($modelClass, $locale, $get, $record)?->editUrl)
            ->openUrlInNewTab();
    }

    protected static function firstConflictWithUrl(string $modelClass, string $locale, Forms\Get $get, ?Model $record): ?RouteConflict
    {
        foreach (static::slugConflicts($modelClass, $locale, $get, $record) as $conflict) {
            if ($conflict->editUrl !== null) {
                return $conflict;
            }
        }

        return null;
    }

    /** El "generar slug del título" de WordPress. */
    protected static function slugGenerateAction(string $name, string $titleField, int $maxLength): Action
    {
        return Action::make($name.'_generar')
            ->label('Generar desde el título')
            ->icon('heroicon-m-sparkles')
            ->color('gray')
            ->action(function (Forms\Set $set, Forms\Get $get) use ($name, $titleField, $maxLength): void {
                $set($name, static::slugSanitize($get($titleField), $maxLength));
            });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Validación
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Regla bloqueante: dos contenidos no pueden responder a la misma ruta.
     *
     * Devuelve un closure que Filament evalúa (inyectándole $get y $record) y
     * cuyo RETORNO es la regla real de Laravel. Es el mismo idioma que usa
     * HasLocalizedSeoFields::seoJsonLdRule(): si se le entregara a Filament
     * directamente el closure con la firma (string $attribute, $value, Closure
     * $fail), su evaluador intentaría inyectar $attribute por reflexión y
     * reventaría con BindingResolutionException en cada guardado.
     */
    protected static function slugRouteRule(string $modelClass, string $locale, string $name, int $maxLength): Closure
    {
        return static function (Forms\Get $get, ?Model $record) use ($modelClass, $locale, $maxLength): Closure {
            return function (string $attribute, $value, Closure $fail) use ($get, $record, $modelClass, $locale, $maxLength): void {
                // Se comprueba el valor YA SANEADO, que es el que va a quedar
                // en BD y el que arma la URL. Validar el texto crudo dejaría
                // pasar "Laguna Humantay" cuando "laguna-humantay" ya existe.
                $conflicts = static::slugConflicts(
                    $modelClass,
                    $locale,
                    $get,
                    $record,
                    static::slugSanitize(is_string($value) ? $value : null, $maxLength),
                    hasOverride: true,
                );

                $blocking = array_values(array_filter($conflicts, fn (RouteConflict $c) => $c->isBlocking()));

                if ($blocking === []) {
                    return;
                }

                $first = $blocking[0];

                $message = $first->message();

                if ($first->editUrl !== null) {
                    $message .= ' Cambia este slug, o abre el otro contenido y cambia el suyo: '.$first->editUrl;
                }

                $fail($message);
            };
        };
    }

    /**
     * Slug único de verdad para creación programática: solo agrega sufijo si
     * el slug EXACTO ya existe.
     *
     * El Tour::makeUniqueSlug() original contaba con `like 'slug%'`, así que
     * un título nuevo cuyo slug era prefijo de otros ya publicados salía con
     * "-2" pegado sin que hubiera ningún duplicado real. De ahí el
     * "...huacachina-islas-ballestas-en-paracas-2" que quedó en producción.
     */
    public static function uniqueSlugFor(string $modelClass, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);

        if ($base === '') {
            $base = 'contenido';
        }

        $slug = $base;
        $i = 1;

        while (
            $modelClass::query()
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
