# Hotfix blog 500 — FIX v2 (cambio de requisito: sin fallback a español en EN/PT)

Fecha: 2026-09-24/25
Entorno: local, `G:\laragon\www\lima-tour`, rama `feat/pagos-fechas-doble-cobro-2026-08-28`.
NO commiteado, NO pusheado, NO desplegado.

## Qué cambió respecto al FIX v1

El FIX v1 (ver `docs/hotfix-blog-500/QA.md`) arregló el 500 de
`/admin/blog-posts/create` haciendo nullable `title_en/excerpt_en/body_en/
title_pt/excerpt_pt/body_pt`, y los accessors del modelo caían al español
(`?:`) cuando la traducción estaba vacía.

El usuario cambió el requisito el mismo día: EN/PT **siguen siendo
opcionales** (la migración nullable se queda igual — QA ya la corrió en la
BD local `lima_tours`), pero **si la traducción está vacía, la nota ya NO se
muestra en ese idioma**. Se retira el fallback a español. Regla:

> Una nota "existe" en un idioma cuando `title_{locale}` **y**
> `body_{locale}` tienen contenido real (no NULL, no `''`, no solo
> espacios). El `excerpt` NO es parte del criterio — es ayuda para la
> tarjeta/meta cuando existe, no decide si la nota se publica.

No hay migración nueva en este FIX v2: solo cambia código de aplicación
(modelo, controladores, vistas) y tests.

## Archivos tocados o creados (lista completa para desplegar por FTP)

### Del FIX v1 (sin cambios adicionales de esquema, solo referencia)
- `database/migrations/2026_09_24_000000_make_blog_posts_i18n_columns_nullable.php` — **nueva**, ya corrida en MySQL local por QA (ver su `QA.md`, batch 20). Pendiente en cualquier otro entorno.

### Del FIX v2 (este cambio)
- `app/Models/BlogPost.php` — agrega `AVAILABLE_LOCALES`, `scopeAvailableIn()`, `isAvailableIn()`; retira el fallback a español de `getTitleAttribute()`, `getExcerptAttribute()`, `getBodyAttribute()`, `getMetaTitleAttribute()`, `getMetaDescriptionAttribute()`. `schemaJsonLd()` (JSON-LD manual por idioma) se dejó **sin tocar** a propósito — ver "Decisiones" más abajo.
- `app/Http/Controllers/BlogController.php` — `index()` y las categorías filtran por `availableIn($locale)`; `show()` hace `abort_if(!$post->isAvailableIn($locale), 404)` **antes** de evaluar cualquier redirect (sin redirect, sin contenido español bajo la URL equivocada); `related` (notas relacionadas) también filtra por `availableIn($locale)`.
- `app/Http/Controllers/SitemapController.php` — el loop de blog posts ahora calcula los locales disponibles por nota y solo emite `<url>` (y sus `alternates`) para esos; además usa `slugFor($locale)` en vez del slug español fijo para las tres filas (bug preexistente adyacente, corregido de paso: antes el sitemap publicaba `/en/blog/{slug-es}` incluso cuando existía un `slug_en` distinto, una URL que 301-redirige).
- `resources/views/blog/show.blade.php` — construye `$localizedAlternates` (solo idiomas disponibles, para hreflang) y `$langSwitcherAlternates` (los 3 idiomas siempre, con fallback al listado del blog del idioma cuando no hay traducción, para el selector); las notas relacionadas ahora enlazan con `slugFor($locale)` en vez del slug español crudo.
- `resources/views/blog/index.blade.php` — los enlaces de tarjeta usan `slugFor($locale)` en vez del slug español crudo (mismo motivo que arriba).
- `resources/views/layouts/app.blade.php` — el bloque hreflang pasó de 4 `<link>` fijos a un `@foreach` sobre `$localizedAlternates` (omite idiomas ausentes; tours/páginas siguen mandando los 3, sin cambio de comportamiento para ellos); pasa `$langSwitcherAlternates` como prop `alternates` a `<x-header>`.
- `resources/views/components/header.blade.php` — recibe `$alternates` (default `null`) y lo reenvía a las dos instancias de `<x-lang-switcher>` (desktop + menú móvil).
- `resources/views/components/lang-switcher.blade.php` — si recibe `$alternates`, usa esa URL real por idioma (o el listado del blog si no hay traducción); si no la recibe (todas las páginas que no son ficha de blog), mantiene el comportamiento de siempre (mismo path, prefijo de idioma distinto).
- `tests/Unit/Models/BlogPostLocalizedFallbackTest.php` — reescrito: ya no prueba el fallback (lo retira), prueba que los accessors devuelven `''` sin traducción y el valor real cuando existe, más `isAvailableIn()`.
- `tests/Feature/Filament/BlogPostCreateSpanishOnlyTest.php` — el segundo test (que antes esperaba el fallback en `/en` y `/pt`) ahora espera 404 en ambos; se agregó un test de colisión de slugs (punto 7).
- `tests/Feature/Seo/RenderedSeoConsistencyTest.php` — el test que esperaba `<title>` en español bajo `/en/blog/...` ahora espera 404 y `assertDontSee('Machu Picchu')`.
- `tests/Feature/Seo/InstitutionalPagesLocalizedSlugTest.php` — un test asertaba el espaciado exacto de las 4 líneas `<link>` hreflang viejas (`hreflang="es"      href=`, con padding de alineación); se actualizó al espaciado normalizado que produce el nuevo `@foreach` (sin cambio semántico: Tour/Page siguen emitiendo los mismos 3 idiomas + x-default, solo con un espacio en vez de varios).

