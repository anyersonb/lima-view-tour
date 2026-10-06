# Auditoría de seguridad: lote PayPal + reseñas + filtros (2026-09-30)

Rama `feat/pagos-fechas-doble-cobro-2026-08-28`, HEAD `c469f3a`, lote SIN COMMITEAR. Solo lectura sobre app/, resources/, lang/.

Veredicto: **NO APTO** (ver final).

## Bloque 1 · Pago PayPal: REVISADO

Correcto (con evidencia):
- El monto y la moneda salen del servidor: `CheckoutController.php:242` `$total = $this->cart->total()` y `createOrder($total, 'USD', ...)`. El body del cliente solo trae nombre, correo, teléfono y accept_terms.
- Captura: snapshot obligatorio (`:384`), `getOrder` y comparación de importe y moneda normalizada ANTES de capturar (`:436-464`), estado `COMPLETED` exigido en `PayPalService.php:295`, snapshot borrado tras capturar (`:473`) y PayPal-Request-Id determinista (`PayPalService.php:255`).
- PaymentLock en las 3 puertas: create `:206` (antes de validar, así que el 422 de accept_terms no tapa el 409), capture `:371`, pay_later `:84`. El botón CARD comparte `ppHandlers` (`checkout.blade.php` ~2414) y va a los mismos endpoints, así que no abre una cuarta puerta.
- pay_later: `ProcessPaymentRequest` valida antes del lock. Esto ya pasaba antes de este lote. Con el lock activo, un envío válido sigue bloqueado; lo único que cambia es la UX: sin marcar los términos, el cliente ve el 422 en lugar del aviso.
- XSS de `notes` y `pickup_detail`: los correos usan `{{ }}` (`emails/bookings/admin-notification.blade.php:71`, `confirmed.blade.php:98`); el panel usa TextInput/Textarea (`BookingResource.php:272,276`); en el checkout, `old()` se imprime escapado. No hay `{!! !!}`.
- Mass assignment: `Booking` usa `$guarded=['id']`, pero `BookingCreationService` arma el array de forma explícita y `notes` solo sale de `$validated`.

Hallazgos:
1. **[MEDIO · funcional/disponibilidad] PayPal puede rechazar un `payer` que nuestro servidor dio por válido, y entonces el cliente no puede pagar.** `CheckoutController.php:291-329`. El comentario dice "nunca motivo para no poder pagar", pero un 422 de PayPal sobre `payer` hace fallar createOrder y el cliente recibe un 500. Hay tres casos: `surname` de más de 140 caracteres (el nombre admite 255), `national_number` de 15 dígitos (el límite de PayPal es 14) y correos que pasan la regla `email` RFC de Laravel pero no el patrón de PayPal (por ejemplo `a@b`). Escenario: un cliente con un teléfono de 15 dígitos no puede pagar. Remedio: acotar surname a 140, teléfono a 14 dígitos y correo con `email:rfc,dns` o con el patrón de PayPal; o bien, ante un 422 con `field` en `/payer/*`, reintentar una sola vez sin `payer`.
2. **[BAJO · A09] PII del payer en logs.** `PayPalService.php:148-152` registra `body` crudo del error de PayPal, y la excepción `:156` lo repite en `message`, que el controlador vuelve a loguear (`CheckoutController.php:~277`). Los 422 de validación de PayPal suelen devolver `details[].value` con el valor enviado (correo, teléfono o nombre). Remedio: loguear solo `debug_id`, `name` y `details[].field/issue`, nunca `value`.
3. **[BAJO · funcional] `application_context` está deprecado en Orders v2.** El reemplazo es `payment_source.paypal.experience_context`, y `payer` también está deprecado en favor de `payment_source.paypal`. Hoy PayPal lo sigue aceptando, pero es riesgo funcional, no de seguridad. Además, `PayPalService.php:135` solo añade NO_SHIPPING/PAY_NOW si `$payer` no está vacío: con el formulario vacío, PayPal pediría dirección de envío. Remedio: enviar siempre `experience_context` y migrar a `payment_source`. NO VERIFICADO contra sandbox.
4. **[BAJO] `tour_language` se valida pero se descarta.** El cliente lo elige y nadie lo recibe. No es un tema de seguridad; lo resuelve `backend-laravel`.

