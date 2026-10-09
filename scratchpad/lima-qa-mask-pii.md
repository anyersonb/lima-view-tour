# QA post-fix — enmascarar PII (Clarity/Hotjar) — Lima View Tours

Commits verificados: e22ccd3 + 91fc3c7 (encima de 90d9923, ya aprobado — ver `scratchpad\lima-qa-csp-eventos.md`)
Informe del fix: lima-fix-mask-pii.md

## 1. Diff solo-atributos

`git diff 90d9923 91fc3c7 --stat`:
```
resources/views/checkout.blade.php          |   9 ++-
resources/views/components/footer.blade.php |   4 +-
resources/views/components/popup.blade.php  |   1 +
resources/views/contact.blade.php           |  13 ++-
tests/Feature/PiiMaskingTest.php            | 119 ++++++++++++++++++++++++++++
5 files changed, 139 insertions(+), 7 deletions(-)
```

`git diff 90d9923 91fc3c7 -- resources/views/checkout/thanks.blade.php` → vacío. CONFIRMADO: thanks.blade.php idéntico a pre-fix.

`git diff 90d9923 91fc3c7 -- resources/views/` (los 4 archivos de vista): cada hunk agrega EXCLUSIVAMENTE `data-hj-suppress` y/o `data-clarity-mask="true"` a elementos ya existentes (divs contenedores, inputs, textarea). No hay cambio de clases Tailwind, no hay cambio de estructura (ningún div/label nuevo), no hay cambio de texto/copy, no hay `@if`/lógica nueva. Verificado línea por línea sobre el diff completo (arriba).