### Nuevo, específico del FIX v2
- **Nueva:** `tests/Feature/Seo/BlogTranslationAvailabilityTest.php` — 17 tests: 404 sin redirect (ES/EN/PT), no fuga de contenido español, listado, categorías, relacionadas, hreflang (con y sin traducción, x-default), selector de idioma (ambos casos), sitemap (con y sin traducción), colisión de slugs.

No se tocó nada de `PaymentLink`, `Checkout`, `Booking`, `PayPal`, ni `docs/payment-links/` — confirmado con `git status` antes y después.

## Decisiones tomadas en puntos ambiguos del pedido

- **Punto 6 (selector de idioma):** se eligió llevar al **listado del blog** de ese idioma (`route('blog.index', ['locale' => $l])`) en vez de ocultar la opción. Se implementó como un prop opcional `alternates` en `x-lang-switcher` (retrocompatible: sin el prop, cualquier otra página del sitio —tours, páginas institucionales— sigue igual que antes).
- **Punto 8 (accessors, qué NO se tocó):** `BlogPost::schemaJsonLd()` (JSON-LD manual por idioma) **se dejó con su fallback a español intacto**. Es un campo editorial independiente (no uno de los 5 accessors nombrados en el pedido), documentado en el propio Blade como "convivencia" deliberada, y su fallback no fue parte del bug original. Si el negocio quiere que el JSON-LD también deje de heredar contenido en español, es un cambio aparte a decidir explícitamente.
- **Excerpt vacío en una nota disponible:** el criterio de disponibilidad es solo `title` + `body`; una nota con `excerpt_en` vacío pero disponible en `en` simplemente muestra `''` en la tarjeta/meta — el layout ya cae al texto genérico del sitio (`layouts/app.blade.php`), no rompe nada. No se agregó una derivación automática desde el body por no estar pedida y para no ampliar el alcance bajo presión de tiempo.

## Punto 9 — Notas que quedarían ocultas en EN o PT (BD local `lima_tours`, verificado con el código real, no una suposición)

```
php artisan tinker --execute="
\$rows = App\Models\BlogPost::all();
echo 'TOTAL notas: ' . \$rows->count() . PHP_EOL;
foreach (\$rows as \$r) {
    \$hidden = [];
    foreach (['en','pt'] as \$l) { if (! \$r->isAvailableIn(\$l)) { \$hidden[] = \$l; } }
    echo 'id=' . \$r->id . ' | slug=' . \$r->slug . ' | publicada=' . (\$r->is_published ? 'si':'no') . ' | oculta_en=' . (\$hidden ? implode(',', \$hidden) : 'ninguno') . PHP_EOL;
}
"
```

