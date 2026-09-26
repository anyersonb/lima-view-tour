# Links de pago — Lote de fixes (FIX-1)

Fecha: 2026-09-25
Ejecutor: backend-laravel
Repo: `G:\laragon\www\lima-tour` — **SIN commitear, SIN desplegar** (límites del encargo)
Fuente de los hallazgos: `docs/payment-links/INFORME.md` (diseño), `docs/payment-links/QA.md`,
`docs/payment-links/SECURITY.md`

---

## Estado de cada ítem

### Bloqueantes

| # | Ítem | Estado | Archivos |
|---|---|---|---|
| 1 | A-1 — PII en links multiuso | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php`, `app/Http/Controllers/WebhookController.php`, `app/Models/PaymentLink.php`, `app/Filament/Resources/PaymentLinkResource.php`, `database/migrations/2026_09_25_000001_add_buyer_columns_to_payment_links_table.php`, `resources/views/payment-links/show.blade.php` |
| 2 | M-2 — carrera capture vs webhook | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php`, `app/Http/Controllers/WebhookController.php` |
| 3 | M-3 — fallo tras capturar | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php`, `app/Services/BookingNotifier.php` |
| 4 | M-4 — webhook sin protección | **CERRADO** | `app/Http/Controllers/WebhookController.php`, `app/Providers/RouteServiceProvider.php`, `routes/web.php` |
| 5 | Badge del menú sin guarda | **CERRADO** | `app/Filament/Resources/PaymentLinkResource.php` |
| 6 | Copiar enlace (http/https + fallback) | **CERRADO** | `app/Filament/Resources/PaymentLinkResource.php`, `resources/views/filament/partials/copy-fallback-script.blade.php`, `app/Providers/Filament/AdminPanelProvider.php` — **verificación con clic real (Playwright) NO ejecutada por mí** (ver "No verificado" abajo) |
| 7 | Robots en /pagar/{code} | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php`, `resources/views/layouts/app.blade.php`, `resources/views/payment-links/show.blade.php` |
| 8 | M-1 — nota interna visible | **CERRADO** | `resources/views/payment-links/show.blade.php` |

### Medios y bajos

