# Auditoría de seguridad: lote 2026-10-09 (estado 4)

## Veredicto: NO BLOQUEA (APROBADO CON OBSERVACIONES)

0 críticos · 0 altos · 0 medios · 5 bajos · 1 informativo.

Revisión de solo lectura del diff sin commitear (`git diff`). No se editó código, no se navegó, no se tocó la BD ni se corrió `artisan test`. Los mtime de los archivos auditados no cambiaron durante la revisión (último cambio: 21:00:52).

---

## Hallazgos bajos

### B-1 [BAJO] Las tandas diaria y urgente arrancan juntas a las 13:00 y pueden mandar el mismo recordatorio dos veces
- Ubicación: `app/Console/Kernel.php:24` (`dailyAt('13:00')`) y `:33` (`everyFifteenMinutes()`), las dos con `runInBackground()`. La selección está en `app/Console/Commands/SendBookingPaymentReminders.php:75`, y la marca después del envío en `:112-113`.
- Causa: `withoutOverlapping()` genera un mutex por cada cadena de comando. `bookings:send-payment-reminders` y `... --urgent` tienen mutex distintos, así que no se excluyen entre sí. A las 13:00 de Lima arrancan dos procesos en paralelo. Los dos leen `whereNull('payment_reminder_sent_at')` antes de que cualquiera haga el update, y el envío SMTP es síncrono y tarda segundos.
- Alcance: reservas para hoy o mañana creadas entre las 10:45 y las 11:00 de Lima. Esas son las que la corrida urgente de las 12:45 todavía no veía. También entran las que fallaron en una corrida anterior. Pasa todos los días y no se puede explotar desde fuera. El impacto es un correo duplicado al cliente.
- El test "urgent+diaria = 1 envío" ejecuta los dos modos uno detrás del otro, así que no cubre la concurrencia.
- Remedio: reclamar las reservas de forma atómica antes de enviar. Hacer `$claimed = Booking::whereIn('id', $ids)->whereNull('payment_reminder_sent_at')->update(['payment_reminder_sent_at' => now()])` y enviar solo si `$claimed === count($ids)`. Si el envío falla, revertir con `update(['payment_reminder_sent_at' => null])`. Una alternativa más barata, aunque parcial, es mover la diaria fuera de los cuartos de hora (por ejemplo `13:07`). No basta si la urgente sigue enviando a esa hora.

### B-2 [BAJO] Una dirección que falla siempre se reintenta cada 15 minutos hasta el día del tour
- Ubicación: `SendBookingPaymentReminders.php:122-128`. El `catch` registra el error y no marca nada, cosa correcta para no perder el recordatorio.
- Efecto: si el SMTP rechaza siempre a un destinatario, la tarea urgente lo vuelve a intentar unas 60 veces al día, y lo mismo con cada reserva así. El cliente no recibe spam porque el envío falla. El problema es el ruido en el log y que, con un proveedor que cuenta rechazos, puede dañar la reputación del remitente. El fallo a mitad de lote está bien aislado: cada grupo tiene su try/catch y los demás siguen.
- Remedio: añadir `payment_reminder_attempts` o `payment_reminder_failed_at` y dejar de reintentar después de N fallos o de una ventana (por ejemplo 3 intentos o 2 h).

