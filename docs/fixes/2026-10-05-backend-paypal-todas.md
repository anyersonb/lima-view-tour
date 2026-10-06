# Fix backend: payer PayPal, PII en logs, application_context, contador "Todas" (2026-10-05)

Rama feat/pagos-fechas-doble-cobro-2026-08-28, HEAD c469f3a. Sin commit (working tree compartido).

## Archivos tocados
- app/Http/Controllers/CheckoutController.php:293-335 `buildPayer()` (tarea 1)
- app/Services/PayPalService.php:129-178 reintento + log sin PII en `createOrder()`; helpers `isPayerRejection()` y `safeErrorSummary()` (:208-250) (tareas 1 y 2). Pint reformateó además espaciado/phpdoc del archivo.
- app/Filament/Resources/BookingResource/Pages/ListBookings.php:36 badge en `all` (tarea 4)
- tests/Feature/PaypalPayerSanitizationTest.php (nuevo, 7 tests)
- tests/Feature/Filament/BookingResourceFiltersTest.php: `test_all_tab_has_badge_with_total_bookings`

## Tarea 1 (hallazgo #1)
- Nombre: control chars fuera, given_name/surname truncados a 140 (mb_substr); se omite `name` si queda vacío.
- Correo: `FILTER_VALIDATE_EMAIL` (sin unicode; rechaza `a@b`, no ASCII) y <= 254; si no, se omite.
- Teléfono: 8-14 dígitos (antes 8-15); si no, se omite.
- Si PayPal responde 400/422 con `details[].field` que contiene `payer`, `createOrder()` reintenta UNA vez sin `payer` (conserva importe y application_context). Otro 4xx no se reintenta. Si el reintento falla, 500 como antes (error real no relacionado con el payer). Importe sin tocar (sigue de `$this->cart->total()`).

## Tarea 2 (hallazgo #2)
`paypal.create_order.failed` ya no loguea `body`; registra status + `{name, debug_id, details[].field/issue}`. El mensaje de la excepción (que el controller loguea) usa el mismo resumen. Alcance: solo createOrder; `getOrder`/`captureOrder`/token siguen logueando `body` (no pedido; el body de capture puede incluir payer: candidato a otro lote).

## Tarea 3 (hallazgo #3): NO migrado
Se deja `application_context` (NO_SHIPPING / PAY_NOW). Motivo: no pude consultar la doc de PayPal desde este entorno (sin acceso web) y el brief exige no migrar si hay riesgo de romper FUNDING.CARD. Es conocido que declarar `payment_source.paypal` en create-order ata la orden a la fuente PayPal y puede impedir el pago como tarjeta de invitado desde los Smart Buttons; sin sandbox no se puede descartar. Fuente a confirmar: PayPal Orders v2 `payment_source.paypal.experience_context` (developer.paypal.com/docs/api/orders/v2/) y guía de Standard Card Fields/Smart Buttons. NO VERIFICADO contra sandbox. `application_context` sigue aceptado por PayPal. Pendiente menor sin tocar: solo se envía si hay payer no vacío (con payer vacío PayPal pediría dirección); payment-links comparte el servicio, por eso no se forzó.

## Tarea 4
Badge `Booking::query()->count()` en `all`. Test comprueba 3 -> 4 al crear otra reserva y contraste con el badge de `paid` (=1).

## Tests
Comando (phpunit.xml usa sqlite :memory:, no toca `lima_tours`):
`php -d memory_limit=512M vendor/bin/phpunit --filter 'Paypal|Checkout|PaymentLink|Culqi|Booking'`
Resultado literal: `OK, but some tests were skipped! Tests: 143, Assertions: 538, Skipped: 4.` (los 4 skips son los Culqi, intactos).
Archivos nuevos/ajustados solos: `OK (28 tests, 108 assertions)`.
No probé el rojo de los tests nuevos revirtiendo el código (sin stash por instrucción); el test de logs trae control positivo (exige que sí aparezcan `paypal.create_order.failed`, debug_id e issue) y el de reintento exige 2 llamadas con y sin `payer`.

## Adenda: logs sin PII en token, getOrder y captureOrder
PayPalService.php: los 3 `Log::error` y los mensajes de excepción usan `safeErrorSummary()` (status, name/error, debug_id, details[].field/issue); ya no queda ningún `body()` en el archivo. Test nuevo: `test_capture_and_get_order_error_logs_contain_no_payer_pii` (body con payer email/nombre; con control positivo de debug_id e issue).
Mismo comando de phpunit. Resultado literal: `OK, but some tests were skipped! Tests: 144, Assertions: 545, Skipped: 4.`
