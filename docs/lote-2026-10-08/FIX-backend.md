# FIX backend · lote 2026-10-08

## Archivos tocados
- app/Console/Kernel.php
- app/Console/Commands/SendBookingPaymentReminders.php
- app/Filament/Resources/BookingResource/Pages/ListBookings.php

## Diff resumido
**Kernel.php** (~:23): `->dailyAt('09:00')` pasa a `->dailyAt('13:00')->timezone('America/Lima')`. La timezone global de la app (UTC) no se toca.

**SendBookingPaymentReminders.php**: se añade `use App\Support\BookingCalendar;`. `$today = now()->startOfDay()` pasa a `$today = BookingCalendar::today()`. Es el helper existente del lote 27/08 y usa `config('booking.timezone')` con fallback a America/Lima. `$target` se deriva de `$today`, así que la ventana [hoy, hoy+days_before] queda en hora de Lima. `payment_reminder_sent_at => now()` se deja en UTC porque es un timestamp, no una fecha de negocio.

**ListBookings.php**: el tab `'tomorrow'` pasa al primer lugar y el resto conserva su orden. Se añade `getDefaultActiveTab(): string | int | null { return 'tomorrow'; }`. La firma coincide con vendor/filament/filament/src/Resources/Concerns/HasTabs.php:42. El filtro y el badge de "tomorrow" ya usaban `BookingCalendar::today()->addDay()` (hora de Lima), sin cambios. Los badges siguen funcionando.

## Verificación
`schedule:list`:
```
*/5 * * * *  php artisan carts:send-recovery ........ Next Due: en 3 minutos
0   13 * * * php artisan bookings:send-payment-reminders ... Next Due: en 17 horas
```
`bookings:send-payment-reminders --dry`: corre sin error y responde "No hay reservas por recordar." (BD local sin candidatas). La rama con reservas (`[dry] ...`) no se ejercitó.

Tinker: `getDefaultActiveTab()` devuelve `tomorrow`. Orden de tabs: tomorrow, all, paid, pending_payment, failed, at_risk, today, this_week.

No verificado: no corrí la suite de tests. No se desplegó ni se hizo commit.

Nota: la etiqueta "Next Due: en 17 horas" la calcula Laravel respetando la timezone del evento. El `13:00` del cron se lee en hora de Lima, no UTC. Además, el cron del servidor debe llamar a `schedule:run` cada minuto.

## Correo inmediato al reservar con "pagar luego"
Sí, sale un correo inmediato al cliente.
- app/Http/Controllers/CheckoutController.php:149 `finalizeBookings(..., 'pay_later', null, false)` llega a :741 `$this->notifier->send($bookings, $paid, $email)`.
- app/Services/BookingNotifier.php:37 `sendCustomerConfirmation()` envía `BookingConfirmed` al cliente (Mail::to en :80-81). Es incondicional respecto a `$paid`. Con pagar luego es el correo de reserva pendiente.
- BookingNotifier.php:38 además envía `BookingNotificationAdmin` a administración con `paymentTiming = 'later'` (:108-111).
- Si el cliente es nuevo, BookingCreationService.php:175-176 envía además `AccountCredentials`.
- El recordatorio de las 13:00 es un tercer correo distinto: `BookingPaymentReminder`.
