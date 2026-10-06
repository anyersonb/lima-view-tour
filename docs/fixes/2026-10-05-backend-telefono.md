# B1 - Telefono de 16+ digitos en create-order (2026-10-05)

## Decision
- `paypalCreateOrder`: `customer_phone` pasa de `regex:/^\+?\d{7,15}$/` a `['nullable','string','max:50']`. El telefono NO se guarda en esta ruta (solo alimenta `payer`), asi que no bloquea el pago: `buildPayer()` lo omite si no tiene 8-14 digitos. Un telefono con espacios ("+51 987 654 321") tambien fallaba el regex antes; ahora pasa.
- `paypalCaptureOrder` y `ProcessPaymentRequest`: el telefono SI se persiste en la reserva y es obligatorio -> se conserva `required` + regex 7-15 digitos. Cambio: el mensaje sale de lang (`checkout_paypal.phone_invalid`, es/en/pt) para `customer_phone.regex` y `customer_phone.required`, con estructura estandar `{message, errors:{customer_phone:[...]}}` (422).
- Nota: el campo se llama `customer_phone` (no `phone`); la clave de error es `errors.customer_phone`.

## Archivos
- app/Http/Controllers/CheckoutController.php (create-order + mensajes capture)
- app/Http/Requests/ProcessPaymentRequest.php (mensajes)
- lang/{es,en,pt}/checkout_paypal.php (`phone_invalid`)
- tests/Feature/PaypalPayerSanitizationTest.php (2 tests nuevos)

## Verificacion
- `phpunit --filter 'Paypal|Checkout|Booking'`: 111 tests, 444 assertions, 1 fallo inicial (mi caso de test con 13 digitos, corregido) -> `PaypalPayerSanitizationTest` OK (10 tests, 60 assertions).
- Suite completa: `OK, but some tests were skipped! Tests: 408, Assertions: 3151, Skipped: 13.`
- Los 2 tests nuevos prueban: 16 digitos y formato con espacios -> 200 + `payer` sin `phone`; capture con telefono invalido -> 422 con `errors.customer_phone.0` igual a la traduccion en es/en/pt.

## Para el front
Mostrar `errors.customer_phone[0]` (ya viene traducido por el locale de la URL); clave lang equivalente `checkout_paypal.phone_invalid`. En create-order ya no hay error de telefono.
