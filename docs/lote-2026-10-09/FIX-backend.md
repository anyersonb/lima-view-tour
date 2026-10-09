# FIX backend — lote 2026-10-09

## 1 · Recordatorio de pago de último momento
- `app/Console/Commands/SendBookingPaymentReminders.php`: nueva opción `--urgent` (tour hoy/mañana, fecha Lima). En AMBOS modos exige `created_at <= now() - urgent_delay_hours` (2h). `--urgent` excluye tours de HOY cuya `tours.departure_time` sea una hora legible (`HH:MM [am/pm]`) y ya pasó. Un solo recordatorio por reserva (`whereNull payment_reminder_sent_at`), mismo agrupado email+fecha, mismo mailable.
- `app/Console/Kernel.php`: tarea `--urgent` cada 15 min, `between(07:00, 22:00)`, tz `BookingCalendar::timezone()`, withoutOverlapping. La tanda diaria 13:00 también usa `BookingCalendar::timezone()` (I-1).
- `config/cart.php` payment_reminder: `urgent_delay_hours=2`, `urgent_window_start=07:00`, `urgent_window_end=22:00`.
- Limitación: `departure_time` es texto libre; si no es una hora clara ("Todo el día") no se puede excluir y se envía. Reserva creada a las 23:00 para mañana: sale a las 07:00.
- Efecto lateral: la tanda diaria también espera 2h desde la creación (una reserva creada a las 12:30 sale al día siguiente, aún en ventana).
- Verificación: `schedule:list` muestra ambas (`0 13 * * *` y `*/15 * * * * --urgent`); `--dry` y `--urgent --dry` sin error.
- Tests: `tests/Feature/BookingUrgentReminderTest.php` (11, verdes, SQLite memoria): mañana 1h no / 3h sí, hoy 3h sí, +2 días solo diaria, ya enviada no, urgent+diaria = 1 envío, tour ya salido no, hora ilegible sí, dry.

## 2 · Bajos de seguridad
- B-1: `app/Filament/Resources/PaymentLinkResource.php` Select locale: `->in(array_keys(self::LOCALE_OPTIONS))`.
- B-2: `resources/views/payment-links/show.blade.php`: URLs calculadas en `@php` y `@json($plCreateUrl)`/`@json($plCaptureUrl)`. Render del link 4 local: 200, mismas URLs con `?lang=`.
- I-1: Kernel (ver arriba).

## 3 · "1 adultos"
- `lang/{es,en,pt}/checkout.php`: nuevas `adults_count` y `children_count` (trans_choice); `pax_breakdown` ahora es `(:adults, :children)`.
- `resources/views/checkout/thanks.blade.php` (~l.76): único uso de `pax_breakdown`. Resultado: "(1 adulto, 1 niño)", "(2 adultos, 0 niños)" (+en/pt). Test en BookingUrgentReminderTest.
- Correos (confirmed, payment-reminder, admin-notification) usan "adulto(s)/niño(s)" propio, no esta clave: sin cambios.

## 4 · Botón "Reenviar recordatorio de pago"
- `app/Services/BookingNotifier.php`: `resendPaymentReminder(Booking, ?email)`: envía `BookingPaymentReminder` con SOLO esa reserva (decisión: no agrupa, el manual es explícito), `->locale($booking->locale)`, marca `payment_reminder_sent_at` solo si el envío no lanzó. `resendCustomerConfirmation` ahora también usa `->locale($booking->locale)` (bajo pendiente resuelto).
- `app/Filament/Resources/BookingResource.php`: acción `resendPaymentReminder` junto a "Reenviar correo", visible solo con `payment_status=pending` y `status!=cancelled` (cualquier método, incl. Link de pago), con re-chequeo en servidor; modal con email editable y descripción; notificación éxito/error. Columna toggleable "Recordatorio enviado" (`payment_reminder_sent_at`, formato d/m/Y H:i en tz Lima, oculta por defecto).
- Colas: ni `BookingConfirmed` ni `BookingPaymentReminder` implementan ShouldQueue; `.env` QUEUE_CONNECTION=sync. Test `assertNotQueued` ambos.
- Tests: `tests/Feature/Filament/BookingResendPaymentReminderTest.php` (6, verdes): envía+locale+sent_at, link de pago, oculta en pagada/cancelada, fallo no marca, confirmación usa locale de reserva, columna existe.

