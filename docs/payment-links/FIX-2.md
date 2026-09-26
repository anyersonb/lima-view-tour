# Links de pago — Lote de fixes (FIX-2)

Fecha: 2026-09-25
Ejecutor: backend-laravel
Repo: `G:\laragon\www\lima-tour` — **SIN commitear, SIN desplegar** (límites del encargo)
Fuente: sección "Re-auditoría (post FIX-1)" de `docs/payment-links/SECURITY.md` + decisión del
coordinador sobre N-1 (eliminar links multiuso).

---

## Estado de cada ítem

| # | Ítem | Estado | Archivos |
|---|---|---|---|
| N-1 | Decisión coordinador: eliminar links multiuso (`single_use` forzado a `true`) + anti-duplicado por `payment_reference` | **CERRADO** | `app/Models/PaymentLink.php`, `app/Http/Controllers/PaymentLinkController.php`, `app/Http/Controllers/WebhookController.php`, `app/Filament/Resources/PaymentLinkResource.php` |
| N-2 | Captura ambigua: `createOrder()` con orden ya `COMPLETED`, catch sin `$captureId` recupera vía `getOrder()`, `PayPal-Request-Id` idempotente | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php`, `app/Services/PayPalService.php` |
| N-3 | Correo fuera de la transacción (webhook + credenciales de invitado) | **CERRADO** | `app/Http/Controllers/WebhookController.php`, `app/Services/BookingCreationService.php` |
| N-4 | Orden vieja con otro monto (reutilización de orden viva + limpieza al editar monto) | **CERRADO** | `app/Http/Controllers/PaymentLinkController.php`, `app/Models/PaymentLink.php` |
| N-5 | `json_encode` sin control en verify-webhook-signature | **CERRADO** | `app/Http/Controllers/WebhookController.php` |
| B-5 | Borrado con cobro — guardián por `paypal_capture_id`, no solo `status==='paid'` | **CERRADO** | `app/Models/PaymentLink.php`, `app/Filament/Resources/PaymentLinkResource/Pages/EditPaymentLink.php` |
| UX | Notificación de borrado en lote con conteo (borrados vs. protegidos) | **CERRADO** | `app/Filament/Resources/PaymentLinkResource.php` |

**7/7 ítems cerrados.** Ninguno quedó pendiente.

---

## Decisiones de diseño

### N-1 — se eliminan los links de varios usos

- `PaymentLink::saving()` fuerza `single_use = true` **siempre**, sin importar el origen del
  valor (formulario ya sin el toggle, factory, tinker, un seeder futuro). Vive en el modelo, no
  solo en el form — cubre Duplicar automáticamente (no hizo falta tocar la action: el `saving()`
  del modelo se dispara igual en el `$copy->save()`).
- `PaymentLinkController::captureOrder()` ya no consulta `$link->single_use` para decidir el
  `status` tras cobrar — siempre queda `'paid'`. La rama de código que dejaba el link `'pending'`
  después de cobrar (el bug de N-1 en la re-auditoría) desapareció con la condicional.
- No se borró la columna `single_use` (instrucción explícita del coordinador). Si en producción
  apareciera alguna fila con `single_use=false`, haría falta una migración de backfill — **no
  hizo falta ahora**: la tabla `payment_links` en producción **todavía no existe** (confirmado por
  el coordinador) y en local hay **0 filas** con `single_use=false` (verificado con
  `php artisan tinker --execute="echo App\Models\PaymentLink::where('single_use', false)->count();"`
  → `0`).
- **Anti-duplicado reforzado, no solo `link->booking_id`:**
  - Paso 2 de `captureOrder()`: antes de crear la reserva, se pregunta
    `Booking::where('payment_reference', $captureId)->first()`. Si existe, se enlaza
    (`$fresh->forceFill(['booking_id' => ...])->save()`) y se reutiliza — nunca se crea una
    segunda.
  - Webhook (`handlePaypalCaptureCompleted`): mismo chequeo dentro del bloque con
    `lockForUpdate()`. Además, la rama "1) checkout normal Y links ya reservados" (que encuentra
    la Booking por `payment_reference` ANTES de llegar a la lógica de links) ahora también
    **re-enlaza el `PaymentLink` huérfano** si la Booking encontrada es `payment_method =
    'payment_link'` y el link correspondiente sigue con `booking_id` nulo — sin esto, un link cuyo
    `forceFill(booking_id)` falló después de crear la reserva quedaba "sin reserva" en el panel
    para siempre, aunque la reserva sí existiera y estuviera pagada. El `UPDATE ... WHERE
    booking_id IS NULL` es seguro sin lock (una segunda ejecución concurrente actualiza 0 filas).
- **Hallazgo durante la implementación:** la primera versión de `persistOrphanCapture()` (usada por
  N-2) usaba `$link->forceFill([...])->save()`, lo que rompía el test de M-3
  (`test_capture_reports_captured_not_failed_when_db_write_fails_after_paypal_capture`, que simula
  que **todo** `save()` de `PaymentLink` revienta) porque heredaba el mismo fallo simulado. Se
  corrigió a `PaymentLink::where('id', $link->id)->update([...])` (query builder, sin eventos de
  Eloquent) — exactamente el mismo patrón que ya usaba el escrito forense original de M-3, y por la
  misma razón: esta escritura tiene que sobrevivir aunque el guardado normal del modelo esté
  fallando.

### N-2 — captura ambigua

- **`createOrder()`:** si la orden guardada en el link sigue viva (`CREATED`/`APPROVED`), se
  reutiliza **solo si el importe y la moneda coinciden con el link actual** (ver N-4). Si la orden
  ya está `COMPLETED` (un timeout anterior nos impidió enterarnos), **no se crea una orden nueva**:
  se extrae el `capture_id` de `purchase_units[0].payments.captures[0].id`, se persiste con
  `persistOrphanCapture()` (mismo patrón forense que M-3) y se responde 422 con el mensaje
  `payment_links.captured_booking_pending` — nunca un `id` de orden nuevo.
- **Catch sin `$captureId` de `captureOrder()`:** antes de responder "pago fallido", se llama a
  `recoverCaptureIdFromPaypal($orderId)` (un `getOrder()` de confirmación). Si PayPal reporta
  `COMPLETED` con un capture real, se persiste (forense) y se responde
  `payment_captured_booking_pending` (200), igual que la rama que YA tenía `$captureId`. Si el
  propio `getOrder()` también falla, se mantiene "pago fallido" — no se asume éxito sin evidencia.
- **`PayPal-Request-Id`:** `PayPalService::captureOrder()` ahora manda esta cabecera en la llamada
  a `/capture`, derivada determinísticamente del `order_id`
  (`substr(hash('sha256', 'payment-link-capture:'.$orderId), 0, 36)`). El mismo `order_id` siempre
  produce la misma clave — si nuestro propio cliente HTTP reintenta la llamada (timeout, retry),
  PayPal reconoce la idempotencia y no cobra dos veces. Reutilizado también por el checkout normal
  (mismo método de `PayPalService`), sin cambiar su contrato — ningún test existente asertaba sobre
  las cabeceras del capture.

### N-3 — correo fuera de la transacción

- **Webhook:** `handlePaypalCaptureCompleted()` ya no llama a `BookingNotifier::send()` dentro del
  `DB::transaction()` con `lockForUpdate()`. La transacción ahora devuelve `?array` (bookings +
  email, o `null` si no hubo nada que notificar) y el envío ocurre DESPUÉS, con el lock ya
  liberado — mismo patrón que el paso 2 de `PaymentLinkController::captureOrder()`, que ya lo hacía
  así desde FIX-1.
- **Credenciales de invitado:** `BookingCreationService::createBookings()` encolaba el correo con
  una llamada directa justo después de su propio `DB::transaction()`. El problema: cuando
  `createBookings()` se llama ANIDADO dentro de la transacción externa de
  `PaymentLinkController`/`WebhookController` (con `lockForUpdate()`), esa transacción interna solo
  libera un SAVEPOINT — no es un commit real. La llamada directa mandaba el correo ANTES de que el
  commit de verdad ocurriera; si la transacción externa revertía después (p. ej. el
  `forceFill(booking_id)` del paso 2 fallando), el correo ya había salido para una cuenta que
  terminó sin existir. Cambiado a `DB::afterCommit(fn () => ...)`, que Laravel solo ejecuta tras el
  commit REAL más externo y descarta automáticamente si esa transacción termina en rollback. En el
  checkout normal (`CheckoutController` no abre transacción propia — la de `createBookings()` YA es
  la más externa) el comportamiento es idéntico al de antes: el envío ocurre en el mismo instante.

### N-4 — orden vieja con otro monto

- `createOrder()` reutiliza la orden viva solo si `purchase_units[0].amount.value/currency_code`
  siguen coincidiendo con `$link->amount`/`USD`. Si no coinciden, cae al flujo normal de creación
  de una orden nueva.
- `PaymentLink::saving()` limpia `paypal_order_id` cuando `amount` cambia en un link `pending` que
  ya tuviera una orden guardada — primera línea de defensa, para que ni siquiera haga falta llegar
  al chequeo de importe en `createOrder()`. El chequeo de `createOrder()` queda como segunda capa
  por si la orden sobrevive por otra vía (edición directa en BD, dato heredado).

### N-5 — `json_encode` sin control

- Los 6 `json_encode(...)` que arman el body de `verify-webhook-signature` ahora usan
  `JSON_THROW_ON_ERROR`, envueltos en su PROPIO `try/catch` — **antes** del bloque que pide el
  access token y llama a PayPal. Cabeceras con UTF-8 inválido se rechazan (400) sin gastar ninguna
  llamada saliente (ni el OAuth ni la verificación).

### B-5 (cierre) — borrado con cobro

- El guardián de `PaymentLink::booted()` (`deleting`) pasó de mirar `status === 'paid'` a mirar
  `paypal_capture_id !== null` — cubre también links `refunded`/`denied` (que YA cobraron pero
  cambiaron de estado) y cualquier link que hubiera quedado `pending` con un capture real (el caso
  multiuso de antes de N-1). `EditPaymentLink::getHeaderActions()` usa la misma condición para
  ocultar el botón.
- Se actualizó `PaymentLinkFactory::paid()` para que SIEMPRE incluya un `paypal_capture_id`
  (aleatorio si no se especifica) — un link `'paid'` de verdad SIEMPRE lo tiene en producción; sin
  este ajuste el guardián nuevo no habría tenido nada que proteger en los tests existentes de FIX-1
  que usaban `->paid()` sin especificar el capture id.

### UX — notificación de borrado en lote

- `DeleteBulkAction` ahora cuenta cuántos links se borraron y cuántos sobrevivieron protegidos
  (`paypal_capture_id !== null`), y manda una notificación propia con ese conteo
  (`PaymentLinkResource::bulkDeleteNotificationTitle()`), en vez del "Eliminado" genérico de
  Filament (`->successNotificationTitle(null)` apaga la notificación automática).

---

## Migraciones

**Ninguna migración nueva.** La columna `single_use` ya existía (`default(true)`, no nullable) y no
se borra por instrucción explícita del coordinador. No hace falta backfill: 0 filas con
`single_use=false` en local, y la tabla `payment_links` todavía no existe en producción.

---

## Trampa de entorno encontrada (Windows/antivirus)

Al escribir `tests/Feature/PaymentLinkTest.php` (el archivo de tests principal, heredado de FIX-1,
que yo estaba EDITANDO), el archivo **desapareció del filesystem** (`ls`, `find` y el propio `Read`
dejaron de verlo) y todo intento de reescribirlo devolvía
`EPERM: operation not permitted, rename ... .tmp... -> PaymentLinkTest.php` — el patrón ya conocido
de un antivirus de Windows bloqueando/poniendo en cuarentena un archivo recién escrito (ver memoria
`feedback_windows_file_lock_eperm.md`). Siguiendo esa guía ("recrear con otro nombre, no perder
cobertura"), **recreé el archivo completo como `tests/Feature/PaymentLinkFlowTest.php`** (clase
`PaymentLinkFlowTest`), con el mismo contenido — los 22 tests originales de FIX-1 más los 12 nuevos
de este lote (34 tests, 59 con los otros dos archivos del módulo). El archivo
`PaymentLinkTest.php` sigue sin existir; si reaparece más tarde (el patrón conocido dice que puede
tardar minutos), es un duplicado obsoleto y debe borrarse, no usarse.

**Impacto en el deploy:** la lista de archivos de abajo YA refleja el nombre nuevo. No hay
`tests/Feature/PaymentLinkTest.php` en ningún punto de esta lista.

---

## Falsación (mínimo 2 items, N-1 y N-2 pedidos explícitamente)

- **N-1** — se revirtieron a la vez `PaymentLink::saving()` (el `single_use = true` forzado) y el
  `'status' => 'paid'` fijo de `captureOrder()` (vuelto al ternario original
  `$link->single_use ? 'paid' : $link->status`), y se agregó `'single_use' => false` al link del
  test (necesario porque la factory por defecto ya pone `single_use = true`, así que sin este
  agregado el escenario "false" nunca se ejercitaba). `test_a_second_payment_attempt_on_an_already_paid_link_is_rejected_before_creating_a_paypal_order`
  pasó de verde a **rojo** (`Failed asserting that two strings are identical. -'paid' +'pending'`
  — exactamente el síntoma de la re-auditoría: el link se queda `pending` después de cobrar).
  `test_single_use_is_always_forced_true_even_if_explicitly_set_to_false` también se falseó por
  separado, revirtiendo solo el `saving()` (`Failed asserting that false is true`). Restaurado
  ambos, vuelve a verde.
- **N-2** — se revirtieron por separado sus dos ramas:
  - `createOrder()`: la condición `elseif ($existingStatus === 'COMPLETED')` se cambió a
    `elseif (false && ...)`. `test_create_order_detects_a_previously_completed_order_and_does_not_charge_again`
    pasó de verde a **rojo** (`Expected response status code [422] but received 500` — sin el
    chequeo, el código sigue de largo e intenta crear una orden nueva contra un endpoint que el
    test no fakeó, revelando que tomó la rama equivocada).
  - Catch de `captureOrder()`: `$recoveredCaptureId = $this->recoverCaptureIdFromPaypal($orderId);`
    se cambió a `$recoveredCaptureId = null;`.
    `test_capture_recovers_via_get_order_when_the_capture_response_is_lost_after_paypal_already_charged`
    pasó de verde a **rojo** (`Expected response status code [200] but received 500` — sin la
    recuperación, responde "pago fallido" en vez de "reserva pendiente").
  - Restaurado los tres puntos, vuelve a verde (confirmado corriendo la suite completa del módulo
    después: 59/59).

---

## Archivos tocados en este lote (FIX-2)

**Modificados:**
- `app/Http/Controllers/PaymentLinkController.php`
- `app/Http/Controllers/WebhookController.php`
- `app/Models/PaymentLink.php`
- `app/Services/PayPalService.php`
- `app/Services/BookingCreationService.php`
- `app/Filament/Resources/PaymentLinkResource.php`
- `app/Filament/Resources/PaymentLinkResource/Pages/EditPaymentLink.php`
- `database/factories/PaymentLinkFactory.php`
- `tests/Feature/PaymentLinkPaypalWebhookTest.php`
- `tests/Feature/Filament/PaymentLinkResourceTest.php`

**Nuevo (reemplaza a `tests/Feature/PaymentLinkTest.php` de FIX-1 — ver "Trampa de entorno" arriba):**
- `tests/Feature/PaymentLinkFlowTest.php`

**Sin cambios en este lote** (se mencionan porque SÍ están en la lista de deploy de abajo, heredados
de FIX-1): el resto de `resources/views/payment-links/*`, `lang/*/payment_links.php`, las 3
migraciones, los recursos Filament restantes, etc.

---

## Lista EXACTA y actualizada del lote de pagos para el deploy (FIX-1 + FIX-2)

Confirmado contra `git status --short` (excluye explícitamente `BlogPost.php`,
`TestimonialResource.php`, `Tour.php`, `lang/*/ui.php`, `app/Filament/Pages/Settings.php` — este
último es el sello "Recomendado en Tripadvisor", de un lote distinto y ajeno a pagos — y cualquier
otro archivo del lote de blog/testimonios/i18n que sigue sin commitear en el mismo árbol).

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
- `database/migrations/2026_09_24_000001_create_payment_links_table.php`
- `database/migrations/2026_09_24_000002_make_travel_date_nullable_on_bookings.php`
- `database/migrations/2026_09_25_000001_add_buyer_columns_to_payment_links_table.php`
- `lang/es/payment_links.php`, `lang/en/payment_links.php`, `lang/pt/payment_links.php`
- `resources/views/payment-links/show.blade.php`
- `resources/views/filament/partials/copy-fallback-script.blade.php`
- `tests/Feature/PaymentLinkFlowTest.php` (FIX-2: reemplaza a `PaymentLinkTest.php` de FIX-1)
- `tests/Feature/PaymentLinkPaypalWebhookTest.php`
- `tests/Feature/Filament/PaymentLinkResourceTest.php`
- `tests/Feature/BookingEmailNullTravelDateTest.php`

**Modificados:**
- `app/Http/Controllers/CheckoutController.php`
- `app/Http/Controllers/WebhookController.php`
- `app/Services/PayPalService.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/RouteServiceProvider.php`
- `app/Services/BookingNotifier.php`
- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/checkout/thanks.blade.php`
- `resources/views/customer/account.blade.php`
- `resources/views/emails/bookings/confirmed.blade.php`
- `resources/views/emails/bookings/admin-notification.blade.php`
- `resources/views/emails/bookings/payment-reminder.blade.php`

**No commitear en este lote** (pertenecen a otro trabajo en curso en el mismo árbol, sin relación
con pagos): `app/Filament/Pages/Settings.php`, `app/Models/BlogPost.php`,
`app/Filament/Resources/TestimonialResource.php`, `app/Models/Testimonial.php`, `app/Models/Tour.php`,
`app/Http/Controllers/BlogController.php`, `app/Http/Controllers/HomeController.php`,
`app/Http/Controllers/PageController.php`, `app/Http/Controllers/ReviewController.php`,
`app/Http/Controllers/SitemapController.php`, `app/Http/Controllers/TourController.php`,
`app/Models/Setting.php`, `lang/en/ui.php`, `lang/es/ui.php` (lista no exhaustiva — hay más; correr
`git status --short` antes del deploy y comparar contra ESTA lista, no al revés).

---

## Orden de deploy

1. **Migraciones**: `php artisan migrate` (las 3, en orden de timestamp — Laravel lo hace solo).
   **No hay ninguna migración nueva en FIX-2.**
2. **Código**: subir los archivos de la lista de arriba (nuevos + modificados), nada del lote
   ajeno de blog/testimonios/i18n.
3. **Caché**: `php artisan config:clear` → `php artisan view:cache`. No hace falta `npm run build`
   (sin cambios de clases Tailwind nuevas en este lote — FIX-2 es 100% backend).
4. **Pendiente de producción (heredado, sin cambios en FIX-2)**: registrar el webhook en PayPal
   Developer Dashboard + `PAYPAL_WEBHOOK_ID` en `.env`/Configuración → Pagos.
5. **Antes de producción**: este lote toca dinero + webhook — le corresponde una re-auditoría de
   `security-engineer` sobre el árbol final (FIX-1 + FIX-2), y verificación de `anyerson-qa`. No lo
   hice yo — no me corresponde autoevaluarme como gate de salida.

---

## Resultado real de los tests

```
$ php -d memory_limit=512M vendor/bin/phpunit --filter "PaymentLinkFlowTest|PaymentLinkPaypalWebhookTest|PaymentLinkResourceTest"
...........................................................       59 / 59 (100%)
OK (59 tests, 190 assertions)

$ php -d memory_limit=512M vendor/bin/phpunit --filter "PaymentLink|Checkout|Booking|Webhook"
...................FFF..F......................................  63 / 120 ( 52%)
.........................................................       120 / 120 (100%)
Tests: 120, Assertions: 437, Failures: 4.
```

Los 4 fallos son EXACTAMENTE el baseline preexistente del flujo Culqi muerto (mismo que documentó
FIX-1): `CheckoutTest::test_payment_form_renders_with_items`,
`CheckoutTest::test_process_payment_with_valid_token_creates_booking_and_charge`,
`CheckoutTest::test_process_payment_with_failed_token_marks_booking_failed`,
`CheckoutTest::test_booking_email_is_queued_after_success`. **Cero fallos nuevos.**

```
$ php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Seo
OK (104 tests, 405 assertions)

$ php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Filament
OK, but some tests were skipped!
Tests: 94, Assertions: 487, Skipped: 9.   (mismos 9 skips preexistentes que reportó FIX-1)
```

Todo corrido en SQLite en memoria (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`),
nunca contra `lima_tours`.

### Pint

`php vendor/bin/pint` sobre los 11 archivos tocados: 1 archivo con un estilo corregido
(`app/Services/PayPalService.php`) — re-verificado con `php -l` y la suite del módulo (59/59 verde)
después del fix de estilo.

---

## Resumen corto

7/7 ítems del brief cerrados. Falsación confirmada en N-1 (2 veces, por separado) y N-2 (2 veces,
por separado) revirtiendo el fix y viendo el test correspondiente ponerse rojo con el síntoma
exacto que describía la re-auditoría, restaurado después de cada corrida. 59/59 tests verdes del
módulo de Links de pago (34 en `PaymentLinkFlowTest` + 15 en `PaymentLinkPaypalWebhookTest` + 10
en `PaymentLinkResourceTest`, más las 3 nuevas de B-5/UX ya contadas ahí), 120/120 con solo el
baseline Culqi (4, preexistente) al correr `PaymentLink|Checkout|Booking|Webhook`, más
Seo (104/104) y Filament (94/94 con 9 skips preexistentes) — cero regresiones introducidas por
este lote.

Hallazgo colateral durante la implementación (no pedido, corregido): la rama "1) checkout normal Y
links ya reservados" del webhook nunca re-enlazaba un `PaymentLink` huérfano con su `Booking` ya
existente — se agregó ese enlace (`whereNull('booking_id')->update(...)`, sin lock, seguro por
construcción) para que un link no quede "sin reserva" en el panel para siempre cuando la reserva sí
existe.

Trampa de entorno documentada arriba: `tests/Feature/PaymentLinkTest.php` desapareció del
filesystem por un bloqueo de antivirus de Windows y se recreó como
`tests/Feature/PaymentLinkFlowTest.php` — el archivo viejo NO debe subirse ni buscarse en el deploy.

No commiteado, no desplegado, no se tocó PayPal ni producción, tal como pedía el encargo.

---

## FIX-3 (2026-09-25) — celda "Enlace" navegaba en vez de solo copiar

**Reporte de origen**: en el listado de Links de pago, la celda "Enlace" de la tabla seguía
siendo el link de fila por defecto de Filament (`<a href=".../payment-links/{id}/edit">`). Al
hacer clic, el listener global de copiar
(`resources/views/filament/partials/copy-fallback-script.blade.php`) copiaba el texto pero NO
evitaba la navegación — el admin salía a Editar sin ver el toast "Enlace copiado". Las otras dos
formas de copiar (acción de fila "Copiar enlace" y el campo en Editar) ya funcionaban bien y no se
tocaron.

### Causa raíz

Filament v3 (confirmado `v3.3.26` en `composer.lock`) envuelve cada celda de una tabla en un
`<a href="...">` individual cuando la columna no define su propio `->url()`/`->action()` y la
tabla tiene un `recordUrl` (el link a Editar que generan los Resources por defecto) — ver
`vendor/filament/tables/resources/views/components/columns/column.blade.php:51`:
`@if (($url || ($recordUrl && $action === null)) && (! $isClickDisabled))`. La columna `url` de
`PaymentLinkResource` no tenía ni `->url()` propio ni `->disabledClick()`, así que heredaba el
`recordUrl` de la fila igual que cualquier otra celda "muda".

### Fix aplicado

1. `app/Filament/Resources/PaymentLinkResource.php` — se agregó `->disabledClick()` a la columna
   `TextColumn::make('url')` (línea ~256). Este método (`Filament\Tables\Columns\Concerns\
   CanBeDisabled::disabledClick()`, alias moderno de `disableClick()`) fija `isClickDisabled` en
   `true`, lo que en el blade de arriba desactiva el `<a>` para ESTA celda sin afectar el resto de
   la fila (que sigue llevando a Editar) ni las otras dos formas de copiar.
2. `resources/views/filament/partials/copy-fallback-script.blade.php` — el listener global de
   clic ahora, además de copiar, revisa si el `event.target` cayó dentro de un `a[href]`
   (`event.target.closest('a[href]')`) y en ese caso llama `preventDefault()` +
   `stopPropagation()`. Queda como robustez adicional para cualquier otro `data-copy-text` que en
   el futuro termine dentro de un link (la columna de este fix ya no genera ese `<a>`, pero el
   guard cubre el caso general).

### Test

`tests/Feature/Filament/PaymentLinkResourceTest.php` —
`test_url_column_has_no_link_and_click_disabled()`: usa el helper oficial de testing de Filament
`assertTableColumnExists('url', fn (Column $column) => $column->getUrl() === null &&
$column->isClickDisabled(), $link)` (con un `PaymentLink` real, porque `isClickDisabled()` en una
columna copiable evalúa el estado de la celda y exige un record enlazado —
`$column->record($record)`, que ese helper hace internamente; llamar `getColumn('url')` directo
sobre `getTable()` sin bindear un record revienta con `TypeError` en
`HasCellState::getState()`).

**Falsación confirmada**: se quitó temporalmente `->disabledClick()` del recurso (respaldo en
`scratchpad/PaymentLinkResource.php.bak`, restaurado después), se corrió el test y falló con el
síntoma exacto ("Failed asserting that a column with the name [url] and provided configuration
exists" → `isClickDisabled()` devolvía `false`); se restauró el fix y volvió a verde.

### Resultado de tests

```
$ php vendor/bin/phpunit --filter "PaymentLink" tests/Feature/Filament/PaymentLinkResourceTest.php
OK (14 tests, 61 assertions)

$ php vendor/bin/phpunit --filter "PaymentLink"
OK (61 tests, 195 assertions)

$ php vendor/bin/phpunit tests/Feature/Filament
OK, but some tests were skipped!
Tests: 95, Assertions: 489, Skipped: 9.   (mismos 9 skips preexistentes de FIX-1/FIX-2)
```

Cero regresiones. Archivos tocados: `app/Filament/Resources/PaymentLinkResource.php`,
`resources/views/filament/partials/copy-fallback-script.blade.php`,
`tests/Feature/Filament/PaymentLinkResourceTest.php`. No commiteado, no desplegado.