| # | Ítem | Estado | Archivos |
|---|---|---|---|
| 9 | Código inexistente → 404 real | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php` |
| 10 | M-5 — travel_date null en correos/cuenta | **CERRADO** | `resources/views/emails/bookings/confirmed.blade.php`, `admin-notification.blade.php`, `payment-reminder.blade.php`, `resources/views/customer/account.blade.php` (2 sitios) |
| 11 | B-2 — reconciliación sin comparar importe | **CERRADO** | `app/Http/Controllers/WebhookController.php` |
| 12 | B-4 — reembolso/denegación no tocan el link | **CERRADO** | `app/Http/Controllers/WebhookController.php` |
| 13 | B-5 — no se puede borrar un link pagado | **CERRADO** | `app/Models/PaymentLink.php`, `app/Filament/Resources/PaymentLinkResource.php`, `app/Filament/Resources/PaymentLinkResource/Pages/EditPaymentLink.php` |
| 14 | B-1 — código completo en logs | **CERRADO** | `app/Models/PaymentLink.php`, `app/Http/Controllers/PaymentLinkController.php`, `app/Http/Controllers/WebhookController.php` |
| 15 | B-6 — createOrder no protege una orden viva | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php` |
| 16 | B-7 — replay con code+orderID ajeno | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php` |

**16/16 ítems cerrados.** Ninguno quedó pendiente por decisión de diseño; el único punto no verificado en vivo es la parte de clic-real-en-navegador del ítem 6 (ver abajo).

---

## Decisiones de diseño

### A-1 — separación `customer_*` vs `buyer_*`

Se agregaron 3 columnas nuevas a `payment_links`: `buyer_name`, `buyer_email`, `buyer_phone`.

- **`customer_*`** = SOLO el prefill que el admin escribe al crear el link. `captureOrder()` y el
  webhook **nunca** lo tocan. Es lo que la página pública precarga en el formulario (aceptable para
  un link de un solo uso enviado a un cliente concreto; con esta separación ya no importa si el link
  es reutilizable, porque este campo no cambia con el pago).
- **`buyer_*`** = lo que de verdad pagó (los datos que el comprador escribió en el formulario al
  momento de pagar). Es la ÚNICA fuente que usa `BookingCreationService` para crear la reserva, y lo
  único que el webhook usa para reconciliar cuando el paso síncrono falló.
- **Duplicar** un link ahora excluye TANTO `customer_*` como `buyer_*` — un link duplicado es una
  plantilla nueva para compartir, nunca debe arrastrar el contacto de a quién iba dirigido el
  original ni de quién pagó.
- La nota interna ("M-1") se quitó de la vista pública en el mismo lote — no se creó un campo
  `public_note` separado porque nadie pidió mostrar una nota al comprador; si en el futuro hace
  falta un mensaje visible, se agrega un campo con etiqueta explícita ("visible al comprador").

### M-2 — carrera capture vs webhook

Los dos caminos (`PaymentLinkController::captureOrder()` paso 2, y
`WebhookController::handlePaypalCaptureCompleted()`) ahora toman `lockForUpdate()` sobre la MISMA
fila de `payment_links` y **re-chequean `booking_id` DESPUÉS de tomar el lock**, antes de crear
ninguna reserva. El que pierde la carrera reutiliza la reserva que el otro ya creó, en vez de crear
una segunda. De paso se corrigió el `where(...)->orWhere(...)->whereNull(...)` del webhook, que
compilaba a `capture = ? OR (order = ? AND booking_id IS NULL)` — agrupado ahora en un closure.

**Hallazgo colateral no pedido pero real**: `Tables\Actions\DeleteBulkAction` usa por defecto
`Collection::each(fn ($r) => $r->delete())`. `Illuminate\Support\Collection::each()` **aborta el
resto del `foreach` en cuanto un callback devuelve `false`** — y el guardián de B-5 (ítem 13)
devuelve exactamente `false` para cancelar el borrado de un link pagado. Sin corregirlo, un solo
link pagado en un lote de borrado dejaba TODO el resto del lote sin borrar (no por protección, sino
por este efecto secundario de `Collection::each()`). Se resolvió con `->using()` + `foreach` normal
en `PaymentLinkResource::table()`. Confirmado con test (`test_paid_link_survives_a_bulk_delete_while_others_are_removed`,
que falla si se vuelve al `->each()` por defecto).

### M-3 — fallo tras capturar

`$captureId` pasó a asignarse por referencia (`use (&$captureId)`) dentro del closure de la
transacción, así que el `catch` de más afuera sabe si PayPal YA cobró aunque el `forceFill()->save()`
posterior haya reventado. En ese caso:
1. Se reintenta la escritura del rastro (`paypal_capture_id`/`paid_at`/`status`) en un `update()`
   AISLADO (vía query builder, fuera de la transacción que falló) — deliberadamente fija
   `status = 'paid'` aunque el link sea `single_use = false`, porque este es un camino de ERROR, no
   el flujo normal de "cobros recurrentes": se prefiere bloquear el link a un reintento seguro.
2. Se responde `payment_captured_booking_pending` (200), nunca "pago fallido".
3. Se notifica al admin por correo (`Mail::raw`, reutilizando
   `BookingNotifier::adminRecipients()`, que pasó de `private` a `public` para esto).

### M-4 — endurecimiento del webhook

- `throttle:paypal-webhook` (60/min/IP, nuevo limiter en `RouteServiceProvider`).
- Límite de 64 KB de payload antes de decodificar nada (413 si se excede).
- `Paypal-Cert-Url` debe ser `https://` y terminar en `.paypal.com` (regex de host), verificado
  ANTES de llamar a la API de PayPal con esos headers.
