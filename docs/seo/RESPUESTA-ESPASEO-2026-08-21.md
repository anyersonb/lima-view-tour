# Respuesta a la propuesta ESPASEO v3 — SEO y AEO

**Documento base:** `Propuesta_Slugs_Metas_LimaViewTours_v3.docx` (ESPASEO, 16-ago-2026).
**Repositorio:** `lima-tour` · rama `feat/tour-detail-pixel-perfect` · commit **`5e90f49`**.
**Fecha de esta respuesta:** 2026-08-21 · **Producción: todavía sin nada de esto** (ver §6).

Este archivo es la respuesta punto por punto, con la ruta del código donde quedó
cada cosa, lo que se decidió distinto y con qué motivo, y lo que sigue del lado
de ESPASEO o del cliente. Los números salen de la base de datos y de los tests,
no de estimaciones.

---

## 1. Lo que pidieron y quedó construido

| Pedido (sección del docx) | Estado | Dónde vive |
|---|---|---|
| Slug propio por idioma en Tours | **Hecho** | columnas `tours.slug_en` / `slug_pt`; formulario en `app/Filament/Resources/TourResource.php:225-235` (EN) y el bloque PT siguiente |
| Slug propio por idioma en Páginas | **Hecho** | `pages.slug_en` / `slug_pt` + `app/Models/Concerns/HasLocalizedSlug.php` |
| Meta título por idioma | **Hecho**, tope 70 con aviso a partir de 60 | `TourResource.php:180-184`, helper `seoCharHelper()` |
| Meta descripción por idioma | **Hecho**, tope 160 con aviso a partir de 150 | `TourResource.php:185-190` |
| Tope de caracteres validado en el propio formulario | **Hecho** | `maxLength()` real + contador en vivo (`->live()`) que cambia el texto de ayuda según el largo |
| Vista previa de la URL final | **Hecho** | `Placeholder::make('slug_*_preview')`, imprime host + ruta + slug |
| Eliminar la pestaña "SEO" global y llevarla a cada idioma | **Hecho** | bloque "SEO — [idioma]" al final de cada pestaña de idioma; la pestaña global ya no existe |
| Campo de datos estructurados (JSON-LD) por tour y por idioma | **Hecho**, con validación de JSON antes de guardar | `tours.schema_jsonld_es/_en/_pt` y `pages.schema_jsonld_*`; regla `seoJsonLdRule()` en `app/Filament/Concerns/HasLocalizedSeoFields.php:80`, cubierta por `SeoJsonLdRuleTest` |
| 301 de la URL vieja al slug traducido | **Hecho** | resolución por slug traducido con 301 desde el slug español; `HasLocalizedSlug` + controladores |
| `hreflang` / `canonical` con las URLs reales de cada idioma | **Hecho** | `resources/views/layouts/app.blade.php:75-81` con `$localizedAlternates`, que ya usa los slugs traducidos |
| URL por idioma de las institucionales (`about-us`, `sobre-nos`, …) | **Hecho** | `config/localized_pages.php` + middleware `CanonicalLocalizedPage` |

Cobertura de pruebas: **63 tests** de SEO/localización en verde (289 aserciones),
más los 116 del lote completo. Incluyen el caso que a mano se olvida: **si el
slug traducido está vacío, la URL en ese idioma cae al slug español y NO
devuelve 404**.

---

## 2. Tres cosas que se decidieron distinto a la propuesta (y por qué)

1. **El slug en español de un tour no se puede editar después de crearlo.**
   La propuesta pide "slug independiente por idioma" para los tres. EN y PT son
   editables siempre; el español queda fijo al crear
   (`TourResource.php:166,176`). Motivo: es el slug que está indexado, el que
   usan los enlaces compartidos y el que apoya todo el mapa de redirects
   legacy. Cambiarlo desde el panel, sin escribir el 301 al mismo tiempo,
   convierte una URL viva en 404 sin que nadie se entere.
   **Consecuencia para ustedes:** los dos slugs ES que la propuesta recomienda
   cambiar (ver §4) no se cambian desde el panel; van con SQL + su 301, en el
   mismo paso.