### B-3 [BAJO] El reenvío manual no registra quién lo hizo
- Ubicación: `app/Services/BookingNotifier.php:94-97`. El log guarda `email` y `booking`, pero no `auth()->id()`.
- Contexto de autorización: el panel no tiene roles ni policies. `AuthServiceProvider::$policies` está vacío y la única puerta es `User::canAccessPanel()`, que pide un correo `@webtilia.com` o `@limaviewtours.com` (`app/Models/User.php:32-36`). Los clientes son otro modelo (`Customer`), así que el registro público no da acceso al panel. Cualquier usuario del panel puede usar la acción, igual que el "Reenviar correo" que ya existía (`BookingResource.php:621-651`).
- Correo editable: se puede mandar el recordatorio (plantilla fija y datos de esa reserva) a cualquier dirección. Eso deja que un empleado envíe datos de la reserva a un tercero. No hay inyección de cabeceras: `->email()` aplica la regla `email` de Laravel (RFC) y Symfony `Address` valida otra vez. No hay rate limit, pero cada clic manda un solo correo síncrono y hace falta una cuenta de staff, así que no sirve como relé de spam anónimo.
- Remedio: añadir `'by' => auth()->id()` y `'customer_email_original' => $booking->customer_email` al `Log::info`, y lo mismo en `resendCustomerConfirmation` (`:68`). Opcional: `RateLimiter::attempt('resend-reminder:'.auth()->id(), 20, ...)`.

### B-4 [BAJO] El reenvío a otra dirección marca la reserva como recordada y la tanda automática ya no le escribe al cliente real
- Ubicación: `BookingNotifier.php:92`. El update de `payment_reminder_sent_at` se hace aunque `$customerEmail` sea distinto de `$booking->customer_email`.
- Efecto: es lógica de negocio. Si un empleado se equivoca o lo manda a su propio correo para probar, el cliente se queda sin el recordatorio automático.
- Remedio: marcar solo cuando `strcasecmp($customerEmail, $booking->customer_email) === 0`, o decirlo de forma explícita en el modal.