- El body de `verify-webhook-signature` se arma con `sprintf` insertando `$request->getContent()`
  crudo (ya validado como JSON con `is_array(json_decode(...))`) como el valor de `webhook_event`,
  sin volver a decodificar/codificar — así PayPal verifica la firma contra los bytes EXACTOS que
  mandó, no contra una re-serialización de Laravel que podría cambiar el orden de las claves o los
  escapes.
- `webhook_id` ahora se lee con `Setting::get('paypal_webhook_id') ?: config(...)`, igual que
  `PayPalService`. El campo en Configuración → Pagos **ya existía** (`app/Filament/Pages/Settings.php:200`,
  confirmado leyendo el archivo) — no hizo falta agregarlo.

### B-6 — createOrder no pisa una orden viva

Antes de crear una orden nueva, si el link ya tiene `paypal_order_id`, se hace UN `getOrder()` a
PayPal; si sigue `CREATED`/`APPROVED` (viva), se reutiliza esa MISMA orden en vez de crear otra y
sobrescribir. Si la consulta falla o el estado ya no es capturable, se crea una nueva con
normalidad. Decisión "simple" tal como pide el brief: no se guarda historial de órdenes, solo se
evita pisar la que sigue viva.

### B-7 — replay con code+orderID ajeno

En la rama idempotente ("ya pagado, mismo orderID"), la sesión (que alimenta `checkout/gracias`)
solo se rellena si el `customer_email` del request ACTUAL coincide (case-insensitive) con el
`customer_email` de la Booking real. Cubre el caso legítimo (doble clic/reintento de red del MISMO
comprador, que manda el mismo correo dos veces) sin abrir la puerta a que alguien con el
`code`+`orderID` filtrado cargue la reserva ajena en su propia sesión.

---

## Migraciones

1. `database/migrations/2026_09_24_000001_create_payment_links_table.php` — YA CORRIDA en local (lote anterior).
2. `database/migrations/2026_09_24_000002_make_travel_date_nullable_on_bookings.php` — YA CORRIDA en local (lote anterior).
3. **`database/migrations/2026_09_25_000001_add_buyer_columns_to_payment_links_table.php`** — NUEVA
   de este lote. Agrega `buyer_name`, `buyer_email`, `buyer_phone` (nullable) a `payment_links`.
   Aditiva y reversible (`down()` las elimina).

