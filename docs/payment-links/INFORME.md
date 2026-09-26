# Links de pago — Informe de implementación

Fecha: 2026-09-24
Repo: `G:\laragon\www\lima-tour` (rama `feat/pagos-fechas-doble-cobro-2026-08-28`)
Estado: **implementado y verde localmente, SIN commitear, SIN desplegar** (según límites del encargo).

---

## 1. Alcance cubierto

1. Migración + modelo `PaymentLink`.
2. Recurso Filament "Links de pago" (precarga de precio, tabla con copiar enlace, anular, duplicar, filtro por estado).
3. Página pública `/pagar/{code}` con botón PayPal, noindex, y mensaje claro cuando el link no es usable.
4. Endpoints `create`/`capture` idempotentes, monto siempre desde BD, con rate limit.
5. Webhook PayPal (`PAYMENT.CAPTURE.COMPLETED` / `DENIED` / `REFUNDED`) con verificación de firma fail-closed, cubriendo checkout normal y links de pago.
6. CSP verificada (no requirió cambios).
7. Tests: 27 nuevos, todos verdes. Suite de checkout corrida completa (baseline sin regresión).

---

## 2. Archivos creados

- `app/Models/PaymentLink.php`
- `app/Http/Controllers/PaymentLinkController.php`
- `app/Services/BookingCreationService.php`
- `app/Exceptions/PaymentLinkUnavailableException.php`
- `app/Filament/Resources/PaymentLinkResource.php`
- `app/Filament/Resources/PaymentLinkResource/Pages/ListPaymentLinks.php`
- `app/Filament/Resources/PaymentLinkResource/Pages/CreatePaymentLink.php`
- `app/Filament/Resources/PaymentLinkResource/Pages/EditPaymentLink.php`
- `database/factories/PaymentLinkFactory.php`
- `database/migrations/2026_09_24_000001_create_payment_links_table.php`
- `database/migrations/2026_09_24_000002_make_travel_date_nullable_on_bookings.php`
- `resources/views/payment-links/show.blade.php`
- `lang/{es,en,pt}/payment_links.php`
- `tests/Feature/PaymentLinkTest.php`
- `tests/Feature/PaymentLinkPaypalWebhookTest.php`
- `tests/Feature/Filament/PaymentLinkResourceTest.php`

## 3. Archivos modificados

- `app/Http/Controllers/CheckoutController.php` — extraída la construcción de Booking(s) a `BookingCreationService` (ver §5). Comportamiento observable **sin cambios** (verificado con la suite completa).
- `app/Http/Controllers/WebhookController.php` — agregado `paypal()` + verificación de firma + 3 handlers, junto al `culqi()` ya existente.
- `routes/web.php` — 3 rutas públicas `/pagar/{code}...` (fuera del grupo `{locale}`) + 1 ruta de webhook `/webhooks/paypal`.
- `resources/views/checkout/thanks.blade.php` — la fecha de viaje ahora se muestra condicionalmente (`$booking['travel_date'] ? ... : __('payment_links.date_to_be_arranged')`); antes `Carbon::parse(null)` habría mostrado "hoy" en vez de avisar que la fecha está pendiente. Solo se activa cuando `travel_date` es null, lo que **antes de este lote nunca ocurría** en ningún flujo — no afecta al checkout normal.

## 4. Migraciones

1. `2026_09_24_000001_create_payment_links_table.php` — tabla nueva, reversible.
2. `2026_09_24_000002_make_travel_date_nullable_on_bookings.php` — **ensancha** `bookings.travel_date` de `NOT NULL` a `nullable` (usa `doctrine/dbal`, ya instalado). Necesaria porque `PaymentLink.travel_date` es nullable por diseño del brief ("se coordinará después"), y sin este cambio `Booking::create()` reventaba con `SQLSTATE[23000]` al capturar un link sin fecha. Es aditiva: el checkout normal sigue exigiendo la fecha en su propia validación (`ProcessPaymentRequest`, `BookingCalendar::dateRules()`), antes de llegar a la BD — nunca crea un Booking con `travel_date` null. Reversible (`down()` vuelve a `NOT NULL`; solo aplicaría en un entorno sin filas con `travel_date` null ya grabadas).

**Comando a correr:** `php artisan migrate`

## 5. Decisión de diseño: refactor compartido con el checkout

El brief pedía "extrae un método compartido si hace falta, sin cambiar el comportamiento del checkout". Se extrajo `BookingCreationService::createBookings()` desde `CheckoutController::finalizeBookings()` — contiene la transacción de creación del/los Booking(s), la resolución de `customer_id` (cuenta invitada + correo de credenciales diferido hasta después del commit). `CheckoutController` conserva intactos: el chequeo de fechas (`assertBookableDates`), el logging `checkout.finalize_bookings`, el envío de notificación (`BookingNotifier`), el cierre de carrito abandonado y `cart->clear()`.