## Bloque 2 · Reseñas y migración: REVISADO

Correcto:
- Moderación: los dos formularios públicos insertan `is_active=false` y `status=pending` (`ReviewController.php:103-111`, `TourController.php:105-113`). La columna tiene default `pending`, así que falla cerrada. Todas las superficies públicas usan `published()` (`Testimonial.php` scope = is_active Y approved): Home:137, Page:26-31, Review:127, Tour:21/34/66/70, `Tour::reviewStats` y `about.blade.php:109`. Con grep no aparece ningún `active()` público restante.
- CSRF: rutas bajo `web`. Throttle `6,1` en las dos (`routes/web.php:145,232`).
- XSS: los componentes review-card, review-summary y tripadvisor-stamp y `tours/show` no tienen `{!! !!}` sobre datos de reseña (los `{!! !!}` de show pasan por `nl2br(e())` o por `json_encode` con HEX_TAG). Las reseñas no entran en ningún JSON-LD: el único es `components/schema-raw`, que el diff no toca.
- `submitter_email` va en `$hidden`.
- Migración: todas las columnas nuevas son nullable o tienen default, `down()` es reversible y hay un backfill explícito.

Hallazgos:
5. **[ALTO] La regla de extensión de `photos` es código muerto: se puede subir `ULID.php` a un disco público.** `TestimonialResource.php:112-122` y `:244-262`. Filament 3.3.26 aplica las `->rules()` del campo POR ARCHIVO (`vendor/filament/forms/src/Components/BaseFileUpload.php:632`, `"{$name}.*"`), así que `$value` es un único `TemporaryUploadedFile`, y `(array) $value` recorre sus propiedades internas y nunca falla. PoC con `php -r` y bytes reales: `shell.php` pasado como escalar da PASA; el control negativo (el mismo archivo dentro de un array) da "bad php". Queda en pie `mimetypes` (finfo), y un JPEG real con PHP anexado lo pasa. El nombre en disco es `Str::ulid().'.'.getClientOriginalExtension()` (`BaseFileUpload.php:184`), en `disk('public')` → `/storage/reviews/*.php`. Escenario: una cuenta del panel comprometida sube un JPEG políglota llamado `.php` y, si `/storage/` ejecuta PHP en el hosting, obtiene RCE. Requiere sesión de panel. Que `/storage/` ejecute PHP en producción: NO VERIFICADO. Remedio: `->getUploadedFileNameForStorageUsing()` con la extensión derivada del MIME detectado (`match` cerrado jpg/png/webp) + `Str::ulid()`, y corregir la regla a `$file = $value` (sin `(array)`). Hardening aparte: denegar la ejecución de PHP en `storage/app/public` (hoy no hay `.htaccess`). El mismo patrón `->image()` sin nombre controlado existe en otros FileUpload anteriores (`Settings.php:207`, etc.), fuera del diff.
6. **[MEDIO] El backfill de la migración aprueba también las reseñas que nunca se moderaron.** `2026_09_20_000000_...php`: `DB::table('testimonials')->update(['status'=>'approved'])` sin `where`. Las reseñas públicas que estaban esperando (`is_active=false`) quedan `approved` y desaparecen de la cola "Pendiente". Siguen ocultas por `is_active`, pero un admin que las vea como "Aprobado" y active "Visible" publica spam sin haberlo revisado. Remedio: `->where('is_active', true)->update(['status'=>'approved'])`, más `where('is_active', false)->where('source','Web')->update(['status'=>'pending'])`.
7. **[BAJO] Los formularios de reseña no tienen captcha: solo honeypot y throttle 6/min por IP.** Esto ya estaba antes y el diff no lo cambia. Contacto y newsletter sí usan `RecaptchaService` (hay un antecedente de claves v2/v3 mal configuradas). Como todo queda moderado, el impacto es spam en la cola del panel. Remedio: el mismo `RecaptchaService::verify()` una vez corregidas las claves.
8. **[BAJO] Caché `rememberForever` de `reviewStats`.** Se invalida en los eventos `saved`/`deleted` de Eloquent. Las bulk actions nuevas usan `$records->each->update()`, que sí dispara los eventos, así que funcionan bien. Un `update()` masivo por query builder (DB directo o imports) dejaría el promedio viejo para siempre. Integridad del dato, no explotable.
- Autorización del recurso: no existen Policies (`app/Policies` no existe); el panel se limita por `canAccessPanel` con el dominio del correo (`User.php:32-36`). Los clientes usan otro modelo y otro guard (`Customer`). Esto ya era así antes; todo usuario del panel es admin total.