## Resultados
- Nuevos: 17/17 verdes. Suite filtrada `PaymentLink|Checkout|Booking|Reminder|Thanks|Paypal`: 167 tests, 14 fallos, TODOS en `BookingResourceFiltersTest`. Causa AJENA a este lote: cambio sin commitear en `app/Filament/Resources/BookingResource/Pages/ListBookings.php` (pestaña por defecto 'tomorrow'). Con ese archivo stasheado, los 21 tests pasan. Hay que resolverlo (los tests deberían fijar la pestaña 'all') o revertirlo antes de desplegar.
- BD: tests corren en SQLite memoria (phpunit.xml); `lima_tours` intacta (36 reservas, 1 link).

## Archivos para FTP
- app/Console/Commands/SendBookingPaymentReminders.php
- app/Console/Kernel.php
- config/cart.php
- app/Filament/Resources/PaymentLinkResource.php
- app/Filament/Resources/BookingResource.php
- app/Services/BookingNotifier.php
- resources/views/payment-links/show.blade.php
- resources/views/checkout/thanks.blade.php
- lang/es/checkout.php, lang/en/checkout.php, lang/pt/checkout.php

Tests (no suben): tests/Feature/BookingUrgentReminderTest.php, tests/Feature/Filament/BookingResendPaymentReminderTest.php

Migración/SQL: NINGUNA. Post-deploy: `php artisan config:clear` / cache (config nueva), `php artisan view:clear`, y opcache-reset.php.

## Corrección posterior
- `ListBookings` (tab "Mañana" por defecto, lote anterior, en prod) NO se toca. `tests/Feature/Filament/BookingResourceFiltersTest.php`: cada `Livewire::test(ListBookings::class)` fija `->set('activeTab','all')`; nuevo `test_default_tab_is_tomorrow`. Suite filtrada (PaymentLink|Checkout|Booking|Reminder|Thanks|Paypal) en SQLite: 168 tests, 653 assertions, 0 fallos, 4 skipped (preexistentes).
- Corrección del punto anterior: los 14 fallos eran de ese archivo de tests, no un defecto de código.

## Config cacheada en prod (`bootstrap/cache/config.php`)
Las claves nuevas de `config/cart.php` NO fallan cerrado: si el caché es viejo, el código usa defaults. Comando: `$cfg['urgent_delay_hours'] ?? 2` (y `max(0, ...)`). Kernel: `config('cart.payment_reminder.urgent_window_start', '07:00')` / `end '22:00'`. Con caché viejo el comportamiento es idéntico a los valores nuevos (2h, 07:00-22:00); se pueden cambiar solo tras `php artisan config:cache`. `config:clear`/`config:cache` sigue recomendado tras el deploy.

## Correcciones SECURITY (docs/lote-2026-10-09/SECURITY.md)
- B-1: `SendBookingPaymentReminders.php`: reclamo atómico por reserva (`UPDATE ... WHERE id AND payment_reminder_sent_at IS NULL`, cuenta 1) ANTES de enviar; solo se envían las reservas reclamadas; si el envío falla se revierte a NULL. Test `test_concurrent_run_that_loses_the_claim_sends_nothing` (un listener `retrieved` simula otro proceso que reclama entre la lectura y el reclamo: 0 envíos).
- B-2: sin migración: contador de fallos por reserva en Cache (`payment_reminder:failures:{id}`, TTL 3 días, `cart.payment_reminder.max_attempts`=3, default 3 en código). Se limpia al enviar bien. Test: 5 corridas con SMTP caído = 3 intentos, sent_at sigue NULL. Limitación: depende del driver de cache (file en prod, un servidor: sirve; `cache:clear` reinicia el contador).
- B-3: `BookingNotifier` (reenvío de recordatorio y de confirmación) registra `by` (auth()->id()), `email` destino y `customer_email_original`.
- B-4: `resendPaymentReminder` marca `payment_reminder_sent_at` solo si el destino coincide con `customer_email` (strcasecmp + trim). Log incluye `marked_sent`. Test con otra dirección = no marca; misma con otra capitalización = marca.
- B-5: `BookingResource` Select locale `->in(['es','en','pt'])`; `BookingNotifier::safeLocale()` cae a 'es' fuera de `config('app.supported_locales')`. Test locale 'xx' = es.
- I-1: la notificación de error de la acción manual es genérica; el detalle va a `Log::warning('booking.payment_reminder.manual_failed')` (con `by`). El comando ya no imprime el mensaje técnico por consola.
- Suite filtrada (PaymentLink|Checkout|Booking|Reminder|Thanks|Paypal), SQLite: 173 tests, 691 assertions, 0 fallos, 4 skipped.
- Archivos añadidos a la lista FTP: ninguno nuevo (ya estaban SendBookingPaymentReminders.php, BookingNotifier.php, BookingResource.php, config/cart.php). Tests tocados: BookingUrgentReminderTest.php, Filament/BookingResendPaymentReminderTest.php. Sin migraciones.