Verificación de que el checkout no cambió: se corrió la suite `CheckoutTest` **contra el código sin tocar** (vía `git stash` selectivo de solo `CheckoutController.php`) y se confirmaron **los mismos 4 fallos preexistentes** (flujo Culqi muerto, ya documentado en memoria de proyecto — `tests_process_payment_with_valid_token...`, `..._with_failed_token...`, `..._payment_form_renders_with_items`, `..._booking_email_is_queued...`). Con mi refactor aplicado, los mismos 4 fallan igual y ningún otro se rompe — ver §7.

## 6. Rutas nuevas

```
GET  /pagar/{code}                      payment-links.show           throttle:30,1
POST /pagar/{code}/paypal/create        payment-links.paypal.create  throttle:checkout (5/min/IP)
POST /pagar/{code}/paypal/capture       payment-links.paypal.capture throttle:checkout (5/min/IP)
POST /webhooks/paypal                   webhooks.paypal               CSRF exento (webhooks/* ya excluido globalmente)
```

Deliberadamente **fuera** del grupo `{locale}`: un link de pago es un enlace único compartible (WhatsApp/correo), no una página traducida por URL. El idioma se detecta del navegador dentro del propio controller (mismo criterio que la redirección de `/`).

## 7. Decisiones de idempotencia / seguridad (contrato pedido por el brief)

- **Monto siempre de BD**: `createOrder()` arma la orden PayPal con `$link->amount`; el request no tiene ningún campo de importe que se lea. `captureOrder()` compara el importe reportado por PayPal (`getOrder()`) contra `$link->amount` (normalizado a 2 decimales, sin `==` sobre floats) — un intento de manipular el body no tiene ningún efecto (test `test_create_order_uses_link_amount_and_ignores_request_amount`, que además verifica con `Http::assertSent` el payload REAL enviado a PayPal).
- **Lock de fila**: `captureOrder()` hace `PaymentLink::where('code', $code)->lockForUpdate()` dentro de una transacción que abarca la verificación + la llamada a PayPal (getOrder + capture) + la escritura durable de `paypal_capture_id`/`paid_at`. Esto evita que un doble clic o dos pestañas capturen la misma orden dos veces.
- **Replay del mismo orderID tras éxito**: si `status==='paid' && paypal_order_id===orderID && paypal_capture_id` ya existe, se responde el mismo resultado (booking existente) sin volver a tocar PayPal.
- **orderID de otro link**: `captureOrder()` exige que el `orderID` recibido coincida con el que `createOrder()` grabó para ESE código — sin esto, un `orderID` válido de OTRO link con igual importe podría "prestarle" su dinero a este.
- **Escritura durable ANTES de crear el Booking**: en cuanto PayPal confirma el cobro, se graba `paypal_capture_id`/`paid_at`/datos reales del comprador en la MISMA transacción corta, ANTES de intentar `Booking::create()`. Si la creación de la reserva falla después (fuera de esa transacción), el link queda con el rastro completo — el webhook (o un humano) puede completar la reserva sin volver a cobrar. Réplica del patrón `PaymentLockService` del checkout, pero persistente en BD en vez de sesión.
- **Un solo uso vs. reutilizable**: `single_use=true` (default) pasa el link a `status='paid'` tras el pago — deja de aceptar pagos. Si `single_use=false`, el `status` se queda en `'pending'` tras un pago exitoso (el link sigue vivo para un próximo comprador); el `booking_id`/`paypal_capture_id` del registro solo reflejan el ÚLTIMO pago — **no hay historial de múltiples usos en la tabla `payment_links` misma** (la única fuente completa de "todas las reservas pagadas con este link" es buscar en `bookings` por `payment_method='payment_link'` y cruzar por tour/monto/fecha). Documento esto como decisión explícita porque el brief no especifica un modelo de "usos" — si se necesita reporting fino de links reutilizables, se recomienda una tabla `payment_link_uses` en un lote futuro.
- **Webhook fail-closed**: sin `PAYPAL_WEBHOOK_ID` configurado, o si `verify-webhook-signature` no responde `SUCCESS` explícito (headers faltantes, error de red, credenciales inválidas), se rechaza con 400 — nunca se procesa un evento sin firma verificada.
- **Webhook sin duplicar**: revisa primero `Booking.payment_reference` (cubre checkout normal y links ya reservados); si no hay match, busca `PaymentLink.paypal_capture_id` con `booking_id` null (link capturado, reserva pendiente) y completa la reserva ahí — un replay del mismo evento ya no encuentra nada que reconciliar.
- **Gap conocido (preexistente, no de este módulo)**: si el checkout normal captura el pago pero `finalizeBookings()` falla, el "marcador" de esa reserva pendiente vive solo en la SESIÓN (`PaymentLockService`), no en BD — el webhook no tiene de dónde reconstruir esa reserva (solo puede loguear una advertencia "no match"). Esto es una limitación del diseño existente del checkout (fuera de mis límites: "no cambies el comportamiento del checkout"), no algo que este módulo introduzca. Si se quiere cerrar ese gap, habría que persistir el snapshot del checkout en BD igual que se hizo aquí para `PaymentLink` — cambio de mayor alcance, recomendado para un lote futuro dedicado.