2. **El slug de las institucionales vive en código, no en la base.**
   `config/localized_pages.php`, no `pages.slug_en`. Motivo: el patrón de esas
   rutas se arma al registrar las rutas, y producción usa `route:cache`; si el
   slug saliera de una consulta, quedaría congelado el día del deploy y
   cambiarlo en el panel daría 404 hasta limpiar la caché de rutas. Es
   exactamente lo que pidieron para "Nosotros" ("requiere campo de slug por
   idioma en el código"), extendido a las cinco.

3. **El tope del meta título es 70, no 60.** El aviso aparece a los 60 (verde
   hasta 60, rojo pasando), pero el campo deja escribir hasta 70. Motivo: un
   tope duro de 60 impide guardar un título que ya está publicado y mide 64, y
   el equipo de contenido termina recortando a ciegas. El límite recomendado se
   comunica, no se impone.

---

## 3. Estado del CONTENIDO: los campos están, vacíos

Medido en la base de datos hoy, sobre los **16 tours publicados**:

| Campo | Tours con dato |
|---|---|
| `slug_en` | **0 de 16** |
| `slug_pt` | **0 de 16** |
| `meta_title_es` / `_en` / `_pt` | **0 de 16** |
| `meta_description_es` | **0 de 16** |
| FAQs cargadas (ES) | **1 de 16** |

Esto es coherente con lo que dice su propio documento (§3: "el texto final de
cada meta título y meta descripción se redacta en la siguiente fase"). Lo que
falta para que el trabajo de código sirva de algo:

- El **catálogo completo de slugs** por idioma (su §5, punto 2). La tabla de la
  §4 cubre 8 filas; hay 16 tours publicados.
- El **texto de meta título y meta descripción** por idioma, con la fórmula de
  su §3.
- El **título en inglés del Full Day Lima**, que su propia tabla marca como
  pendiente para poder recomendar el slug EN.
- Confirmar si **"Tour de Museos"** sigue en catálogo (hoy su URL responde 410,
  que es lo correcto si el tour no existe más).

---

## 4. Los dos slugs ES que la propuesta pide corregir

| Tour | Slug hoy | Recomendado por ESPASEO |
|---|---|---|
| Tour privado 2 días a Machu Picchu (id 13) | `machu-picchu-2-dias-1-noches` | `machu-picchu-2-dias-1-noche` (singular) |
| Montaña Arcoíris de 7 Colores (id 8) | `montana-arcoiris-de-7-colores` | `montana-de-7-colores` |

Los dos son cambios de URL indexada, así que van en un solo paso, nunca solo el
`UPDATE`:

```sql
-- 1) el slug nuevo
UPDATE tours SET slug = 'machu-picchu-2-dias-1-noche' WHERE id = 13;
```

```php
// 2) y en el MISMO deploy, el 301 del viejo, en config/legacy_redirects.php → 'map'
'machu-picchu-2-dias-1-noches' => '/es/tours/detalle/machu-picchu-2-dias-1-noche',
```

Sin el paso 2 la URL vieja no cae en la regla genérica (que busca el slug actual
del tour) y termina en 410. Con los dos pasos, la que está indexada redirige y
el posicionamiento ganado se conserva, que es lo que pide su §5.

**Nota sobre el resto de la tabla §4:** varios slugs recomendados difieren de los
actuales por más que un typo (por ejemplo Pachacámac, que hoy tiene un slug largo
con "museo-con-recojo-a-hotel"). Cada uno de esos cambios es el mismo par
UPDATE + 301. Conviene decidir si vale la pena mover URLs que ya tienen
posicionamiento, o si el beneficio está solo en EN/PT, que hoy no existen y no
tienen nada que perder. **Esa decisión es de ustedes con el cliente**, no la
tomamos por defecto.

---

## 5. Los hallazgos de su §7, uno por uno

**(a) La tercera estructura de URL, `/product/{slug}/`.** Cubierta en código
desde el lote de julio: `routes/web.php:313-320`. Si el slug corresponde a un
tour publicado, redirige 301 a `/es/tours/detalle/{slug}`; si no, responde 410.
Cubre también las variantes con y sin barra final. El mapa explícito de URLs
viejas (`config/legacy_redirects.php`) tiene **27 entradas** con su destino una
por una y **4 declaradas 410**; el resto del inventario de 76 URLs muertas de
julio lo resuelven las reglas genéricas (slug suelto en la raíz, `/product/`,
restos de WordPress), que no necesitan una línea por URL.

**Un detalle que sí sigue abierto de esto:** en producción, la URL vieja **con
barra final** encadena tres redirects y en el `Location` intermedio aparece
`/limaprogramacion/public/`, que es la ruta física de la app dentro del hosting.
Sin barra es un solo salto. No es de la app: es el `.htaccess` de la raíz, que
sigue siendo el provisorio del montaje. Se arregla en el mismo deploy, y hay que
verificarlo con `curl -I` sobre las dos formas, no solo sobre una.

**(b) El slug compartido entre idiomas, confirmado también en portugués.**
Resuelto para las cinco institucionales: `/en/about-us`, `/pt/sobre-nos`,
`/en/contact-us`, `/pt/contato`, `/en/reviews`, `/pt/avaliacoes`, `/en/terms`,
`/pt/termos`, `/en/privacy`, `/pt/privacidade`. La URL vieja con el slug español
(`/pt/nosotros`, `/en/resenas`) emite 301 a la traducida, y el `canonical` y los
`hreflang` apuntan a la URL real de cada idioma. Esto es código nuevo, así que
**en producción sigue devolviendo lo de antes hasta el deploy**.

**(c) Los redirects del bloque .htaccess del 16-ago, sin aplicar.** Confirmado:
producción no tiene ninguno de los cambios de este lote. Van todos juntos, ver
§6. Y una aclaración para no duplicar trabajo: **los 301 de este lote no van en
`.htaccess`**, los resuelve la aplicación (`Route::fallback` + el mapa de
`config/legacy_redirects.php`). El `.htaccess` solo tiene que dejar de meter
`/limaprogramacion/public/` en el medio.

---

## 6. AEO: qué emite hoy cada tipo de página

Lo que su documento pide en §6 ("que los motores de IA entiendan mejor cada
página") ya tiene su capa técnica construida. Estado real por tipo de página:

| Página | Datos estructurados que emite |
|---|---|
| Todo el sitio | `TravelAgency` + `LocalBusiness` con dirección postal, teléfono, geo y `areaServed` (Lima, Cusco, Ica, Paracas) · `WebSite` + `SearchAction` — `resources/views/components/jsonld.blade.php` |
| Ficha de tour | `Product` + `TouristTrip` (el par que Google acepta para fragmentos de reseña; `TouristTrip` solo no califica) · `FAQPage` **si el tour tiene FAQs cargadas** — `resources/views/tours/show.blade.php:253,308`. **No emite `BreadcrumbList`** (ver el hueco al final de esta sección) |
| Artículo de blog | `BlogPosting` + `BreadcrumbList` — el único tipo de página que sí publica la miga |
| Home | `WebSite` + `SearchAction` |
| Cualquier página | `FAQPage` del bloque de preguntas frecuentes global — `resources/views/partials/faq-schema.blade.php` |
| Por idioma | el campo `schema_jsonld_{idioma}` **reemplaza** el JSON-LD automático de esa página en ese idioma (el `FAQPage` no se toca). Con validación de JSON al guardar |

**Dónde está el hueco real de AEO, y no es de código: es de contenido.** Solo
**1 de los 16 tours publicados** tiene preguntas frecuentes cargadas. El
`FAQPage` es la pieza que más peso tiene para AI Overviews y para los motores de
respuesta, y hoy se emite en una sola ficha porque en las otras 15 no hay nada
que emitir. Tres o cuatro preguntas reales por tour (qué incluye, cuánto dura,
dónde recoge, qué pasa si llueve) rinden más que cualquier ajuste de schema
adicional.

**Un hueco de código, chico y real:** la ficha de tour no emite
`BreadcrumbList`, y el artículo de blog sí. La miga visible existe en la ficha,
pero no su versión estructurada, que es la que Google usa para pintar la ruta en
el resultado. Es media hora de trabajo y se puede hacer en el mismo deploy si lo
piden; no se metió en este lote para no mezclarlo con lo que ustedes ya
validaron.

Lo que **no** se hizo, a propósito: no se agregó `speakable` ni tipos extra de
schema. Con `Product` + `TouristTrip` + `FAQPage` + `LocalBusiness` la ficha ya
cubre lo que los validadores piden; sumar tipos sin contenido que los respalde
no mejora nada y agrega superficie para errores de validación.

---

## 7. Deploy: qué falta para que todo esto exista en producción

Producción no tiene **nada** de este lote todavía. El orden importa: primero las
migraciones, después el código.

1. `bash deploy-lote-2026-08-20.sh migraciones`
2. `bash deploy-lote-2026-08-20.sh runner`
3. Abrir `migrate-once.php?t=anyerson-2026-08-20-seo-i18n` (corre las migraciones)
4. `bash deploy-lote-2026-08-20.sh codigo`
5. `bash deploy-lote-2026-08-20.sh caches`
6. Abrir `opcache-reset.php?key=lvt-mail-diag-2026` (producción congela el opcache)
7. Borrar `migrate-once.php` del servidor
8. Verificar con `curl -I` (las dos formas, con y sin barra final):
   `/machu-picchu-2-dias-1-noches/`, `/product/valle-sagrado-de-los-incas/`,
   `/en/about-us`, `/pt/sobre-nos`, `/pt/nosotros` (debe dar 301 a `/pt/sobre-nos`)

Producción no tiene caché de rutas ni de config (solo packages/services), y su
`public/.htaccess` es idéntico al del repo — el que hay que revisar es el de la
raíz del hosting.

---

## 8. Resumen de quién tiene la pelota

| Pendiente | De quién |
|---|---|
| Catálogo completo de slugs EN/PT (16 tours, no 8) | ESPASEO |
| Texto de meta título y meta descripción por idioma | ESPASEO |
| Título en inglés del Full Day Lima | Cliente |
| ¿"Tour de Museos" sigue en catálogo? | Cliente |
| Preguntas frecuentes por tour (la palanca de AEO) | Cliente + ESPASEO |
| Decidir si se mueven los slugs ES ya posicionados | Cliente + ESPASEO |
| Deploy del lote y arreglo del `.htaccess` de la raíz | Nosotros |