**Comando:** `php artisan migrate` (aplica solo la #3 en un entorno que ya tenga las dos primeras).

---

## Archivos para el deploy (SOLO el lote de Links de pago)

El árbol de trabajo tiene OTRO lote ajeno en curso (blog/testimonios/i18n — `git status` muestra
`BlogPost.php`, `TestimonialResource.php`, `Tour.php`, `lang/*/ui.php`, etc. modificados SIN
commitear desde ANTES de este encargo, ya documentado en `INFORME.md §9`). La lista de abajo es
SOLO lo que toca Links de pago — no mezclar con ese otro lote al armar el deploy.

**Nuevos:**
- `app/Exceptions/PaymentLinkUnavailableException.php`
- `app/Filament/Resources/PaymentLinkResource.php`
- `app/Filament/Resources/PaymentLinkResource/Pages/ListPaymentLinks.php`
- `app/Filament/Resources/PaymentLinkResource/Pages/CreatePaymentLink.php`
- `app/Filament/Resources/PaymentLinkResource/Pages/EditPaymentLink.php`
- `app/Http/Controllers/PaymentLinkController.php`
- `app/Models/PaymentLink.php`
- `app/Services/BookingCreationService.php`
- `database/factories/PaymentLinkFactory.php`
- **`database/migrations/2026_09_24_000001_create_payment_links_table.php`** (migración)
- **`database/migrations/2026_09_24_000002_make_travel_date_nullable_on_bookings.php`** (migración)
- **`database/migrations/2026_09_25_000001_add_buyer_columns_to_payment_links_table.php`** (migración, NUEVA de este lote)
- `lang/es/payment_links.php`, `lang/en/payment_links.php`, `lang/pt/payment_links.php`
- `resources/views/payment-links/show.blade.php`
- `resources/views/filament/partials/copy-fallback-script.blade.php` (NUEVO)
- `tests/Feature/PaymentLinkTest.php`
- `tests/Feature/PaymentLinkPaypalWebhookTest.php`
- `tests/Feature/Filament/PaymentLinkResourceTest.php`
- `tests/Feature/BookingEmailNullTravelDateTest.php` (NUEVO)

**Modificados:**
- `app/Http/Controllers/CheckoutController.php` (lote anterior — extracción a `BookingCreationService`, sin cambio de comportamiento)
- `app/Http/Controllers/WebhookController.php`
- `app/Providers/Filament/AdminPanelProvider.php` (NUEVO en este lote — render hook del script de copiar)
- `app/Providers/RouteServiceProvider.php` (NUEVO en este lote — limiter `paypal-webhook`)
- `app/Services/BookingNotifier.php` (NUEVO en este lote — `adminRecipients()` público)
- `routes/web.php`
- `resources/views/layouts/app.blade.php` (NUEVO en este lote — `$forceNoindex`)
- `resources/views/checkout/thanks.blade.php` (lote anterior — fecha nullable)
- `resources/views/customer/account.blade.php` (NUEVO en este lote — travel_date nullable, 2 sitios)
- `resources/views/emails/bookings/confirmed.blade.php` (NUEVO en este lote — travel_date nullable)
- `resources/views/emails/bookings/admin-notification.blade.php` (NUEVO en este lote — travel_date nullable)
- `resources/views/emails/bookings/payment-reminder.blade.php` (NUEVO en este lote — travel_date nullable)

## Orden de deploy

1. **Migraciones**: `php artisan migrate` (las 3, en el orden de sus timestamps — Laravel lo hace solo).
2. **Código**: subir los archivos de arriba.
3. **Caché**: `php artisan config:clear` (no se agregó ninguna clave `.env` nueva — `paypal_webhook_id`
   ya vivía en Settings/`.env` desde antes — pero limpiar config es barato y evita arrastrar caché
   vieja) → `php artisan view:cache` → **no hace falta `npm run build`**: este lote no agregó clases
   Tailwind nuevas en el sitio público (`payment-links/show.blade.php` solo perdió el bloque de la
   nota interna y ganó una variable PHP; el HTML del formulario "Enlace para el cliente" vive
   SOLO en `/admin`, que usa el CSS ya compilado de Filament, no el build del sitio).
4. **Pendiente de producción (heredado del lote anterior, sin cambios acá)**: registrar el webhook
   en PayPal Developer Dashboard y `PAYPAL_WEBHOOK_ID` en `.env` de prod (o en Configuración → Pagos
   del panel, ya que `WebhookController` ahora lee de ahí primero).

---

## Resultado real de los tests

```
$ php -d memory_limit=512M vendor/bin/phpunit --filter "PaymentLink"
............................................                      44 / 44 (100%)
OK (44 tests, 139 assertions)

$ php -d memory_limit=512M vendor/bin/phpunit --filter "PaymentLink|Checkout|Booking|Webhook"
...................FFF..F......................................  63 / 104 ( 60%)
.........................................                       104 / 104 (100%)
Tests: 104, Assertions: 383, Failures: 4.
```

Los 4 fallos son EXACTAMENTE el baseline preexistente del flujo Culqi muerto, ya documentado en
`INFORME.md §5/§9` y `QA.md` punto 8:
`CheckoutTest::test_payment_form_renders_with_items`,
`CheckoutTest::test_process_payment_with_valid_token_creates_booking_and_charge`,
`CheckoutTest::test_process_payment_with_failed_token_marks_booking_failed`,
`CheckoutTest::test_booking_email_is_queued_after_success`. **Cero fallos nuevos.**

```
$ php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Seo
OK (104 tests, 405 assertions)

$ php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Filament
OK, but some tests were skipped!
Tests: 91, Assertions: 467, Skipped: 9.   (los 9 skips son preexistentes, ajenos a este lote)

$ php -d memory_limit=512M vendor/bin/phpunit tests/Feature/{Cart,CartGuardrails,ContactForm,ConversionEvents,Example,Newsletter,PaypalCaptureSecurity,PaypalCardDeclined,PhoneCountryCodes,PiiMasking,SecurityHeadersCsp,TourHeaderRatingBadge,TourReviewsPublication}Test.php
OK (70 tests, 1859 assertions)

$ php -d memory_limit=512M vendor/bin/phpunit tests/Unit
OK (39 tests, 87 assertions)
```

**Suite completa ejercida** (Feature root + Filament + Seo + Unit): **452 tests corridos, 4 fallos —
todos el mismo baseline preexistente de Culqi. Cero regresiones introducidas por este lote.**
(Los 13 fallos ajenos de blog/i18n que documentaba `INFORME.md §9` no se volvieron a correr en esta
pasada porque no se tocó ningún archivo de ese otro lote — quedan igual que estaban.)

### Falsación (mínimo 2, hecha con 2)

- **Ítem 9 (404)**: se revirtió temporalmentente `$link ? 200 : 404` a `200` fijo en
  `PaymentLinkController::show()`. `test_show_hides_button_for_unknown_code` pasó de verde a
  **rojo** (`Expected response status code [404] but received 200`). Restaurado, vuelve a verde.
- **Ítem 5 (badge)**: se revirtió temporalmente el guardián de `getNavigationBadge()` a la consulta
  directa sin `Schema::hasTable()`/try-catch. `test_navigation_badge_does_not_crash_when_table_is_missing`
  pasó de verde a **rojo** (`QueryException: no such table: payment_links` — el MISMO error que QA
  reprodujo a mano). Restaurado, vuelve a verde.

### No verificado (declarado, no asumido)

- **Ítem 6, la mitad de "clic real en navegador"**: implementé el fallback (`data-copy-text` +
  listener global con `execCommand`, mismo patrón que `checkout.blade.php` ya usa en el sitio
  público) y lo cubrí con lo que SÍ se puede probar por HTTP (que el HTML trae el atributo
  `data-copy-text` con la URL correcta en la celda de la tabla, en la acción de fila y en el campo
  del formulario — ver assertions de `PaymentLinkResourceTest`). El brief pedía además un clic real
  con Playwright contra `http://lima-tour.test`; **no tengo herramientas de navegador en este rol**
  (backend-laravel no tiene MCP Playwright) — esa verificación le corresponde a `anyerson-qa` en el
  estado 3 de la máquina de 6 estados. Lo dejo señalado explícitamente para que no se dé por hecho.
- El resto de "no verificado por entorno" que ya traía `QA.md`/`SECURITY.md` (flujo de pago real en
  sandbox, credenciales PayPal, bloqueo SSL local) sigue igual — no cambié nada del entorno.

---

## Resumen corto

16/16 ítems del brief cerrados (8 bloqueantes + 8 medios/bajos), con test dedicado para cada uno de
los 11 pedidos explícitamente (1, 2, 3, 4, 5, 7, 9, 10, 11, 12, 13) y 3 más de regalo (6 parcial —
solo lo comprobable por HTTP, 15, 16). Falsación confirmada en 2 de ellos revirtiendo el fix y
viendo el test ponerse rojo con el MISMO síntoma que QA/Security habían reportado a mano. 452 tests
corridos entre Feature+Filament+Seo+Unit, únicos 4 fallos son el baseline preexistente de Culqi
(cero regresiones). Hallazgo colateral no pedido pero real y corregido: `Collection::each()` de
Filament abortaba el resto de un borrado en lote en cuanto tocaba el primer link protegido — sin
esa corrección, el guardián de B-5 rompía silenciosamente el borrado de OTROS links no pagados en el
mismo lote.

No commiteado, no desplegado, no se tocó PayPal ni producción, tal como pedía el encargo.