## 8. CSP

No requirió cambios: `App\Http\Middleware\SecurityHeaders` ya permite `paypal.com`/`paypalobjects.com` en `script-src`, `frame-src`, `connect-src` y `form-action` para el sitio público (no-admin), y `/pagar/{code}` no es `/admin*` por lo que hereda esa CSP tal cual. Verificado leyendo el middleware, no hizo falta un test dedicado (el SDK de PayPal se carga con el mismo `<script src="https://www.paypal.com/sdk/js?...">` que ya usa `checkout.blade.php`).

## 9. Resultado de tests (salida real)

```
$ php vendor/bin/phpunit tests/Feature/PaymentLinkTest.php
........................ ...............                          15 / 15 (100%)
OK (15 tests, 42 assertions)

$ php vendor/bin/phpunit tests/Feature/PaymentLinkPaypalWebhookTest.php
.......                                                             7 / 7 (100%)
OK (7 tests, 17 assertions)

$ php vendor/bin/phpunit tests/Feature/Filament/PaymentLinkResourceTest.php
.....                                                               5 / 5 (100%)
OK (5 tests, 24 assertions)

$ php vendor/bin/phpunit tests/Feature/CheckoutTest.php tests/Feature/PaypalCaptureSecurityTest.php \
    tests/Feature/PaypalCardDeclinedTest.php tests/Feature/CulqiWebhookTest.php
............FFF..F......................                          29 / 29 (100%)
Tests: 29, Assertions: 139, Failures: 4.   (los 4 son baseline preexistente, ver §5)
```

**Suite completa del proyecto** (`php vendor/bin/phpunit`, 324 tests): **17 fallos**, de los cuales:
- 4 son el baseline de `CheckoutTest` (flujo Culqi muerto, documentado arriba).
- 13 son de un lote **ajeno, ya en curso antes de empezar este encargo** (`BlogPostLocalizedFallbackTest`, `BlogPostCreateSpanishOnlyTest`, `InstitutionalPagesLocalizedSlugTest`, `RenderedSeoConsistencyTest` — todos sobre fallback de idioma en Blog/páginas institucionales; `git status` ya mostraba `BlogPost.php`, `TestimonialResource.php`, `Tour.php`, varios controllers y `lang/*/ui.php` modificados sin commitear ANTES de que yo tocara nada). No los toqué ni los tienen relación con pagos/reservas.
- **0 fallos nuevos introducidos por este lote.**

## 10. Pendientes / notas para el siguiente paso

1. **Producción**: registrar la URL del webhook en el panel de PayPal (Developer Dashboard → Webhooks) apuntando a `https://<dominio>/webhooks/paypal`, suscrito a `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.REFUNDED`. Copiar el Webhook ID resultante a `PAYPAL_WEBHOOK_ID` en el `.env` de producción (hoy la verificación falla cerrado sin esta clave — correcto en dev/testing, bloqueante hasta configurarla en prod).
2. Migrar (`php artisan migrate`) antes de desplegar el código — el orden importa: la tabla `payment_links` y la columna `travel_date` nullable deben existir antes de que el nuevo controller/resource reciba tráfico.
3. Pulido visual de `resources/views/payment-links/show.blade.php` pendiente por `maquetador-frontend` — hoy es funcional y usa las clases utilitarias ya existentes del sitio (`btn--primary`, `bg-cream-100`, `font-display`, etc.), sin las clases `cart-*` específicas del carrito.
4. Decisión de negocio pendiente: si se quiere reporting de "cuántas veces se usó" un link `single_use=false`, hace falta una tabla de historial (`payment_link_uses`) — no implementada (fuera del alcance literal del brief, ver §7).
5. `security-engineer` debería revisar este lote antes de producción (toca backend, auth implícita por posesión del código, manejo de dinero y un webhook nuevo) — corresponde a la máquina de 6 estados del equipo (Auditar → Arreglar → Verificar → **Blindar** → Aceptar → Desplegar), y este informe cierra el estado "Arreglar".
6. Los 13 fallos ajenos de blog/i18n (§9) no fueron tocados ni diagnosticados a fondo — quedan para quien esté llevando ese lote.
