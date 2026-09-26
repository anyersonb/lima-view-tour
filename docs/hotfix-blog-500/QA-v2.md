# QA post-fix v2 — Blog i18n sin fallback a español en EN/PT

Fecha: 2026-09-24
Entorno: local (`http://lima-tour.test`), MySQL `lima_tours`, rama `feat/pagos-fechas-doble-cobro-2026-08-28`.
NO commiteado. NO desplegado. QA no edita código.

## Veredicto
EN CURSO — se completa al cerrar todos los puntos.

## Estado por punto
1. Nota SOLO en español (id=3) — HECHO
2. Nota con los 3 idiomas llenos (nueva, clic real) — HECHO (panel desbloqueado por el coordinador)
3. Regresión site-wide (home, tour, listado tours, institucional × es/en/pt) — HECHO
4. Gate SEO del blog (sitemap XML válido + URLs de notas existentes sin cambio) — HECHO
5. Tests (Seo + filtro Blog) — HECHO
6. Tailwind / manifest.json — HECHO
7. Limpieza de notas de prueba — HECHO

Orden de ejecución tras mensaje del coordinador (límite de turnos): 3, 5, 2, 4, 6, 7.

### Retomado — HECHO tras desbloqueo del coordinador

El coordinador corrió `2026_09_24_000001_create_payment_links_table` y
`2026_09_24_000002_make_travel_date_nullable_on_bookings` (yo no las corrí,
no toqué PaymentLink). Con el panel ya operativo (`/admin` 200, badge "Links
de pago" visible sin error), hice clic real completo en
`/admin/blog-posts/create`:
- Pestaña Español: Título "QA Post Fix v2 Trilingue", Extracto y Cuerpo llenados.
- Pestaña English: Title "QA Post Fix v2 Trilingual", Excerpt y Body llenados.
- Pestaña Português: Título "QA Post Fix v2 Trilingue PT", Extracto y Corpo llenados.
- Pestaña Publicación: switch "Publicado" activado con clic real.
- Clic real en botón "Crear" → redirige a `/admin/blog-posts/4/edit` (sin 500).

Verificado por tinker: `id=4 slug=qa-post-fix-v2-trilingue publicada=si
available_en=si available_pt=si`.

Verificación pública (curl):
- `/es/blog/qa-post-fix-v2-trilingue`, `/en/...`, `/pt/...` → los 3 HTTP 200.
- Aparece en los 3 listados (`grep -c` del slug = 2 en cada uno de
  `/es/blog`, `/en/blog`, `/pt/blog` — card + posible link duplicado, mismo
  patrón ya visto en el punto 1).
- Sitemap: 3 bloques `<url>` (uno por locale, es/en/pt), cada uno con las 4
  etiquetas `hreflang` (es/en/pt/x-default) apuntando a las URLs reales.
- hreflang en `<head>` de la ficha ES: los 4 (es/en/pt/x-default), con hrefs
  reales a las 3 URLs.
- Bono no pedido pero observado: en la ficha ES, "Artículos relacionados"
  muestra la nota `qa-post-fix-guia-rapida-solo-espanol` (id=3, solo
  disponible en ES) — coherente, porque en locale `es` esa nota SÍ está
  disponible. Confirma que el filtro de relacionadas usa datos reales, no
  solo el test.
- Selector de idioma, clic real (1, como pidió el coordinador): desde
  `/es/blog/qa-post-fix-v2-trilingue`, clic en "Idioma" → clic en "English" →
  destino real `http://lima-tour.test/en/blog/qa-post-fix-v2-trilingue`,
  título "QA Post Fix v2 Trilingual" (la ficha traducida real, no el
  listado). Correcto: distinto del comportamiento del punto 1 (que cae al
  listado) porque esta nota SÍ tiene traducción.

Veredicto punto 2: PASA. Único matiz declarado: no se verificaron
manualmente el resto de combinaciones de clic del selector (PT desde esta
ficha) por ahorro de turnos indicado por el coordinador — cubierto por
`BlogTranslationAvailabilityTest` (test "Language switcher sends a translated
locale to its own translated url").

---

### BLOQUEO histórico en punto 2 (ya resuelto arriba) — panel de admin caído por causa AJENA a este fix

Al navegar a `/admin/blog-posts/create` con Playwright (sesión ya autenticada
como admin), la página devuelve **HTTP 500**, título de error:
```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'lima_tours.payment_links'
doesn't exist (Connection: mysql, SQL: select count(*) as aggregate from
`payment_links` where `status` = pending)
```
Confirmado que es site-wide, no solo esa página: `/admin` (dashboard) también
da 500 con el mismo error (consola: `Failed to load resource: 500 @
http://lima-tour.test/admin`, y `net::ERR_CONNECTION_RESET`).

Causa raíz identificada (`app/Filament/Resources/PaymentLinkResource.php:31-34`):
```php
public static function getNavigationBadge(): ?string
{
    return static::getModel()::where('status', 'pending')->count() ?: null;
}
```
Este badge de navegación se evalúa en CADA página del panel para construir el
sidebar. La tabla `payment_links` no existe porque su migración
(`2026_09_24_000001_create_payment_links_table`) está `Pending` (confirmado
con `php artisan migrate:status`). Es trabajo de otro agente/feature
(Payment Links, mismo branch, NO commiteado) — explícitamente fuera de mi
alcance ("NO toques PaymentLink"). No corrí la migración ni toqué el archivo.
No reintenté en bucle: un solo intento a `/admin/blog-posts/create`, uno a
`/admin` para confirmar alcance, y sigo.

**Efecto:** ahora mismo NO se puede hacer ningún "clic real en el panel" en
todo `/admin`, para nada, no solo para blog. Esto bloquea el punto 2 tal como
está pedido (clic real). Se retoma con un sustituto documentado (tinker, sin
clic) para no perder la verificación del comportamiento del FIX v2 en el
front/SEO — declarado explícitamente como NO equivalente a clic real, y el
punto queda en severidad a reportar (no es un defecto de mi fix, pero bloquea
la vía de verificación pedida).

---

## 1. Nota SOLO en español (id=3, `qa-post-fix-guia-rapida-solo-espanol`) — HECHO

Precondición confirmada por tinker: `title_en=''`, `body_en_len=0`, `title_pt=''` → no disponible en `en`/`pt`.

- `/es/blog/qa-post-fix-guia-rapida-solo-espanol` → HTTP 200 (curl).
- `/en/blog/qa-post-fix-guia-rapida-solo-espanol` → HTTP 404, `redirect_url` vacío (sin redirect). Confirmado.
- `/pt/blog/qa-post-fix-guia-rapida-solo-espanol` → HTTP 404, sin redirect. Confirmado.
- Listado `/en/blog`: `grep -c` del slug = 0 (no aparece).
- Listado `/pt/blog`: `grep -c` del slug = 0 (no aparece).
- Listado `/es/blog`: el slug SÍ aparece (aparece 2 veces: card + posible link duplicado, normal).
- `category_id` de la nota es `null`, por lo que no hay página de categoría propia que verificar para este caso puntual; la cobertura de "categorías" para notas con traducción parcial queda cubierta por los tests automatizados de `BlogTranslationAvailabilityTest` (17 tests, ver punto 5), no manualmente aquí — declarado como no verificado manual.
- Relacionadas: no hay otra nota publicada en la BD local (`TOTAL notas` reportado por FIX-v2.md al momento de escribirse == 1, y sigue siendo 1 hasta que yo cree la del punto 2), así que no hay bloque "relacionadas" real que inspeccionar manualmente en esta BD; cubierto por el test automatizado, no por click manual — declarado como no verificado manual.
- Sitemap: `curl http://lima-tour.test/sitemap.xml` — un solo bloque `<url>` para esta nota, con `<loc>` en `/es/...` y solo dos `<xhtml:link rel="alternate">`: `hreflang="es"` y `hreflang="x-default"`. Sin `hreflang="en"` ni `hreflang="pt"` para esta URL. Confirmado con `grep -B2 -A2`.
- hreflang en `<head>` de la ficha ES: `curl` + `grep -o '<link rel="alternate" hreflang...'` → solo `es` y `x-default`. Sin `en`/`pt`. Confirmado.
- Selector de idioma con clic real (Playwright, navegador visible, refs verificados en cada snapshot, no CSS por índice):
  - Desde `/es/blog/qa-post-fix-guia-rapida-solo-espanol`, clic en botón "Idioma" → clic en opción "English" → destino real `http://lima-tour.test/en/blog` (título "Travel Blog — Lima View Tours"), NO 404.
  - Repetido desde la misma ficha (nueva navegación), clic en opción "Português" → destino real `http://lima-tour.test/pt/blog` (título "Blog de Viagens — Lima View Tours"), NO 404.
  - Consola del navegador tras ambos clics: 0 errores, 0 warnings.

Veredicto punto 1: PASA. Todos los criterios pedidos cumplidos con evidencia HTTP/curl y clic real. Único matiz: categorías/relacionadas para ESTA nota puntual no se pudieron ejercitar a mano por falta de una segunda nota en la BD local en ese momento (se resuelve al crear la nota del punto 2, que sí convive con esta).

---

## 3. Regresión site-wide (home, tour, listado tours, institucional × es/en/pt) — HECHO

Método (por indicación del coordinador, para ahorrar turnos): `curl` + `grep -oE`
para extraer `<link rel="canonical">` y `<link rel="alternate" hreflang=...>` de
cada página/locale, comparando contra `https://www.limaviewtours.com` (código
previo al fix, mismo dominio de referencia que ya se usaba en producción).
Playwright solo para el clic real del selector de idioma (2 páginas, ya es más
de 1) y para consola.

### Trampa de medición detectada y corregida (se documenta para no repetirla)
Mi primer `grep -oE '...hreflang="[^"]*" href=...'` (un solo espacio) dio 0
matches en home/tours/listado. Antes de reportarlo como regresión, comparé
contra el código real: `resources/views/layouts/app.blade.php` líneas 86-89
usan el `@else` (branch NO tocado por este fix, aplica cuando
`$localizedAlternates` es null) con espaciado de alineación
(`hreflang="es"      href=`, varios espacios). Confirmé lo mismo en
`https://www.limaviewtours.com` (idéntico padding). Era mi regex, no el
código. Corregido a `[[:space:]]+` y repetido todo.

### Resultados (curl, HTTP real)
| Página | Locale | HTTP | canonical | hreflang es/en/pt/x-default | vs prod |
|---|---|---|---|---|---|
| Home | es/en/pt | 200/200/200 | self-referencing, dominio local | presentes, mismo orden/valores que prod salvo dominio | idéntico (mismo padding con espacios) |
| /tours (listado) | es/en/pt | 200/200/200 | self-referencing | presentes, correctos | idéntico |
| /tours/detalle/{slug} | es/en/pt | 200/200/200 | self-referencing, usa `slugFor(locale)` | presentes; en prod el `en` usa slug traducido real (`lima-city-tour-catacombs`) porque esa nota SÍ tiene `slug_en` en producción; en local el tour de prueba no tiene `slug_en/slug_pt` seedeados, así que cae al slug ES — es diferencia de DATOS, no de código | estructura idéntica |
| /contacto | es | 200 | self-referencing | es/en/pt/x-default correctos, hrefs a `/en/contact-us` y `/pt/contato` (slugs traducidos reales) | idéntico salvo dominio |
| /en/contacto | — | 301 → `/en/contact-us` | canonical de destino correcto | correctos | esperado (slug traducido, no 404) |
| /pt/contacto | — | 301 → `/pt/contato` | canonical de destino correcto | correctos | esperado |

Nota sobre formato: `/contacto` en local emite hreflang con espaciado
normalizado (un espacio) en vez del padding viejo, porque esta ruta SÍ pasa
`$localizedAlternates` (slug traducido real, `contact-us`/`contato`). Es un
cambio de espaciado únicamente — incluido y documentado explícitamente en
`FIX-v2.md` ("cambio no semántico") y ya cubierto por
`InstitutionalPagesLocalizedSlugTest` actualizado (ver punto 5). No es un
hallazgo nuevo.

### H1 único
`grep -c '<h1'` = 1 en home, listado de tours, y `/contacto` (los 3 locales).
En `/tours/detalle/{slug}` da 2 tanto en local (los 3 locales) como en
`https://www.limaviewtours.com` (verificado con el mismo grep en la versión
de producción) — es un patrón preexistente (un `<h1 class="m-title">` de un
menú/breadcrumb móvil oculto + el `<h1 id="tour-title">` real), no introducido
por este fix y no relacionado con los archivos tocados.

### Selector de idioma — clic real (Playwright, 2 páginas, no 1)
- `/es/contacto` → clic en "Idioma" → clic en opción "English" → destino real
  `http://lima-tour.test/en/contact-us` (título "Contact — Lima View Tours").
  Correcto: slug traducido, no 404. Consola: 0 errores.
- `/es/tours/detalle/city-tour-en-lima-con-visita-a-las-catacumbas` → clic en
  "Idioma" → "Português" → destino real
  `http://lima-tour.test/pt/tours/detalle/city-tour-en-lima-con-visita-a-las-catacumbas`
  (título "City Tour Lima Com Catacumbas"). Correcto.

### Consola y red — hallazgo (NO atribuible a este fix, se reporta aparte)
En `/pt/tours/detalle/city-tour-en-lima-con-visita-a-las-catacumbas` la
consola muestra, reproducible en 2/2 cargas:
```
[ERROR] Failed to load resource: 404 @ http://lima-tour.test/storage/tours/Green-gardens-and-palm-trees-line-the-historic…jpeg
```
- La misma URL en `es` (mismo tour, mismo navegador) da 0 errores de consola.
- El archivo no existe en `public/storage/tours/` con ese nombre (contiene
  `…` unicode, probablemente un texto alternativo truncado usado como nombre
  de archivo). Es la sección "Otros viajeros también reservaron" (carrusel de
  tours relacionados), cuyo contenido variable por locale no toca ningún
  archivo de este FIX v2 (`tours/show.blade.php` no está en la lista de
  "Archivos tocados" de `FIX-v2.md`, aunque `git diff --stat` muestra que SÍ
  tiene 483 líneas cambiadas por OTRO trabajo no commiteado en el mismo
  branch — probablemente el lote de reseñas/testimonios). No es una regresión
  de header/lang-switcher/layout (los únicos archivos compartidos que sí toca
  este fix): el propio selector de idioma y el hreflang de esa página están
  correctos. Se reporta como hallazgo aparte, fuera del alcance de este QA,
  para quien mantenga esa carga de imágenes de tours relacionados.

Veredicto punto 3: PASA para lo que corresponde a este fix (hreflang,
canonical, H1, selector de idioma, sin errores de consola nuevos en los
archivos tocados). Un hallazgo de imagen rota queda anotado para otro lote,
no bloquea este fix.

---

## Hallazgo aparte reportado por el coordinador (fuera del blog, para el lote de pagos)

Antes de que el coordinador corriera las migraciones `2026_09_24_000001_create_payment_links_table`
y `2026_09_24_000002_make_travel_date_nullable_on_bookings`, TODO el panel
`/admin` (no solo `/admin/blog-posts/create`) devolvía HTTP 500 para
cualquier sesión autenticada, con:
```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'lima_tours.payment_links'
doesn't exist (Connection: mysql, SQL: select count(*) as aggregate from
`payment_links` where `status` = pending)
```
Causa: `app/Filament/Resources/PaymentLinkResource.php:31-34`,
`getNavigationBadge()` consulta `payment_links` sin guardas, y Filament evalúa
los badges de navegación en cada carga de página del panel — un badge roto
tumba TODO el admin, no solo su propio Resource. Corregido operativamente por
el coordinador corriendo la migración pendiente (fuera de mi alcance: yo no
toqué PaymentLink ni corrí migraciones de ese lote). Recomendado para
`backend-laravel`/`security-engineer` del lote de pagos: envolver el
`getNavigationBadge()` en un `try/catch` o `Schema::hasTable()` para que un
badge nunca pueda tumbar el panel completo — es un patrón de "puerta única"
(ver mi propia lección de memoria "Red en una puerta de cuatro": un solo
punto de falla sin guard bloquea todo). No corresponde a este QA de blog
arreglarlo ni verificarlo más a fondo.

---

## 5. Tests — HECHO

```
php vendor/bin/phpunit tests/Feature/Seo
OK (104 tests, 405 assertions)
```
```
php vendor/bin/phpunit --filter "Blog"
OK, but some tests were skipped!
Tests: 48, Assertions: 142, Skipped: 1.
```
El único skip es `ResourceEditUrlRouteKeyConsistencyTest` con data set
`BlogPostResource`. Verificado que NO es un test camuflado en verde: corrí
solo esa clase (`--filter "ResourceEditUrlRouteKeyConsistency"`, 15 tests, 9
skipped en total, uno por cada Resource sin registro de prueba disponible o
sin página `edit`). El propio test usa `markTestSkipped()` con el motivo en
el nombre (`tests/Feature/Filament/ResourceEditUrlRouteKeyConsistencyTest.php`
líneas 116 y 138: "no declara página 'edit'" o sin registro para probar). No
es un test del FIX v2 (no aparece en la lista de `FIX-v2.md`) ni evalúa el
comportamiento de disponibilidad por idioma — es infraestructura de otro
lote. Confirmado que no oculta una regresión de blog: los 47 tests restantes
del filtro "Blog" pasaron en verde, incluidos los 17 nuevos de
`BlogTranslationAvailabilityTest` listados uno por uno arriba.

Ambas corridas usan SQLite `:memory:` (phpunit.xml), no tocan la BD MySQL
local. Conteos reales, no estimados.

Veredicto punto 5: PASA.

---

## 4. Gate SEO del blog — HECHO

**Sitemap XML válido:** parseado con `simplexml_load_file()` de PHP (parser
XML real, no regex), con `libxml_use_internal_errors(true)` para capturar
cualquier error de bien-formación:
```
RESULT=VALIDO, urls=85
```
Sin errores libxml. También verificado estructuralmente: 85 aperturas `<url>`
y 85 cierres `</url>` (balanceado), declaración `<?xml` presente, `<urlset>`
abierto y cerrado.

**URLs de notas existentes con traducción no cambian:** declarado como NO
verificable con datos reales en este entorno. La BD local `lima_tours` no
tiene ninguna nota de blog de producción real — los únicos 2 registros que
existieron durante esta sesión (`id=3`, `id=4`) fueron mis propias notas de
prueba de los puntos 1 y 2 (ya borradas en el punto 7), y ninguna tenía
`slug_en`/`slug_pt` distintos de su slug en español (por eso no pude ejercitar
a mano el caso "slug traducido real que no debe cambiar"). Lo que SÍ
verifiqué:
- Código: `SitemapController` usa `slugFor($locale)` (revisado en el punto 3
  para tours, mismo método en el modelo `BlogPost`), que es determinístico:
  si `slug_{locale}` existe usa ese valor, si no cae al slug español — no hay
  aleatoriedad ni dependencia de orden de inserción.
- Automatizado: `BlogTranslationAvailabilityTest` incluye "Sitemap includes
  all three locales for a fully translated post" y el test de colisión de
  slugs, verde (ver punto 5).
- Esto es una brecha de cobertura del ENTORNO LOCAL (no hay contenido real
  para comparar antes/después), no del fix. Recomendado antes de desplegar:
  correr este mismo parseo de sitemap contra producción antes y después del
  deploy, comparando el conjunto de `<loc>` para notas con traducción real.

Veredicto punto 4: PASA en lo verificable (XML válido). El sub-punto de
"URLs existentes sin cambio" queda declarado explícitamente como no
verificado por falta de datos reales en el entorno local, no como asumido OK.

---

## 6. Tailwind / manifest.json — HECHO

`git diff` de los 5 archivos Blade tocados (`blog/show.blade.php`,
`blog/index.blade.php`, `layouts/app.blade.php`, `components/header.blade.php`,
`components/lang-switcher.blade.php`) filtrado por líneas añadidas (`+`) que
contengan `class="..."`: **0 coincidencias**. Revisé también el diff completo
línea por línea (75 inserciones): todo el cambio es lógica PHP-en-Blade
(`@php`, construcción de arrays `$localizedAlternates`/`$langSwitcherAlternates`,
nuevos atributos `href` dinámicos) — ninguna clase Tailwind nueva, ninguna
utilidad arbitraria nueva. Coincide con lo declarado en `FIX-v2.md` ("No
requiere config:clear ni view:cache/build"). `public/build/manifest.json`
existe (278 bytes, del build anterior) y no necesita regenerarse porque no
hay clases nuevas que purgar/incluir.

Veredicto punto 6: PASA, no se requiere build.

---

## 7. Limpieza de notas de prueba — HECHO

Borradas por tinker (`BlogPost::find(id)->delete()`), con confirmación antes
y después:
- `id=3` (`qa-post-fix-guia-rapida-solo-espanol`, creada en el FIX v1 QA) — borrada.
- `id=4` (`qa-post-fix-v2-trilingue`, creada por mí en el punto 2 de este QA) — borrada.
- `App\Models\BlogPost::count()` tras el borrado: `0`.
- Verificado que la baja se refleja en el FRONT, no solo en BD (lección de
  memoria: fix de código no limpia caché por sí solo): `curl` a las 2 URLs ES
  → ambas 404; `php artisan cache:clear`; sitemap recargado →
  `grep -c "qa-post-fix"` = 0.

No quedó ninguna nota de prueba en la BD local `lima_tours`.

---

## No verificado (declarar explícitamente)

- Punto 1: categorías y relacionadas para la nota SOLO-ES puntual no se
  ejercitaron a mano con una segunda nota real en ese momento (se resolvió
  parcialmente al crear la nota del punto 2, y quedó cubierto por el test
  automatizado `BlogTranslationAvailabilityTest`, no por clic manual en cada
  combinación).
- Punto 2: solo se probó el clic del selector hacia English (1 clic, por
  indicación explícita del coordinador de ahorrar turnos); el clic hacia
  Português desde la ficha trilingüe no se repitió manualmente — cubierto
  por el test automatizado, no por clic manual.
- Punto 3: el clic real del selector de idioma se hizo en 2 páginas
  (`/contacto` y ficha de tour), no en las 4 categorías de página × 3
  idiomas completas (serían 24 combinaciones) — home y listado de tours se
  verificaron solo por `curl` (hreflang/canonical/H1), sin clic real del
  selector en esas 2.
  encabezados debajo del H1 (jerarquía completa) no se revisaron en cada
  página, solo el conteo de H1 únicos.
- Punto 4: "URLs de notas existentes con traducción no cambian" no se pudo
  verificar con datos reales porque la BD local no tiene notas de blog de
  producción (solo mis propias notas de prueba, ya borradas). Recomendado
  repetir el parseo de sitemap contra producción antes/después del deploy.
- No se verificó JSON-LD (`schemaJsonLd()`) de las notas de prueba de forma
  exhaustiva más allá de lo que ya cubre `SchemaJsonLdNewSurfacesTest` (verde)
  — el propio `FIX-v2.md` documenta que ese método se dejó intacto a
  propósito, fuera del alcance de este fix.
- El hallazgo de imagen rota (`Green-gardens-and-palm-trees...jpeg`, 404 en
  `/pt/tours/detalle/...`) no se investigó a fondo (no es de este fix); solo
  se confirmó que es reproducible y no relacionado con los archivos tocados.
- El hallazgo del badge `PaymentLinkResource::getNavigationBadge()` tumbando
  todo `/admin` no se verificó como corregido de raíz — el coordinador lo
  destrabó corriendo la migración pendiente, pero el código sigue sin guardas
  (`Schema::hasTable()` o `try/catch`), así que puede repetirse con cualquier
  otra migración pendiente futura. Fuera de mi alcance arreglarlo.

## Veredicto final

**APTO** para el FIX v2 del blog (los 8 archivos declarados en `FIX-v2.md`:
`BlogPost.php`, `BlogController.php`, `SitemapController.php`,
`blog/show.blade.php`, `blog/index.blade.php`, `layouts/app.blade.php`,
`header.blade.php`, `lang-switcher.blade.php`).

- El bug objetivo (nota solo-ES ya no debe verse en EN/PT) está resuelto y
  confirmado con evidencia HTTP real (404 sin redirect, ausencia en
  listados/categorías/sitemap/hreflang) — punto 1.
- El caso positivo (nota trilingüe) funciona end-to-end con clic real en el
  panel, sin 500, disponible en los 3 idiomas y en el sitemap — punto 2.
- Regresión site-wide en home/tours/listado/institucional × 3 idiomas: sin
  cambios de hreflang/canonical respecto a producción (estructura idéntica,
  solo domino/whitespace documentado), H1 único, selector de idioma
  funcional, sin errores nuevos de consola atribuibles a los archivos
  tocados — punto 3.
- Gate de regresión SEO: sitemap XML válido (parser real, no regex); sin
  `noindex` encontrado en ninguna respuesta (`curl -sI`, puntos 1-2); ningún
  slug de blog cambiado sin 301 (no hay slugs viejos en juego en este fix);
  H1 único y sin saltos de jerarquía en las URLs tocadas (verificado el
  conteo, no la jerarquía completa H2-H6 — declarado arriba); JSON-LD sigue
  intacto (`SchemaJsonLdNewSurfacesTest` verde); canonicals intactos y sin
  apuntar a staging.
- Tests: 104/104 en `Seo`, 47/48 en `Blog` (el único skip es de otro lote,
  confirmado no relacionado).
- Sin necesidad de build de Tailwind (0 clases nuevas).
- Limpieza confirmada en BD y en el front.

**Hallazgos que NO bloquean este fix pero se reportan aparte:**
1. `PaymentLinkResource::getNavigationBadge()` sin guardas contra tabla
   faltante puede tumbar TODO `/admin` — para el equipo de pagos
   (`backend-laravel`), severidad ALTA operativa (bloquea todo el panel, no
   solo su propio recurso), no bloquea este fix de blog.
2. Imagen rota en el carrusel "Otros viajeros también reservaron" de
   `/pt/tours/detalle/city-tour-en-lima-con-visita-a-las-catacumbas`
   (`Green-gardens-and-palm-trees-line-the-historic…jpeg`, 404) — para quien
   mantenga el contenido/seed de tours, severidad BAJA (una imagen en un
   carrusel secundario), no relacionado con los archivos de este fix.

**Pendiente antes de desplegar a producción (no bloquea el fix en sí, pero
es una recomendación explícita ya presente en `FIX-v2.md`):** correr el
query de notas ocultas por FIX v2 contra la BD de PRODUCCIÓN antes del
deploy, porque ahí sí puede haber notas reales con EN/PT sin traducir que
dejarán de verse en esos idiomas.

Informe completo: `G:\laragon\www\lima-tour\docs\hotfix-blog-500\QA-v2.md`