### B-5 [BAJO] Ya existía: el Select `locale` de Reservas no valida en el servidor y ahora alimenta `Mail::locale()`
- Ubicación: `app/Filament/Resources/BookingResource.php:279-284`. Tiene `->required()` pero no `->in()`. Es el mismo patrón que el B-1 del lote 08/10, que ya se cerró en PaymentLinkResource.
- Origen del locale en los flujos públicos: está en la lista blanca. Viene de la ruta `->where(['locale' => 'es|en|pt'])` (`routes/web.php:76`), de `SetLocale` con `in_array(..., true)` y de `PaymentLinkController::resolveLocale()`, que valida con `in_array` estricto. Solo un admin que forje el payload de Livewire puede meter otro valor, y como mucho de 5 caracteres (`string('locale', 5)`).
- Impacto con los consumidores nuevos (`BookingNotifier.php:64-65` y `:88-89`): ninguno explotable. `Translator::setLocale()` rechaza `/` y `\` lanzando una excepción (`vendor/laravel/framework/src/Illuminate/Translation/Translator.php:500`). La acción la captura, no marca `sent_at` y avisa con una notificación. Un código desconocido cae al texto en español. En la plantilla, `<html lang="{{ $locale }}">` va escapado.
- Remedio: añadir `->in(['es','en','pt'])` al Select. Como defensa en profundidad, en `BookingNotifier` usar `in_array($booking->locale, config('app.supported_locales'), true) ? $booking->locale : 'es'`.

## Informativo
- I-1: el modal de error muestra `$e->getMessage()` al admin (`BookingResource.php:692`). Puede dejar ver detalles del SMTP a un usuario de staff. El patrón ya existía en "Reenviar correo". Se recomienda mostrar un texto genérico y mandar el detalle a `Log::warning`.

---

## Verificaciones realizadas

1. **Acción manual: OK, con B-3 y B-4.** El chequeo del servidor está en `BookingResource.php:671` (`payment_status`/`status`) y se suma a `->visible()`. Se envía una sola reserva y el mailable no tiene `ShouldQueue`. `sent_at` se marca solo si `send()` no lanzó (`BookingNotifier.php:86-92`). El correo pasa por la regla `email` y no hay inyección de cabeceras.
2. **Locale de la reserva: OK, con B-5.** La lista blanca está en todos los puntos públicos de escritura y el framework bloquea la traversal en `setLocale`.
3. **Modo urgente: OK, con B-1 y B-2.** Se mantienen `whereNull('payment_reminder_sent_at')` (`:75`), `limit($batch)` (`:81`) y el retraso mínimo `created_at <= now()-2h` (`:79`). El agrupado es por email+fecha y hay try/catch por grupo. La ventana `between(07:00, 22:00)` va en tz Lima. Con `config:cache` viejo, `?? 2` y los defaults del Kernel dan el mismo comportamiento. Mutex `withoutOverlapping` con `CACHE_DRIVER=file`: vale en un solo servidor. `hasDeparted()` usa una regex anclada y simple, sin riesgo de ReDoS, y el parse está en try/catch.
4. **B-1 y B-2 del lote 08/10: CERRADOS.**
   - B-1: `PaymentLinkResource.php:155` tiene `->in(array_keys(self::LOCALE_OPTIONS))`. El campo no es requerido, así que NULL sigue pasando.
   - B-2: la vista compilada en `storage/framework/views` contiene `json_encode($plCreateUrl, 15, 512)` y `json_encode($plCaptureUrl, 15, 512)`. Son los flags JSON_HEX_* (15), así que la vista compilada está al día. Los nuevos `@json(__('payment_links.js_*'))` no llevan comas.
5. **reCAPTCHA `hl`: OK.** El valor sale de un mapa fijo `['es'=>'es','en'=>'en','pt'=>'pt-BR'][app()->getLocale()] ?? 'es'` (`partials/recaptcha.blade.php:21`, `filament/auth/recaptcha-field.blade.php:11`) y se imprime con `{{ }}` (`:31`, `:45`, `:42`, `:79`). Como el valor es siempre una de tres constantes, el escape HTML dentro de `<script>` no importa. La validación del servidor no cambió: `app/Services/RecaptchaService.php`, `app/Filament/Auth/` y los controladores de formularios no aparecen en `git status`.
6. **thanks.blade.php / lang/*/checkout.php / footer: OK.** Todo va con `{{ }}` y `thanks.blade.php` no tiene `{!! !!}`. Las cadenas nuevas de lang no llevan HTML ni `:input`. `trans_choice` recibe enteros con cast `(int)`.
7. **PaymentLinkController `?lang=`: OK.** Usa `in_array($lang, $supported, true)` antes de `setLocale`, y el `locale` del link se vuelve a validar igual.

## No verificado
- La concurrencia de B-1 se dedujo leyendo el código (mutex por comando y ejecución en background a la misma hora). No se ejecutó.
- Que la acción oculta (`->visible()`) no se pueda montar forjando Livewire: no se probó con PoC. Da igual, porque hay chequeo explícito en el servidor.
- `composer audit` y `npm audit`: no se corrieron porque el lote no toca dependencias.
- El login del admin con `hl`: solo se revisó leyendo el código, no renderizado.

---

## Re-auditoría (2026-10-08, tras "Correcciones SECURITY" de FIX-backend.md)

### Veredicto: NO BLOQUEA

B-1, B-2, B-3, B-4, B-5 e I-1 quedan **cerrados**. Hay 2 observaciones nuevas, las dos de severidad baja o informativa. Revisión de solo lectura: no se editó código, no se navegó, no se tocó la BD ni se corrió `artisan test`. Los mtime de los 4 archivos no cambiaron durante la revisión (el último es 21:27:13).

### Cierre por hallazgo
- **B-1: CERRADO.** `SendBookingPaymentReminders.php:124-126` reclama cada reserva con `whereKey()->whereNull('payment_reminder_sent_at')->update(...) === 1` antes de enviar, y solo envía las que reclamó (`:133`). Si el envío falla, el catch revierte a NULL solo esas reservas (`:148`). El test `test_concurrent_run_that_loses_the_claim_sends_nothing` sí distingue el código viejo del nuevo: con el código anterior, la reserva ya había pasado el `whereNull` en el SELECT y se habría enviado.
  - **El proceso muere entre el reclamo y el envío** (OOM, kill, timeout del hosting): la reserva queda con `payment_reminder_sent_at` puesto y sin correo enviado. Ninguna corrida la vuelve a tomar y no deja rastro en el log, porque el `Log::info` va después del envío. Se acepta: falla cerrada, como mucho se pierde un recordatorio y nunca hay duplicado. Queda documentado.
  - **Reclamo parcial en un grupo email+fecha:** si dos procesos leen el mismo grupo {b1, b2} y cada uno reclama una reserva, el cliente recibe dos correos con una reserva cada uno en lugar de uno con las dos. No hay duplicado de contenido. Es aceptable. No hay test con un grupo de 2 y reclamo parcial.
- **B-2: CERRADO, con la limitación declarada.** `config/cache.php:18` toma `env('CACHE_DRIVER', 'file')`. El `.env` local y `.env.example` dicen `file`. El driver `file` sirve igual: `get`/`put` con TTL de 3 días en `storage/framework/cache/data`, el mismo directorio que ya usa el mutex de `withoutOverlapping`. El read-modify-write no es atómico, pero no hay carrera: solo el proceso que tiene el reclamo incrementa la clave de esa reserva. Límites: un `cache:clear` o un deploy que vacíe la caché reinicia el contador, y con varios servidores haría falta un driver compartido. Con `config:cache` viejo, `?? 3` en `:91` mantiene el comportamiento. El test `times(3)` sobre 5 corridas falla si hubiera un 4.º intento, así que es discriminante.
- **B-3: CERRADO.** `BookingNotifier.php:68-73` y `:102-108` registran `by`, `email` y `customer_email_original`. El fallo manual también deja `by` (`BookingResource.php`, `manual_failed`).
- **B-4: CERRADO.** `BookingNotifier.php:96-100` marca la reserva solo si `strcasecmp(trim(..), trim(..)) === 0`, y el log incluye `marked_sent`.
- **B-5: CERRADO.** El Select tiene `->in(['es','en','pt'])` y `safeLocale()` (`BookingNotifier.php:112-115`) usa `in_array` estricto contra `config('app.supported_locales')`, que existe (`config/app.php:101`). El comando automático no llama a `Mail::locale()`. El mailable solo usa el locale en un `match` con `default` y en la vista escapada, así que no hay superficie.
- **I-1: CERRADO** para la acción nueva: el texto es genérico y el detalle va a `Log::warning`. Queda un residual que ya existía antes: "Reenviar correo" sigue mostrando `$e->getMessage()` en `BookingResource.php:650`. Está fuera del alcance de este lote.

### Observaciones nuevas
- **N-1 [BAJO]** En `SendBookingPaymentReminders.php:135-145`, `Cache::forget` y `Log::info` están dentro del mismo `try` que el envío. Si alguno lanza después de que el SMTP ya aceptó el correo (log sin permisos o disco lleno), el catch revierte el reclamo y la reserva se vuelve a enviar en la próxima corrida. Es un duplicado real, aunque tiene tope: el contador limita a `max_attempts`, es decir hasta 3 envíos. Con el driver `file`, `forget` sobre una clave inexistente no lanza (`@unlink`), así que el riesgo práctico es el log. Remedio: poner `$mailed = true` justo después de `send()` y revertir en el catch solo si `! $mailed`, o sacar `forget` y `Log` del try.
- **N-2 [INFORMATIVO]** `Log::info('booking.payment_reminder.sent')` (`:143`) registra `$refs` del grupo completo y no las reservas reclamadas. Después de un reclamo parcial, el log dice que se enviaron reservas que no iban en ese correo. Remedio: usar `$claimed->pluck('reference')`.

### No verificado
- El `CACHE_DRIVER` real del `.env` de producción: se dedujo del default y de `.env.example`. Si prod tuviera `array`, el contador de B-2 no persistiría entre corridas y el mutex de `withoutOverlapping` tampoco funcionaría.
- No se ejecutaron los tests (lo prohíbe el brief). Las conclusiones sobre ellos salen de leerlos.