`git status --short`: el lote de reseñas ajeno (Settings.php, TestimonialResource.php, HomeController.php, PageController.php, ReviewController.php, TourController.php, Setting.php, Testimonial.php, Tour.php, lang/*/ui.php, about/home/tours/*.blade.php, migración y tests de reseñas) sigue sin commitear e intacto — no forma parte de este fix, no lo toqué.

**Resultado punto 1: PASS.** Diff es solo atributos data-*, thanks.blade.php sin cambios, árbol ajeno intacto.

## 4. Tests (`php artisan test`, sqlite `:memory:` vía phpunit.xml, PHP 8.2.1)

```
php artisan test tests/Feature/PiiMaskingTest.php tests/Feature/ConversionEventsTest.php tests/Feature/SecurityHeadersCspTest.php
```
Resultado: **6 passed (74 assertions)**
- PiiMaskingTest: 1/1 ✓
- ConversionEventsTest: 3/3 ✓
- SecurityHeadersCspTest: 2/2 ✓

Prueba al revés (contra-check, no repetí la falsación completa del informe del fix, solo la
auditoría del método): leído `tests/Feature/PiiMaskingTest.php` completo — usa
`DOMDocument`/`DOMXPath`, exige `length === 1` (no `>=0`) en cada `assertSame`, y camina la
cadena de ancestros de `#customer_name` buscando `data-clarity-mask="true"` +
`data-hj-suppress` juntos — un `assertNotNull` que falla si el ancestro no existe.
Extracción vacía = falla, no OK. Confirmado no vacuo.

**Resultado punto 4: PASS.**

## 2. Render sin regresión (Playwright, servidor local ya levantado en lima-tour.test:80, sin puerto)

Flujo: `/es/tours/detalle/city-tour-en-lima-con-visita-a-las-catacumbas` → seleccionar fecha →
"Reservar ahora" → `POST /es/carrito/agregar` (302) → `GET /es/carrito` (200) con el tour en el
panel "Datos".

**Confirmación en el HTML servido (no en el código fuente):** con `view-source:` sobre
`/es/carrito` ya con el tour agregado, el DOM real devuelto por el servidor contiene:
- `<div class="cart-card" data-hj-suppress data-clarity-mask="true">` (contenedor del panel Datos)
- `#customer_name`, `#phone_local`, `#customer_phone` (hidden), `#customer_email`,
  `#pickup_point` — cada uno con `data-hj-suppress` presente en el HTML real.
- Footer newsletter: `<div class="grid gap-3 sm:grid-cols-2" data-hj-suppress data-clarity-mask="true">`
  con los inputs `name`/`email` también con `data-hj-suppress`.

Esto confirma que los atributos no solo están en el `.blade.php` sino que llegan al HTML que
recibe el navegador (descarta caché de vista vieja).

**Consola:**
- `/es/tours` (fuera de alcance del fix, no tocada): 1 error 404 de una imagen de tour
  (`storage/tours/Green-gardens...jpeg`) — preexistente, no atribuible a este fix (esa vista no
  fue tocada por e22ccd3/91fc3c7).
- `/es/tours/detalle/...` → `/es/carrito` (flujo completo agregar al carrito): 1 error, PayPal SDK
  responde 400 (`client-id=sb-test-client-id-CRO-AUDIT`) — es el client-id de sandbox de pruebas
  del entorno local (visto también en auditorías previas del proyecto), no relacionado con el fix
  de PII masking. Ningún error JS nuevo atribuible a los archivos tocados.
- Red: sin 404 de assets propios del fix (CSS/JS del build, imágenes del tour agregado, todo 200).

**PENDIENTE (no verificado por límite de turnos):**
- `/es/contacto`: no se abrió en esta sesión para confirmar visualmente el DOM con los atributos
  (el diff estático del punto 1 ya confirma que los 4 archivos llevan los atributos correctos;
  falta la confirmación en vivo equivalente a la que sí se hizo en /carrito y footer).
- Popup de newsletter: no aaparece de forma determinística en el flujo navegado; no se forzó su
  apertura ni se confirmó `data-hj-suppress` en el input de email del popup en el DOM servido.
- Capturas 375/1440 de panel Datos y contacto: NO tomadas (diff solo-atributos ya descarta cambio
  visual con más fuerza que una captura; se declara explícitamente no hecho, no se asume OK).
- Viewports tablet/laptop explícitos: no recorridos individualmente (mismo motivo de tiempo).

## 3. Envío funcional

**PENDIENTE — no ejecutado por límite de turnos.** No se probó el envío real de:
- Formulario de contacto (`/es/contacto`).
- Newsletter del footer.
- Checkout "pagar después" hasta completar una reserva con referencia.

No se asume que sigan funcionando: se declara explícitamente no verificado. El diff (punto 1)
solo agrega atributos `data-*` a inputs existentes sin tocar `name`, `id`, `required`, ni el
JS de validación/submit que los lee (confirmado leyendo el diff completo línea por línea: ningún
atributo `name=`, `id=`, ni el bloque `<script>` de sincronización de teléfono en `checkout.blade.php`
fue modificado) — esto hace la regresión de envío poco probable, pero es una inferencia de código,
no una prueba de envío ejecutada.

## Gate de regresión SEO

Alcance del inventario: solo las URLs/componentes tocados por e22ccd3+91fc3c7 (`/es/carrito`
vía `checkout.blade.php`, `/es/contacto`, footer sitewide, popup newsletter) — el fix no tocó
CSS global ni `functions.php`/layout compartido más allá de dos componentes puntuales (footer,
popup), y el diff completo (punto 1) ya confirma que ninguna línea toca `<head>`, `<meta>`,
`<h1>`, JSON-LD ni robots. Herencia: el gate SEO completo de CSP/eventos (commit 90d9923, padre
de este fix) ya fue corrido y aprobado en `scratchpad\lima-qa-csp-eventos.md`; aquí solo se
verifica que ESTE fix no lo regresionó.

1. **noindex / X-Robots-Tag**: PASS. `git diff 90d9923 91fc3c7 -- resources/views/ | grep -iE "noindex|X-Robots"` → 0 matches. Ningún archivo tocado emite meta robots ni cabeceras.
2. **robots.txt de producción, no bloquea CSS/JS**: no re-verificado en esta pasada (el fix no tocó `public/robots.txt` ni rutas de assets) — heredado del pase anterior, PASS por herencia, no por prueba directa en esta sesión.
3. **Canonicals intactos**: PASS. Mismo grep, 0 matches de `canonical` en el diff.
4. **301 de slugs cambiados**: N/A. Este fix no cambió ningún slug ni ruta.
5. **Un solo H1 por página / jerarquía de encabezados**: PASS por diff (0 matches de `<h1`); no se re-inspeccionó visualmente el árbol de encabezados de `/carrito` y `/contacto` en esta sesión (sí se vio el `<h1>` de la ficha de tour en la navegación, sin relación con el fix).
6. **Datos estructurados siguen parseando**: PASS por diff. 0 matches de `application/ld+json` en los 4 archivos tocados — el fix no pudo haber roto JSON-LD porque no lo tocó.
7. **Alcance**: el fix SÍ toca dos componentes compartidos sitewide (`footer.blade.php`, `popup.blade.php`), pero el cambio en ambos es exclusivamente `data-hj-suppress`/`data-clarity-mask` en inputs de newsletter — no hay CSS ni HTML de layout/head afectado, por lo que no se exige barrido de las 6 checks en el inventario completo del sitio (regla aplica a cambios de CSS/functions.php/layout que sí puedan afectar cabeceras o estructura global; este no es el caso, evidencia: diff completo ya revisado en punto 1).

**Gate de regresión SEO: PASS**, con la salvedad explícita del punto 2 (heredado, no re-probado).

## Estado

**PASS sobre lo verificado — con pendientes declarados, no asumidos como OK.**

Verificado y en verde:
- Punto 1 (diff solo-atributos): PASS.
- Punto 4 (tests): PASS, 6/6, método de test no vacuo confirmado.
- Punto 2 (parcial): DOM servido en `/es/carrito` confirmado con los atributos correctos tras
  agregar un tour real; consola sin errores nuevos atribuibles al fix; footer confirmado en el
  mismo volcado de DOM.
- Gate de regresión SEO: PASS.

No verificado (declarado, no asumido):
- `/es/contacto` en vivo (DOM + consola) — solo cubierto por el diff estático.
- Popup de newsletter en vivo.
- Capturas 375/1440.
- Envío funcional real de contacto, newsletter y checkout pagar-después (punto 3 completo).

## Siguiente paso sugerido

No hay ningún hallazgo que devuelva el fix a `backend-laravel`/`maquetador-frontend`: el diff es
limpio y los tests pasan. Antes de dar por cerrado el ciclo completo (estado 3), falta cerrar los
pendientes de arriba — idealmente en una sesión de continuación de este mismo QA, no una nueva
auditoría — antes de pasar a `security-engineer`/`client-validator`/`deployer`.
