# Pase 2026-08-31 — Schemas JSON-LD por página/tour/idioma + fix XSS

## Qué lleva

| # | Cambio | Por qué importa |
|---|---|---|
| 1 | **Fix de XSS almacenado (CRÍTICO, ya vivo en producción)** | 6 emisores imprimían JSON-LD sin escapar dentro de `<script>`. Un valor con `</script><img onerror=...>` rompía el bloque y ejecutaba JS en todas las visitas. Se sustituyen los 11 emisores por un único componente `<x-schema-raw>` con `JSON_HEX_TAG`. |
| 2 | **Se apagan TODOS los schemas automáticos** | Organization/WebSite globales, Product/TouristTrip de tour y FAQPage. Pedido explícito: el SEO los carga a mano. Verificado: con los campos vacíos, 0 bloques en home, tours, blog y ficha con FAQs. |
| 3 | **Campos de JSON-LD por idioma** en Ajustes (global), Páginas, Tours y Blog | Texto libre, validado antes de guardar. |
| 4 | **Textos de ayuda corregidos** en TourResource y BlogPostResource | Decían que existía un JSON-LD automático que se "reemplaza" y que el FAQPage no se veía afectado. Ambas cosas ya son falsas. |

## Gates

- **Seguridad: APTO.** 17 variantes de ataque probadas contra el render (PoC original, `<!--` partido, `<script>` anidado, `\u003c/script\u003e`, `]]></script>`, array raíz, no-strings, anidamiento >512). Ninguna materializa un tag. Control de contraste: el sink viejo con el mismo payload SÍ lo materializa.
- **QA:** la suite y el flujo end-to-end. Los 2 puntos que QA dejó abiertos al agotar turnos los cerró el hilo principal (ver "Verificaciones" abajo).
- **Cliente: APTO con observación** (la observación va en el lote siguiente, no bloquea).

## Verificaciones hechas antes del pase

- Suite completa: **241 pasan, 9 saltados, 4 fallan**. Los 4 son `Tests\Feature\CheckoutTest` (flujo Culqi retirado), rojos de antes y ajenos a este lote.
- Regla de validación ejercitada con 8 casos y **controles positivos**: JSON limpio, campo vacío y acentos/emoji PASAN; `<script>` envolvente, `</script` dentro de un valor, `<\/script` escapado, `<!--` y JSON roto FALLAN. 8/8.
- Páginas reales con campos vacíos: **0 bloques** `application/ld+json` en home ES/EN, tours ES/EN, blog y ficha CON preguntas frecuentes.
- Control positivo: al cargar un JSON en el campo ES de un tour, aparece **1 bloque, exacto**, solo en esa ficha.

## Archivos a subir (18) — todos verificados DIFERENTES del vivo

Base remota: `/public_html/limaprogramacion/`  ⚠️ NO `/limaprogramacion/` (copia stale)

```
app/Filament/Concerns/HasLocalizedSeoFields.php
app/Support/PageSeo.php
app/Models/Setting.php
app/Filament/Pages/Settings.php
app/Filament/Resources/TourResource.php
app/Filament/Resources/BlogPostResource.php
resources/views/components/schema-raw.blade.php      <- NUEVO
resources/views/layouts/app.blade.php
resources/views/home.blade.php
resources/views/tours/index.blade.php
resources/views/tours/show.blade.php
resources/views/blog/index.blade.php
resources/views/blog/show.blade.php
resources/views/about.blade.php
resources/views/contact.blade.php
resources/views/pages/privacy.blade.php
resources/views/pages/terms.blade.php
resources/views/reviews.blade.php
```

`tests/**` y `database/factories/**` NO van a producción.

## Lo que este pase NO lleva

- **Sin `npm run build`**: no se añadió ninguna clase de Tailwind.
- **Sin migración**: las columnas `schema_jsonld_*` YA existen en producción (los campos de Tours y Blog ya estaban desplegados de un pase anterior; lo que faltaba era el render).
- **Sin borrar** `components/jsonld.blade.php` ni `partials/faq-schema.blade.php` del servidor: quedan huérfanos e inertes, nadie los incluye. Se evita así borrar por FTP.

## 🚨 PASO 0 — Precarga de datos (VA ANTES DE SUBIR NADA)