## Bloque 3 · Filtros de /tours: REVISADO

- `resources/js/tour-filters.js` no contiene `innerHTML`, `insertAdjacentHTML` ni `outerHTML`: escribe con `textContent` (`:102,103,180,209`) y reordena nodos existentes con `appendChild` (`:141`).
- `data-i18n='@json($filtersI18n)'` (`tours/index.blade.php:316`): Blade `@json` aplica HEX_TAG, HEX_APOS, HEX_AMP y HEX_QUOT por defecto, así que no se puede salir del atributo con comillas simples.
- URLs: el JS no llama a `history.*`, `location.*` ni toca el hash, así que los filtros no generan URLs indexables.
- Sin hallazgos.

## Bloque 4 · ListBookings: REVISADO

- Solo cambia `Date::today()` por `BookingCalendar::today()` (zona Lima) y añade la pestaña `tomorrow`. Las fechas son del servidor y no hay input del usuario. Sin hallazgos.

## Bloque 5 · Secretos en archivos sin trackear: REVISADO

- `deploy-og-image-2026-09-10.sh:5`: la clave se lee de un entorno obligatorio (`${FTP_PASS:?...}`), así que el archivo no guarda credenciales. En `:3-4` están el host y el usuario FTP como defaults: no es un secreto, pero ayuda a quien intente adivinar la clave. El archivo no está en `.gitignore`.
- `scratchpad/lima-qa-mask-pii.md`: sin credenciales ni correos (los "PASS" son resultados de test). Tampoco está en `.gitignore`, así que un `git add .` lo sube.
9. **[BAJO · ya existía, trackeado] Token de `opcache-reset.php` en la documentación:** `docs/seo/RESPUESTA-ESPASEO-2026-08-21.md:208`. Si el repo es público, cualquiera puede resetear opcache en producción. Remedio: rotar el token y sacarlo del doc.
10. **[ALTO · ya existía, sigue abierto] `/_diag/mail` sigue vivo:** `routes/web.php:60-63`, relay de correo con token estático (hallazgo 3 del 27/08). No es de este lote, pero sigue sin cerrarse.
- Visibilidad del repo: hay dos remotos, `github` → github.com/anyersonb/lima-view-tour y `origin` → gitlab.com/pranmejor/lima-tour. El repo no dice en ningún lado si es público o privado: NO VERIFICADO (no se hicieron llamadas externas).
- Aparte: el `vendor` local tiene `livewire/livewire v3.6.3` (CVE-2025-54068, RCE sin autenticar, ya existía). La versión de producción NO se verificó. Si se despliega con este vendor, es un bloqueante Crítico.

## Verificaciones realizadas
- [✓] A01 acceso (lock en 3 puertas, moderación, panel por dominio) · [✓] A03 XSS/inyección (notes, pickup_detail, reseñas, filtros) · [✓] A04 lógica de dinero · [✓] A05/A08 subidas · [✓] A09 logs · [✓] secretos en archivos sin trackear
- [ ] No verificado: `composer audit`/`npm audit` en esta pasada; que `/storage/` ejecute PHP en producción; el comportamiento real de PayPal con `payer`/`application_context` (sin sandbox); la visibilidad del repo; la versión de Livewire en producción.

## Veredicto: NO APTO

Hay que corregir antes de producción:
- **#5:** nombre en disco derivado del MIME y regla de extensión arreglada.
- **#6:** backfill con `where('is_active', true)`. Si la migración corre tal cual, deja datos que no se pueden reconstruir con seguridad.

Recomendado en el mismo lote: #1 (el cliente puede quedarse sin poder pagar) y #2 (PII en logs).

El resto es Bajo o ya existía. Todo lo de arreglar va a `backend-laravel`.
