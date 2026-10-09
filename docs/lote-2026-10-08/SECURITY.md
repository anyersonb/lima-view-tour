# Auditoría de seguridad (estado 4): lote 2026-10-08

**Veredicto: NO BLOQUEA**. Lo apruebo con observaciones: 0 críticos, 0 altos, 0 medios, 2 bajos y 1 informativo.

Revisé solo el código, sobre el árbol sin commitear (`git diff` más los archivos nuevos). No navegué, no toqué la base de datos y no corrí la suite: `tests/Feature/PaymentLinkLocaleTest.php` usa la base y QA puede estar trabajando en paralelo.
Los mtime de PaymentLinkController, show.blade.php y PaymentLinkResource no cambiaron entre el inicio y el cierre de la revisión.

## Hallazgos críticos / altos / medios
Ninguno.

## Hallazgos bajos

### B-1 [BAJO] El Select `locale` del panel no valida el valor en el servidor
- Ubicación: `app/Filament/Resources/PaymentLinkResource.php:152-158`.
- Evidencia: Filament forms v3.3.26 (`vendor/composer/installed.json`) no añade una regla `in` automática al Select. La única `Rule::in` está en `CanBeValidated::in()` (`vendor/filament/forms/src/Components/Concerns/CanBeValidated.php:191-207`), y solo se aplica si se llama a `->in()`. Además, el modelo tiene `$guarded = ['id']` (`app/Models/PaymentLink.php:27`).
- Vector: un admin autenticado puede forjar la actualización de Livewire y guardar cualquier cadena en `payment_links.locale`. Una cadena de más de 5 caracteres revienta con error SQL en modo estricto.
- Impacto: limitado. El único lector del valor, `PaymentLinkController::resolveLocale()` (`:600`), lo vuelve a validar con `in_array(..., $supported, true)` y, si no es válido, cae al Accept-Language. La columna de la tabla lo escapa y usa `LOCALE_OPTIONS[$state] ?? 'Automático'`. Nada llega a `setLocale`, a rutas de archivos ni al HTML.
- Remedio: añadir `->in(array_keys(self::LOCALE_OPTIONS))` al Select. El campo no es requerido, así que Filament ya añade `nullable` (`CanBeValidated.php:644`) y el NULL sigue pasando. Todos los links existentes tienen NULL, así que no se rompe la edición de valores viejos.

### B-2 [BAJO] Las llamadas `@json(...)` con comas pierden los flags JSON_HEX_*
- Ubicación: `resources/views/payment-links/show.blade.php:162-163`.
- Evidencia: el compilador de Blade parte el argumento por comas (`vendor/laravel/framework/src/Illuminate/View/Compilers/Concerns/CompilesJson.php:22-28`). La vista compilada en `storage/framework/views` contiene `json_encode(route('payment-links.paypal.create', ['code' => $link->code, 'lang' => $locale]))`, es decir, con flags 0. Antes del cambio el segundo argumento era `512`, así que el patrón ya existía.
- Explotabilidad hoy: ninguna. `code` sale de la base y debe coincidir con la ruta, `lang` está en la lista blanca y `route()` codifica la query para URL. Además, `json_encode` sin flags escapa `/` como `\/`, así que no hay forma de cerrar el `</script>`.
- Riesgo: es una trampa latente. Si alguien añade otro argumento con datos del usuario, o `JSON_UNESCAPED_SLASHES`, queda abierto.
- Remedio: calcular las URL en el bloque `@php` (`$createUrl = route(...)`) y usar `@json($createUrl)` sin comas.

## Informativo

### I-1 El scheduler tiene la zona horaria cableada
`app/Console/Kernel.php:24` usa `->timezone('America/Lima')`, pero el comando usa `BookingCalendar::timezone()`, que lee `config('booking.timezone')`. Si alguien cambia esa config, la hora del cron y el "hoy" del comando se desalinean. No permite envíos duplicados (ver punto 5). Sugerencia: `->timezone(\App\Support\BookingCalendar::timezone())`.

## Verificación por punto del encargo

1. **`?lang=` y locale propagado: OK.** `resolveLocale()` (`PaymentLinkController.php:581-617`) exige `is_string` (un `lang[]=` no entra) e `in_array` estricto contra `config('app.supported_locales')`, que vale `['es','en','pt']` (`config/app.php:101`). El fallback del código también es `['es','en','pt']`. La columna del link pasa la misma validación.
   - La función se llama en los tres puntos: show `:67`, createOrder `:89` y captureOrder `:218`.
   - El valor solo llega a: `app()->setLocale()` (ya validado), `route('checkout.thanks', ['locale' => ...])` (`:441`, `:530`), el `$locale` de la reserva (`:497`) y la vista.
   - En la vista, el locale del SDK de PayPal sale de un mapa fijo (`show.blade.php:29`) con `?? 'en_US'`, así que el valor del usuario nunca se interpola en la URL del SDK. No hay path traversal, inyección de cabeceras ni XSS.
2. **Traducciones al JS: OK.** `@json(__('payment_links.js_*'))` (`show.blade.php:205, 239, 243`) no lleva comas, así que conserva los flags HEX. Las cadenas nuevas de `lang/{es,en,pt}` son literales sin HTML. No hay `{!! !!}` en `show.blade.php` ni en `thanks.blade.php`.
3. **Invariantes de SECURITY.md: OK.** El diff del controlador tiene solo dos hunks: el reordenamiento de show (resolveLocale después de cargar el link) y `resolveLocale()`. No se tocó nada de importe contra `$link->amount`, order_mismatch, un solo uso, el lock del link, el compare-and-set del webhook ni el centinela `&$captureId`. Cambiar el idioma entre show y capture solo altera los textos y el `locale` de la reserva, que es un valor de la lista blanca.
4. **Validación del Select en servidor: FALLA, sin impacto explotable.** Ver B-1.
5. **Recordatorio: OK.** Se mantienen `whereNull('payment_reminder_sent_at')`, `->limit($batch)`, `withoutOverlapping()` y el update de `sent_at` después de cada envío (`SendBookingPaymentReminders.php:44, 50, 77-78`).
   - `BookingCalendar::today()` devuelve un Carbon en Lima y `whereDate` lo formatea en su propia zona. A las 13:00 de Lima (18:00 UTC) la fecha de Lima y la UTC coinciden, así que la ventana no se ensancha.
   - El día del deploy, una corrida previa a las 09:00 UTC más la de las 13:00 de Lima no duplica envíos, gracias a `whereNull`.
6. **`lang/*/validation.php` y `:input`: OK.** Hay 0 ocurrencias de `:input` y de HTML. Ninguna vista imprime `$errors` ni mensajes de validación con `{!! !!}`. Los `{!! !!}` existentes en home/blog vienen de Settings del admin y no forman parte de este lote.

## No verificado
- Suite `PaymentLinkLocaleTest` y comportamiento en tiempo de ejecución: no los corrí, por la regla de solo lectura y porque RefreshDatabase vacía la base.
- `composer audit` / `npm audit`: el lote no cambia dependencias.