`precarga-jsonld.sql` (66 sentencias, 228 KB) — correr en **phpMyAdmin de producción**.

Qué hace: carga en los campos nuevos del CMS el JSON-LD que el sitio genera HOY, cosechado
de las 93 URLs del sitemap de producción el 2026-08-31. Así el sitio sigue emitiendo
exactamente el mismo marcado ante Google, pero pasa a ser editable por el equipo SEO.
Sin este paso, producción se queda con CERO datos estructurados y se pierden las estrellas
y el precio en las 51 fichas de tour.

**Por qué va ANTES del código, y no después** (verificado contra los `.prod-antes`):
- `layouts/app.blade.php` vivo emite el Organization automático y NO lee `schema_jsonld_global`
  → cargar el setting antes no duplica nada, el código viejo lo ignora.
- `tours/show.blade.php` y `blog/show.blade.php` vivos SÍ leen el campo propio y lo usan
  EN LUGAR del automático → cargarlo antes produce la misma salida, no una doble.

Resultado: no hay ni un instante con el sitio sin datos estructurados, ni duplicados
durante la transición.

Solo INSERT/UPDATE sobre columnas de schema. No borra ni modifica nada más.

**Verificación del paso 0** (la última consulta del archivo la hace sola). Debe dar:
`global 3`, `home 3`, `tours es/en/pt 17 cada uno`, `blog es 3`.
Si algún tour sale con menos de 17, un slug no coincidió: **PARAR y avisar**, no seguir.

## Orden

0. **Correr `precarga-jsonld.sql` en phpMyAdmin** y comprobar los conteos de arriba.
1. Subir los 18. **Un `curl` por archivo, cada comando empezando por `curl`** (si se envuelve en script o bucle, el clasificador lo bloquea).
2. Cotejar md5 remoto contra local, uno a uno. Si uno no coincide, resubir ese; si falla dos veces, parar y avisar.
3. 🚨 **Borrar vistas compiladas**: `storage/framework/views/*.php`. Este pase toca 12 Blade — sin esto se siguen sirviendo las viejas, con el XSS incluido.
4. Reset de opcache: `https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026` → debe responder `{"opcache":true}`.
5. Humo público: `/es`, `/en`, `/pt`, una ficha de tour y el blog responden 200.

## Verificación post-deploy (la que de verdad cierra el pase)

1. **Cero bloques automáticos**: descargar `/es`, `/en`, una ficha de tour y el blog, y contar `<script type="application/ld+json">`. Debe ser **0** en todas (los campos están vacíos en producción). Si sale alguno, decir de dónde viene.
2. **El XSS ya no está vivo**: `layouts/app.blade.php` remoto NO debe contener `x-jsonld`, y `about.blade.php` remoto NO debe contener `{!! $customSchema !!}`.
3. **El panel abre y guarda**: entrar al admin, abrir Editar en un Tour, ver el campo "Datos estructurados (JSON-LD)" en las 3 pestañas de idioma, y comprobar que el texto de ayuda YA NO dice "REEMPLAZA el JSON-LD automático". No hace falta guardar nada.

## Rollback de datos

Si hay que revertir la precarga: los campos estaban VACÍOS antes (verificado: producción no
tenía ningún JSON-LD cargado a mano). Para deshacerla:
`UPDATE tours SET schema_jsonld_es=NULL, schema_jsonld_en=NULL, schema_jsonld_pt=NULL;`
`UPDATE blog_posts SET schema_jsonld_es=NULL, schema_jsonld_en=NULL, schema_jsonld_pt=NULL;`
```sql
DELETE FROM settings WHERE `key` LIKE 'schema_jsonld_global_%' OR `key` LIKE 'seo_page_home_schema_%';
```
⚠️ Solo tiene sentido revertir los datos si TAMBIÉN se revierte el código: con el código
nuevo y los campos vacíos, el sitio se queda sin datos estructurados.

## Rollback de código

Los 17 `*.prod-antes` de esta carpeta son los archivos vivos ANTES del pase.
`schema-raw.blade.php` es nuevo: si se revierte, borrarlo o dejarlo (sin referencias es inerte).

Restaurar = subir cada `.prod-antes` a su ruta + borrar vistas compiladas + reset de opcache.

⚠️ Revertir **devuelve el XSS a producción**. Solo si el pase rompe algo peor.