Resultado real:
```
TOTAL notas: 1
id=3 | slug=qa-post-fix-guia-rapida-solo-espanol | publicada=si | oculta_en=en,pt
```

Es la nota que QA creó para probar el FIX v1 (ver su `QA.md`, sección 2). Con
este FIX v2 deja de responder 200 en `/en/blog/...` y `/pt/blog/...` (ahora
404) y deja de aparecer en `/en/blog` y `/pt/blog`. Sigue disponible normal en
`/es/blog/...`. Ninguna otra nota en la BD local se ve afectada porque no hay
más registros.

**Aviso para el usuario antes de desplegar:** si en producción existen notas
de blog reales con EN/PT sin traducir, esas notas van a **dejar de ser
visibles en inglés/portugués** en cuanto se despliegue este código (aunque la
migración nullable ya esté corrida). Recomendado: correr el mismo query de
arriba contra la BD de producción ANTES de desplegar, para saber cuántas
notas y cuáles se ven afectadas.

## Tests: output real

Corridos con `php vendor/bin/phpunit --filter "..."` (SQLite `:memory:` vía
`phpunit.xml`, sin tocar ninguna BD MySQL — mismo mecanismo de aislamiento
usado en el FIX v1):

```
php vendor/bin/phpunit --filter "BlogPostLocalizedFallbackTest|BlogLocalizedSeoTest|BlogPostCreateSpanishOnlyTest|SlugGenerateActionTest|BlogTranslationAvailabilityTest|InstitutionalPagesLocalizedSlugTest|RenderedSeoConsistencyTest|TourLocalizedSeoTest|PageLocalizedSeoRenderTest|AutomaticJsonLdDisabledTest|SystemPagesEditableSeoTest"

PHPUnit 10.5.63 by Sebastian Bergmann and contributors.
OK (97 tests, 368 assertions)
```

También se corrió `tests/Feature/Seo` completo (104 tests) para verificar que
tocar `layouts/app.blade.php`, `header.blade.php` y `lang-switcher.blade.php`
(compartidos con tours y páginas institucionales) no rompió nada fuera del
blog: **104/104 verde**.

### Falsación (gate de "el check puede fallar")

Se hizo `git stash push --` **solo** sobre los 8 archivos de código fuente
tocados (modelo, 2 controladores, 4 Blade, sin tocar los tests nuevos), se
corrieron los mismos tests contra el código PRE-fix, y fallaron en masa:
**16 failures + 4 errors de 30 tests** (sitemap publicando `/en/blog/...`
para una nota sin traducir, sin 404, contenido en español filtrándose bajo
`/en`, etc.). Se restauró el stash (`git stash pop`) y la suite volvió a
97/97 verde. El fix y los tests están genuinamente ligados al comportamiento,
no son un check que siempre da OK.

Además, al escribir el test de hreflang se detectó un falso rojo propio (no
del código): `assertDontSee('hreflang="en"')` también matcheaba el `<a
hreflang="en">` del selector de idioma (que SIEMPRE existe, con o sin
traducción — es la UI, no el `<head>`). Se corrigió anclando la aserción a
`<link rel="alternate" hreflang="en"` (el único que SEO/buscadores miran),
que es el mismo ajuste que ya traía `InstitutionalPagesLocalizedSlugTest`.

## Comandos artisan para prod

Ninguno nuevo específico de este FIX v2 (no hay migración). Sigue pendiente
únicamente lo que ya requería el FIX v1:

```
php artisan migrate --force
```

(la migración `2026_09_24_000000_make_blog_posts_i18n_columns_nullable` — si
el entorno no la tiene corrida todavía).

No requiere `config:clear` ni `view:cache`/build — no hay config nueva ni
clases arbitrarias de Tailwind nuevas.
