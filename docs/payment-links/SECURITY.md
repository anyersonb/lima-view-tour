# Auditoría de seguridad — Links de pago PayPal (estado 4)

Fecha: 2026-09-25 · Auditor: security-engineer · Árbol sin commitear (mtimes de los archivos auditados: 2026-09-24 20:14–20:46, sin cambios entre el inicio y el cierre de la revisión).
Modo: SOLO LECTURA. No se editó código, no se corrió la suite (hay un QA validando este mismo árbol y `artisan test` con RefreshDatabase vacía la base), no se tocó producción ni PayPal.

## Veredicto: BLOQUEA

Motivo: 1 hallazgo **Alto** introducido por este lote (A-1, fuga de datos personales de un comprador a quien abra un link multiuso). El arreglo es corto. El núcleo de dinero está bien hecho: el monto sale de la BD, el importe se compara, la orden queda atada al link y el webhook falla cerrado. No encontré forma de pagar menos ni de reutilizar la orden de otro link o del checkout.

Aparte, fuera de este lote: `composer audit` sigue marcando CVE-2025-54068 (RCE en Livewire), que ya está en producción (ver "Preexistente"). Este deploy no lo empeora, pero la regla de "no se aprueba con críticos abiertos" aplica a la aplicación entera. Lo decide Anyerson.

---

## Hallazgos altos

### A-1 [ALTO] En un link multiuso, cualquiera que lo abra ve el email y el teléfono del último comprador, y su pago puede quedar a nombre de esa persona
- Ubicación: `app/Http/Controllers/PaymentLinkController.php:339-346` sobrescribe `customer_name/email/phone` del link con los datos del comprador. Con `single_use=false`, el `status` sigue en `pending` (`:342`). Después, `resources/views/payment-links/show.blade.php:123` y `:131` precargan `value="{{ $link->customer_email }}"` y `customer_phone` en el formulario público.
- Escenario: el admin crea un link multiuso para "cobros recurrentes con el mismo enlace" (texto de ayuda en `PaymentLinkResource.php:122`) y lo difunde por WhatsApp o redes (ver el comentario en `routes/web.php`). El comprador 1 paga. Cualquier otra persona que abra `/pagar/{code}`, sin sesión, ve el email y el teléfono del comprador 1. Si el comprador 2 no corrige los campos, `BookingCreationService::resolveCustomerId()` (`:123`) cuelga su reserva de la cuenta del comprador 1 y la confirmación le llega al comprador 1.
- Agravante: "Duplicar" (`PaymentLinkResource.php:260-263`) no excluye `customer_*`, así que copia los datos del comprador real de un link pagado a un link nuevo que se va a compartir.
- Remedio: guardar los datos del comprador en la Booking o en columnas `buyer_*` separadas, nunca en `customer_*` (el prefill del admin). Como mínimo, no sobrescribir `customer_*` cuando `single_use=false`. En Duplicar, excluir los datos del comprador.

---

## Hallazgos medios

### M-1 [MEDIO] La "nota interna" se publica al comprador
- Ubicación: `resources/views/payment-links/show.blade.php:85-86` renderiza `{{ $link->note }}`. Pero `PaymentLinkResource.php:125-126` le dice al admin: "Nota interna — Solo visible en el panel — no se muestra al comprador."
- Escenario: el admin escribe "cliente difícil, le rebajé 30%, margen 5 USD". Esa nota la lee cualquiera que tenga el link. Está escapada (no hay XSS): el problema es de confidencialidad.
- Remedio: quitar el bloque de la vista, o separar el campo en `note` (interna) y `public_note` (visible).

### M-2 [MEDIO] Carrera entre el capture síncrono y el webhook: puede crear dos reservas por un solo cobro
- Ubicación: el capture síncrono confirma `paypal_capture_id` en `PaymentLinkController.php:339-346` (tx 1) y crea la Booking en `:425-431` fuera de esa tx, sin lock ni re-chequeo de `booking_id`. El webhook, en `WebhookController.php` (`handlePaypalCaptureCompleted`), busca `Booking::where('payment_reference', $captureId)` y luego el link, también sin lock. Además, la condición `where(capture)->orWhere(order)->whereNull('booking_id')` compila a `capture = ? OR (order = ? AND booking_id IS NULL)`: si el link coincide por capture_id, no se exige `booking_id` nulo.
- Escenario: `PAYMENT.CAPTURE.COMPLETED` llega mientras el paso 2 síncrono está en curso (bcrypt de la cuenta invitada y la inserción). Los dos ven "sin Booking" y los dos crean una. Resultado: 2 reservas `confirmed/paid` con el mismo `payment_reference`, 2 correos de confirmación y 2 avisos al admin. La ventana es corta pero real, y nada la cierra.
- Remedio: en los dos caminos, envolver "¿existe Booking o `booking_id`? → crear → `booking_id`" en una transacción con `PaymentLink::lockForUpdate()` y re-chequear dentro. Agrupar el `orWhere` en un closure.

### M-3 [MEDIO] Un fallo de BD después de capturar se informa como "pago fallido" y deja el link reabierto (riesgo de doble cobro)
- Ubicación: `PaymentLinkController.php:264` inicializa `$captureId = null` fuera del closure. `:327` lo asigna **dentro** del closure (`use` sin `&`), así que el `catch (\Throwable)` de `:362-369` nunca sabe que ya se cobró. Si `forceFill()->save()` (`:339-346`) lanza, la tx revierte: el link queda `pending`, sin `capture_id`, y el comprador recibe `booking.payment_failed` (500).
- Escenario: el comprador ve "falló" y reintenta. `createOrder` sobrescribe `paypal_order_id` (`:231`) y el segundo pago se captura, con dinero cobrado dos veces. El webhook del primer cobro ya no casa: ni el capture_id ni el order_id siguen en el link, y termina en `no_match`. La probabilidad es baja (hace falta un error de BD justo ahí). El impacto es un cobro sin rastro en el panel.
- Remedio: marcar "capturado" en una variable por referencia (`use (&$captured)`) y, si ya se capturó, responder `payment_captured_booking_pending` con la referencia. Mejor aún: hacer el `captureOrder` fuera de la tx y persistir el capture en una tx propia con reintento.

### M-4 [MEDIO] `/webhooks/paypal` no tiene rate limit ni límite de tamaño, y cada petición sin autenticar dispara una llamada saliente a PayPal
- Ubicación: `routes/web.php` (ruta `webhooks.paypal`, sin `throttle`). `WebhookController::verifyPaypalSignature()` llama a `v1/notifications/verify-webhook-signature` con el token OAuth de la cuenta en cuanto vienen los 5 headers, y cualquiera puede inventar esos headers.
- Escenario: un atacante envía miles de POST con headers falsos y cuerpos grandes. Cada uno se convierte en una llamada de nuestra cuenta a PayPal (amplificación), y el `json_decode` procesa cuerpos de hasta `post_max_size`. Si PayPal limita la cuenta, también fallan el checkout y los links, porque comparten credenciales y token.
- Remedio: `throttle` propio (por ejemplo 60/min por IP) y rechazo previo si `Content-Length > 64 KB` o si `Paypal-Cert-Url` no es `https://api(-m)?(.sandbox)?.paypal.com/`, antes de llamar a PayPal.

### M-5 [MEDIO · integridad, derivar a `backend-laravel`] Con `travel_date` nulo, los correos y la cuenta del cliente muestran la fecha de HOY
- Ubicación: `Carbon::parse(null)` devuelve `now()`. Aparece en `resources/views/emails/bookings/confirmed.blade.php:95`, `emails/bookings/admin-notification.blade.php:68`, `emails/bookings/payment-reminder.blade.php:120` y `customer/account.blade.php:172,198`.
- Escenario: link sin fecha → reserva con `travel_date NULL` → la confirmación al cliente y el aviso al admin dicen "Fecha del tour: 25 sep 2026". Operaciones puede despachar un tour que nadie pidió para hoy. No revienta, y por eso nadie lo nota.
- Revisados sin problema: `SendBookingPaymentReminders.php:44-46` y `Booking.php:68-69,95-96` filtran con `whereDate`, que excluye NULL, así que el `->toDateString()` de `:56,66,81` nunca recibe null. `checkout/thanks.blade.php:69` ya contempla el null. Las reservas de link nacen `paid`, así que no entran en el recordatorio de pago.
- Remedio: en esas 5 líneas, `$booking->travel_date ? … : __('payment_links.date_to_be_arranged')`.

---

## Hallazgos bajos

- **B-1 El código del link viaja a los logs y a la analítica.** `PayPalService.php:138-143` loguea `metadata` (= `payment_link_code`) en cada orden creada, y `PaymentLinkController.php:216,315,363,433,455` loguea `code`. El layout carga GTM/GA4, que envían `page_location` con `/pagar/{code}` completo (inferido: GA4 manda la URL entera por defecto; no lo verifiqué midiendo `/collect`). Con el código se ve el tour, el monto y la PII del prefill. Remedio: loguear `payment_link_id` en lugar de `code`, y en esta vista sobrescribir `page_location` sin el código o no cargar GTM.
- **B-2 La reconciliación del webhook no compara el importe.** `handlePaypalCaptureCompleted` crea la Booking sin mirar `resource.amount.value` contra `$link->amount`. Hoy no es explotable, porque la orden solo la crea el servidor con el monto del link. Pero si el admin edita el monto de un link pendiente con una orden ya aprobada y el cobro entra por otra vía (por ejemplo `actions.order.capture()` del SDK en el cliente), el webhook reserva sin verificar. Remedio: comparar importe y moneda también ahí y, si no casan, dejarlo para revisión manual.
- **B-3 Sin protección anti-replay en el webhook.** No se deduplica `Paypal-Transmission-Id` ni se acota `Paypal-Transmission-Time`. Hoy los handlers son idempotentes, así que el impacto es nulo. Remedio: guardar el transmission_id en caché 72 h y rechazar eventos con más de 24 h.
- **B-4 DENIED y REFUNDED no tocan el PaymentLink.** Un link reembolsado sigue marcado "Pagado" en el panel. Remedio: reflejar `refunded` en el link.
- **B-5 Borrar un link pagado borra el rastro del cobro.** `EditPaymentLink` usa `DeleteAction` y el listado `DeleteBulkAction` (`PaymentLinkResource.php:280`) sin guarda por estado, y no hay activitylog. Además, el webhook deja de poder reconciliar (`no_match`). Remedio: `->hidden(fn ($r) => $r->paypal_capture_id)` o soft-delete.
- **B-6 Cualquier poseedor del link puede bloquear el pago legítimo.** Cada `createOrder` sobrescribe `paypal_order_id` (`PaymentLinkController.php:231`). Quien tenga el link puede crear órdenes en bucle (5/min por IP, sin límite si rota IPs) y el comprador real recibe `order_mismatch` al aprobar. No mueve dinero. Remedio: no sobrescribir si la orden guardada sigue viva, o guardar varias órdenes por link.
- **B-7 Replay de `already_paid`.** Con `code` + `orderID`, `PaymentLinkController.php:376-398` vuelve a cargar la reserva en la sesión del que pregunta (la página de gracias muestra la PII de la reserva) y re-arma `conversion_pending`, lo que duplica el evento de compra en la analítica. Requiere el orderID de la víctima. Remedio: en el replay, redirigir sin rellenar la sesión, o exigir que el email coincida.
- **B-8 [operativo, confirmado; ya reportado por QA como alto operativo]** `PaymentLinkResource.php:31-34`, `getNavigationBadge()`, consulta `payment_links` sin guarda. Si el código se sube antes que la migración, todas las pantallas del panel dan 500. No es un vector de ataque. Derivar a `backend-laravel`.

---

## Verificaciones realizadas (limpias, con evidencia)

1. **Integridad del monto** [✓]
   - `createOrder` usa `(float) $link->amount` y `'USD'` fijo (`PaymentLinkController.php:212`). El request no aporta importe: el JS envía `{}`.
   - En el capture, `getOrder` se compara con `$link->amount` como texto normalizado a 2 decimales, más la moneda `USD` (`:306-324`), ANTES de `captureOrder` (`:326`). `PayPalService::captureOrder` exige `status COMPLETED` (`PayPalService.php:262`).
   - Rango: `->numeric()->minValue(0.01)->required()` (`PaymentLinkResource.php:78-83`) se valida en servidor. La columna es `decimal(10,2)` (migración `000001:24`). Los redondeos de `number_format` son iguales en create y capture. No hay forma de 0 ni de negativos.
2. **Idempotencia y carreras** [✓ con M-2 y M-3]
   - `lockForUpdate` + tx en capture (`:267-269`). El `orderID` debe ser igual al guardado para ESTE código (`:284-286`), y eso se chequea antes de cualquier llamada a PayPal, lo que también impide meter una ruta arbitraria en la URL de `getOrder`.
   - Órdenes cruzadas: el checkout exige un snapshot de sesión para el orderID (`CheckoutController.php:286-288`), así que no captura una orden de link. El link no acepta una orden del checkout ni de otro link. El capture_id se persiste antes que la Booking (`:339-346`). Un doble capture de la misma orden lo rechaza PayPal (ya capturada) y no crea reserva.
3. **Webhook** [✓ con M-4 y B-3]
   - Falla cerrado sin `webhook_id`, con evento no-array, si falta cualquiera de los 5 headers, ante un error HTTP o una excepción, y ante todo lo que no sea `verification_status === 'SUCCESS'` estricto (`verifyPaypalSignature`).
   - El `event_type` se valida con `match` y lo desconocido solo se loguea.
   - CSRF: `VerifyCsrfToken.php:14-15` excluye solo `webhooks/*`. Las rutas `/pagar/*` quedan con CSRF y el JS manda `X-CSRF-TOKEN`.
4. **Código del link** [✓ con B-1]
   - `Str::random(40)` (usa `random_bytes`) con reintento por colisión (`PaymentLink.php:55-62`), `unique` y `string(64)`. Con collation case-insensitive de MySQL quedan unos 206 bits: la enumeración es inviable, y además `show` tiene `throttle:30,1`.
   - `noindex,nofollow` (`show.blade.php:28`). `Referrer-Policy: strict-origin-when-cross-origin` (`SecurityHeaders.php:126`): paypal.com solo recibe el origen.
   - Sobre la PII, con un link de un solo uso enviado a su cliente, precargar email y teléfono es aceptable (el nombre no se precarga, `:115`). En links multiuso no lo es (A-1).
5. **Autorización Filament** [✓]
   - Ningún Resource tiene Policy ni `canViewAny` (`grep` en `app/Filament` vacío, sin `app/Policies`). El gate único es `User::canAccessPanel()` por dominio de email (`User.php:32-35`), y PaymentLinkResource es coherente con el resto del panel.
   - Re-abrir un link pagado por Livewire: los `Section->disabled()` ponen `dehydrated(false)` (`vendor/filament/forms/src/Components/Concerns/CanBeDisabled.php:17`), sus hijos se eliminan del estado al guardar (`HasState.php:173-199`) y la relación `tour_id` no se guarda con el contenedor deshabilitado (`BelongsToModel.php:26`). `status` no es un campo del form.
   - "Anular" tiene `visible(pending)`: una acción oculta queda deshabilitada y se corta en servidor.
   - "Duplicar" crea un link NUEVO con código nuevo y `status=pending`. No reabre el original, pero ver A-1 por la PII copiada.
6. **Inyección y XSS** [✓] No hay `{!! !!}` en `payment-links/`, `emails/bookings/` ni `checkout/thanks`. Todo va con `{{ }}` y las URLs del JS con `@json`. El mensaje de error se pinta con `textContent`.
7. **Secretos y CSP** [✓] Sin credenciales en `docs/payment-links/` ni en los tests nuevos. `SecurityHeaders.php` no cambió en este lote, así que no hay `unsafe-*` nuevos (los `unsafe-inline/eval` son preexistentes). No se loguean tokens OAuth. Sí se loguean los `body` de error de PayPal (preexistente, sin PII en los errores de create/get/capture).
8. **travel_date nullable** [✓ con M-5]
9. **Rate limiting** [✓ con B-6] create y capture usan `throttle:checkout` = 5/min por IP (`RouteServiceProvider.php:46-47`), un contador compartido con el checkout.

### No verificado
- **Que la firma verifique con eventos reales.** `webhook_event` se envía re-serializado (`json_decode` → `json_encode`), no el cuerpo crudo. Si PayPal lo rechaza por la re-serialización, la red de seguridad queda muerta en silencio: falla cerrado, así que no es un agujero, pero M-2 y M-3 dependen de ella. No se pudo probar: el entorno local no tiene credenciales PayPal y tiene el bloqueo SSL (QA.md). Hay que confirmarlo con el "Webhook simulator" de sandbox antes de dar la red por operativa.
- Que GA4, Clarity o Hotjar registren el código de la URL (B-1): inferido del layout, no medido.
- Tests: no se corrió la suite (árbol congelado por QA).

---

## Preexistente (no introducido por este lote; dependencias sin cambios)
`composer audit --locked`: **livewire CVE-2025-54068 (RCE, crítico)**, además de filament CVE-2026-48500/55409/48067, laravel CVE-2026-48019 (CRLF en la regla `email`: este lote suma una entrada pública nueva, `customer_email` de `/pagar/*/capture`, que termina en `Mail::to`), guzzle, psr7, symfony/mime, commonmark, y otros. Remedio: `composer update livewire/livewire filament/* laravel/framework guzzlehttp/*` en un lote propio.

## Recomendaciones de hardening
- Comando programado `payment-links:expire`, para no depender del self-heal al visitar.
- Log de auditoría (quién creó, editó, anuló o borró cada link, y con qué monto).
- Alerta, por correo al admin, ante `payment_link.capture_succeeded_booking_failed` y `webhook.paypal.capture_completed.link_missing_customer_data`: hoy solo quedan en el log.

---

## Re-auditoría (post FIX-1) — 2026-09-25

Auditor: security-engineer · SOLO LECTURA: sin editar código, sin correr la suite (QA la corre en paralelo), sin tocar producción ni PayPal. Los mtimes de los 16 archivos auditados son iguales al abrir y al cerrar la revisión.

### Veredicto: BLOQUEA

Motivo: **N-1 [ALTO]**, una regresión introducida por el propio fix de M-2. En un link multiuso, a partir del segundo pago se cobra sin crear la reserva, y al comprador se le muestra la reserva del anterior. El resto del lote está bien cerrado.

### Estado de cada hallazgo previo

| # | Estado | Evidencia |
|---|---|---|
| A-1 | **CERRADO** (links de un solo uso); para multiuso, ver N-1 | `PaymentLinkController.php:268-275` escribe solo `buyer_*`. Ningún camino de pago toca `customer_*` (grep). La vista pública precarga solo `customer_*` (`show.blade.php:126,134`) y no hay ningún `buyer_` en las vistas públicas. La reserva sale del `$validated` del request actual (`:416-423`); en el webhook, de `buyer_*` (`WebhookController.php:373-380`). Duplicar excluye `customer_*` y `buyer_*` (`PaymentLinkResource.php:336-341`). |
| M-1 | **CERRADO** | En `show.blade.php:87-90` solo queda un comentario Blade. El grep de `->note` en `resources/views` no encuentra nada en las vistas públicas. |
| M-2 | **CERRADO** (un solo uso). La regresión en multiuso es N-1 | Lock en el paso 1 (`PaymentLinkController.php:194`), en el paso 2 (`:396`, con re-chequeo en `:398`) y en el webhook (`WebhookController.php:293-300`, con re-chequeo en `:311`). El `orWhere` va agrupado en un closure (`:293-298`). |
| M-3 | **PARCIAL** | Cerrado para el caso reportado: `use (&$captureId)` (`:192`), asignación inmediata después de capturar (`:255`), escritura forense aislada (`:309-318`) y respuesta 200 `payment_captured_booking_pending` (`:329-334`). El JS no ofrece reintento (`show.blade.php:232-235`). Sigue abierto por otra vía: el timeout de la captura (N-2). |
| M-4 | **CERRADO** | `throttle:paypal-webhook` a 60/min por IP (`routes/web.php:308-310`, `RouteServiceProvider.php:56-58`). Tope de 64 KB con 413 antes del `json_decode` (`WebhookController.php:24,100-107`). Cert-Url: exige https y host `^([a-z0-9-]+\.)*paypal\.com$` antes de llamar a PayPal (`:176-180,224-235`). El body crudo va por `sprintf` (`:187-196`). PoC `php` con 8 casos: el JSON resultante siempre es válido y `webhook_event` llega idéntico, incluso con comillas, `</script>`, espacios y claves duplicadas. Escalares, basura final y BOM se rechazan antes de llamar. `webhook_id` = `Setting::get` con respaldo en config (`:152`), el mismo origen que las credenciales de `PayPalService.php:20-22`. |
| M-5 | **CERRADO** | Ternario con `date_to_be_arranged` en `confirmed.blade.php:95`, `admin-notification.blade.php:68`, `payment-reminder.blade.php:18-19,120` y `customer/account.blade.php:172,198`. |
| B-1 | **PARCIAL** | Logs: `maskCode()` de 6 caracteres (`PaymentLink.php:146-149`) en todos los logs y en la metadata de PayPal (`PaymentLinkController.php:128`). Analítica: sin cambios. `layouts/app.blade.php:232-268` sigue cargando GTM/GA4 en `/pagar/{code}`, con el código completo en `page_location`. |
| B-2 | **CERRADO** | Compara importe y moneda bajo lock antes de reservar (`WebhookController.php:327-343`). |
| B-3 | **ABIERTO** (bajo, aceptable) | No se tocó: no se deduplica `Paypal-Transmission-Id`. Sin impacto, porque los handlers son idempotentes. |
| B-4 | **CERRADO** | Los estados `denied` y `refunded` se reflejan en el link (`:432-442,483-493`). `status` es `string(20)`, así que MySQL no rechaza los valores nuevos. |
| B-5 | **PARCIAL** | Tanto el guardián (`PaymentLink.php:57-66`) como `DeleteAction` (`EditPaymentLink.php:19-20`) miran solo `status === 'paid'`. Siguen borrables, y se pierde el rastro del cobro: un link **multiuso** ya cobrado (sigue `pending`, `PaymentLinkController.php:271`) y los links `refunded` o `denied`, todos con `paypal_capture_id`. Remedio: condicionar el borrado a `paypal_capture_id !== null`. |
| B-6 | **CERRADO**, con la observación N-4 | Reutiliza la orden viva (`PaymentLinkController.php:105-121`). |
| B-7 | **CERRADO** | La sesión solo se rellena si el email coincide (`:373-381`). |
| B-8 | **CERRADO** | `Schema::hasTable` más try/catch (`PaymentLinkResource.php:41-54`). |
| Colateral `DeleteBulkAction->using()` | **CERRADO** | `foreach` sin corte (`PaymentLinkResource.php:380-385`). UX: Filament notifica "Eliminado" aunque un link pagado haya sobrevivido. |

### Nuevos

**N-1 [ALTO · regresión del fix de M-2 · bloqueante] En un link multiuso, desde el 2.º pago se cobra sin crear reserva y se muestra la reserva de otro**
- Ubicación: en multiuso, el `status` sigue `pending` después de pagar (`PaymentLinkController.php:271`). `booking_id` se escribe una sola vez (`:431`) y nadie lo limpia (grep de `booking_id` en `app/`). En el 2.º pago, el paso 2 encuentra el `booking_id` del comprador 1 (`:398-399`) y devuelve esa reserva con `created=false`. Así, no crea reserva, no manda el aviso del `notifier`, guarda la reserva del comprador 1 en `last_bookings` (`:454`) y dispara la conversión. El webhook de la 2.ª captura cae en `already_reconciled` (`WebhookController.php:311-317`).
- Impacto: el comprador 2 paga y se queda sin reserva y sin correo, y el admin no recibe ningún aviso. El panel muestra la reserva 1, porque `paypal_capture_id` y `buyer_*` quedan pisados con los datos del comprador 2. En la página de gracias, el comprador 2 ve la referencia, el tour, la fecha y el importe del comprador 1. El log dice `booking_already_created_by_webhook`, que es falso. No hace falta un atacante: es el uso documentado del toggle ("cobros recurrentes con el mismo enlace", `PaymentLinkResource.php:142`).
- Cobertura: el único test multiuso (`tests/Feature/PaymentLinkTest.php:355`) hace un solo pago.
- Remedio (le toca a `backend-laravel`):
  - (a) Lo mínimo: forzar `single_use=true` o quitar el toggle hasta tener (b).
  - (b) Una tabla hija por cobro (order_id, capture_id, buyer_*, booking_id), con el lock y el re-chequeo ahí.
  - Como mínimo, el re-chequeo del paso 2 y del webhook debe preguntar "¿existe una Booking con `payment_reference = $captureId`?", no mirar `link.booking_id`.
  - Test obligatorio: dos pagos sobre el mismo link deben dar dos reservas, cada una en la sesión de su comprador.

**N-2 [MEDIO] Una captura ambigua (timeout o error de red después de cobrar) todavía puede cobrar dos veces**
- Si PayPal cobra pero la respuesta de `POST /capture` no llega, `PayPalService.php:279-288` lanza `RuntimeException` y `$captureId` queda null. El comprador recibe "pago fallido" 500 (`PaymentLinkController.php:337-343`) y el link sigue `pending`. Al reintentar, `createOrder` ve la orden `COMPLETED`, no la reutiliza y crea otra (`:105-121`): segundo cobro. El webhook del primero termina en `no_match`, porque el order_id del link ya se pisó.
- Remedio: en `createOrder`, si la orden guardada está `COMPLETED`, no crear otra. Leer el capture de `purchase_units[0].payments.captures[0]`, persistirlo y responder "reserva pendiente". En el catch sin `$captureId`, hacer un `getOrder` antes de declarar el fallo. Opcional: `PayPal-Request-Id` en el capture.

**N-3 [BAJO · operativo] SMTP síncrono dentro del lock del webhook, y las credenciales de invitado salen antes del commit externo**
- El webhook ejecuta `BookingNotifier::send` dentro de la tx con `lockForUpdate` (`WebhookController.php:390`). Con `QUEUE_CONNECTION=sync` y Mailables sin `ShouldQueue`, el SMTP retiene el lock. Si tarda más que `innodb_lock_wait_timeout`, el paso 2 revienta: el comprador ve "reserva pendiente" y el admin recibe una alarma falsa.
- Además, `createBookings` ahora corre anidado dentro de la tx de ambos llamadores, y envía las credenciales de invitado (`BookingCreationService.php:151-154`) antes del commit externo. Si la tx externa revierte, el correo llega para una cuenta que no existe.
- Remedio: sacar `send()` del closure en el webhook, como ya hace el paso 2 (`PaymentLinkController.php:446`), y diferir el correo de credenciales hasta después del commit externo.

**N-4 [BAJO] B-6 reutiliza la orden viva aunque el monto del link haya cambiado**
- Si el admin edita el `amount` de un link `pending` que ya tiene una orden viva, `createOrder` devuelve la orden vieja (`:109-110`). El capture la rechaza por `amount_mismatch` (`:239-249`), y el link queda impagable hasta que PayPal expire esa orden.
- Remedio: reutilizar la orden solo si su `amount` coincide con el del link, o limpiar `paypal_order_id` al editar el monto.

**N-5 [BAJO · hardening] No se comprueba el resultado de `json_encode` sobre las cabeceras**
- Con UTF-8 inválido, `json_encode` devuelve `false` y el body sale malformado (`"transmission_id":,`, reproducido en la PoC). Falla cerrado, porque PayPal responde 4xx, pero gasta una llamada saliente.
- Remedio: `JSON_THROW_ON_ERROR`, o rechazar la petición antes de llamar a PayPal.

### Revisiones pedidas, sin hallazgo
- **Render hook de copiar** (`AdminPanelProvider.php`, `BODY_END`; `copy-fallback-script.blade.php`): no hay XSS.
  - Es JS inline propio, sin `src` externo.
  - Lee el texto con `getAttribute` y lo escribe con `textarea.value` y `toast.textContent`. No usa `innerHTML` ni `eval`.
  - La URL entra con `e()` en `value` y en `data-copy-text` (`PaymentLinkResource.php:182,186`). En la tabla y en las acciones pasa por `extraAttributes`, que escapa el ComponentAttributeBag.
  - La URL sale de `route()`, con un código `[A-Za-z0-9]{40}`.
- **`webhook_id` en Configuración** (`Settings.php:200-201`): el campo ya existía y se ve en claro, pero no es un secreto: es un identificador y no sirve para forjar firmas. Quien entre al panel puede cambiarlo, y eso solo apaga el webhook (falla cerrado). Preexistente, fuera de este lote: `paypal_secret` se precarga desde config (`Settings.php:46`) y es `revealable` (`:193-196`) para cualquier usuario del panel.

### No verificado
- Que PayPal acepte `verify-webhook-signature` con el cuerpo crudo y eventos reales: no hay sandbox.
- N-1 y N-2 salen de trazar el código línea a línea. No se ejecutó PoC, porque no se corre la suite ni se muta el árbol mientras QA valida.
- `composer audit` no se volvió a correr. `composer.lock` no cambió (`git diff --stat` vacío), así que sigue la **CVE-2025-54068 de Livewire (RCE)** preexistente. No bloquea este lote: la decisión es de Anyerson.

---

## Re-auditoría 2 (post FIX-2) — 2026-09-25

Auditor: security-engineer · SOLO LECTURA: sin editar código, sin correr la suite (QA la corre en paralelo), sin tocar producción ni PayPal. Los mtimes de los 9 archivos del módulo son iguales al abrir y al cerrar la revisión (PaymentLinkController 08:31:40, WebhookController 08:28:12, PaymentLink 08:30:46, PayPalService 08:39:31, BookingCreationService 08:12:38, PaymentLinkResource 08:13:19, EditPaymentLink 08:12:44). `composer.lock` sin cambios.

### Veredicto: NO BLOQUEA

No queda ningún hallazgo Crítico ni Alto en el lote. N-1 está cerrado de verdad, y N-2 a N-5 y B-5 están implementados como se pidió. Hay 5 hallazgos nuevos, todos **Bajos**: residuales de rutas de recuperación que exigen dos fallos seguidos de PayPal o de la BD. Ninguno es explotable a voluntad por un tercero. Se pueden arreglar en un lote aparte.

Fuera del lote: sigue abierta la **CVE-2025-54068 de Livewire (RCE)** preexistente. La decisión es de Anyerson.

### Estado de cada punto

| # | Estado | Evidencia |
|---|---|---|
| N-1 multiuso | **CERRADO** | Se fuerza `single_use = true` en `PaymentLink.php:56-57`, dentro de `saving`. Ese evento corre antes que `creating` en los inserts, así que cubre alta, edición, Duplicar (`$copy->save()` en `PaymentLinkResource.php:367`), el factory y tinker. No existe un hook `creating` específico, pero no hace falta. No hay `saveQuietly`, `withoutEvents` ni `insert` sobre PaymentLink en `app/` ni en `database/` (grep vacío). El toggle ya no está en el form: el único `single_use` fuera del modelo es el del factory, en `:30`. **Pendiente tras cobrar:** no queda ningún camino. El paso 1 escribe `'status' => 'paid'` fijo (`PaymentLinkController.php:317`), y `persistOrphanCapture` también (`:636`). **Re-chequeo por `payment_reference`:** en el paso 2, bajo lock (`:465-471`), y en el webhook, bajo lock (`WebhookController.php:371-383`). **Rechazo antes de crear la orden:** `isUsable()` exige `pending` (`PaymentLink.php:143-146`, `PaymentLinkController.php:100-102`), y se aplica antes de cualquier `getOrder` o `createOrder`. |
| N-2 captura ambigua | **CERRADO** (con el residual NF-2) | **Orden `COMPLETED` en createOrder:** no crea orden nueva, persiste la captura y responde 422 (`PaymentLinkController.php:139-154`). **Catch sin captureId:** hace un `recoverCaptureIdFromPaypal()` que exige `status === 'COMPLETED'` (`:372-391`, `:663-676`) y, si el `getOrder` falla, sigue respondiendo "fallido" sin suponer que hubo cobro. **`PayPal-Request-Id`:** `sha256(order_id)` truncado a 36 (`PayPalService.php:232,318-321`). **Sin reserva duplicada:** ninguna de las tres ramas crea una Booking. Solo marcan el link como `paid` con la captura, y de la reserva se encargan el webhook o el admin. **Sin segundo cobro:** el link queda `paid`, así que `createOrder` y `captureOrder` lo rechazan (`:100`, `:249`). |
| N-3 correo | **CERRADO** | **Webhook:** la transacción devuelve `?array` y `send()` corre fuera, después del commit (`WebhookController.php:336,461,473-477`). **Credenciales de invitado:** usan `DB::afterCommit` (`BookingCreationService.php:103-111`). Lo verifiqué en vendor (Laravel **v10.48.29**, `DatabaseTransactionsManager::addCallback:199-205`): si no hay una transacción pendiente, el callback se ejecuta al instante. Por eso el checkout normal no cambia: `CheckoutController` no abre ninguna transacción (grep de `DB::transaction` vacío) y la única que hay es la de `createBookings`. Dentro de la transacción externa, el envío se difiere al commit real y se descarta si hay rollback. |
| N-4 | **CERRADO** | **Reutilización de la orden:** solo si coinciden `amount` (a 2 decimales) y `USD` (`PaymentLinkController.php:124-133`). **Limpieza al editar:** `paypal_order_id = null` cuando cambia `amount` en un link `pending` (`PaymentLink.php:64-66`). Efecto colateral seguro: si el admin edita el monto con el popup de PayPal abierto, la captura da `order_mismatch` antes de cobrar (`:253-255`). |
| N-5 | **CERRADO** | Los 6 campos van con `JSON_THROW_ON_ERROR`, dentro de un try propio y antes del OAuth (`WebhookController.php:182-210`). Si falla, devuelve `false` y el `abort(400)` de `:111-115` responde. Un cuerpo con UTF-8 inválido ya lo rechazaba el `json_decode` → `! is_array`, en `:160`. |
| B-5 | **CERRADO** | **Modelo:** `paypal_capture_id !== null` devuelve `false` en `deleting` (`PaymentLink.php:84-94`). **DeleteAction:** se oculta con esa misma condición (`EditPaymentLink.php:22-23`), y una acción oculta se corta en el servidor. **DeleteBulkAction:** un `foreach` con `$record->delete()`, que respeta el `false` del evento, y cuenta los links protegidos (`PaymentLinkResource.php:411-428`). No hay `DeleteAction` en las filas de la tabla. |
| 7 · Re-enlace del webhook | **CERRADO** para los datos legítimos; ver NF-1 | La rama 1 (`WebhookController.php:296-307`) solo re-enlaza si la Booking encontrada por `payment_reference = captureId` (el id de un evento con firma verificada) es `payment_method = 'payment_link'`. Además, el `where` agrupado exige que el link tenga ESE `capture_id` o el `order_id` de ESE mismo evento, y `booking_id IS NULL`. Como PayPal hace una captura por orden, el único link que casa es el dueño del cobro. Solo podría enlazar a la reserva de otro cobro si `paypal_capture_id` estuviera contaminado de antemano. La única vía que lo permite es NF-1. |
| 8 · Regresión | **Nada reabierto** | **A-1:** `buyer_*` solo en `PaymentLinkController.php:318-320`; la vista precarga solo `customer_*` (`show.blade.php:126,134`) y no tiene `buyer_`; Duplicar excluye los dos grupos (`PaymentLinkResource.php:358-363`). **M-1:** no hay `->note` en `show.blade.php`. **M-2:** hay lock y re-chequeo en el paso 2 (`:452-456`) y en el webhook (`:337-362`), y el `orWhere` va agrupado. **M-4:** `throttle:paypal-webhook` 60/min (`routes/web.php:308-310`, `RouteServiceProvider.php:56-58`), tope de 64 KB (`WebhookController.php:24,100-107`) y Cert-Url validada (`:239-250`). **M-5:** los 5 ternarios `date_to_be_arranged` siguen. **B-2:** el importe y la moneda se comparan bajo lock (`:392-408`). **B-4:** `denied` y `refunded` se reflejan en el link (`:505-514`, `:556+`). **B-7:** la sesión solo se rellena si el email coincide (`PaymentLinkController.php:429-437`). **B-8:** `Schema::hasTable` más try/catch (`PaymentLinkResource.php:43-54`). |

### Nuevos (todos Bajos, ninguno bloquea)

**NF-1 [BAJO · hardening] La recuperación de N-2 usa el `orderID` del request sin haberlo validado contra el link**
- Ubicación: `PaymentLinkController.php:372` llama a `recoverCaptureIdFromPaypal($orderId)` con el `orderID` del request, y `:382` hace `persistOrphanCapture($code, …)`, que escribe en el link por `code` sin comprobar que `paypal_order_id === $orderId` (`:613-637`).
- Cuándo pasa: si el `Throwable` salta ANTES del chequeo `order_mismatch` (`:253`). Eso solo ocurre con un error de BD en el `lockForUpdate` de `:238` (deadlock, lock wait timeout o conexión caída).
- Qué haría falta: un atacante con el code de un link ajeno y el `orderID` de una orden `COMPLETED` (por ejemplo, una compra barata suya) tendría que provocar además ese error de BD.
- Impacto:
  - Marca como `paid` el link de la víctima con una captura ajena.
  - El `orderID` sin validar vuelve a llegar al path de `GET /v2/checkout/orders/{id}`, cosa que el orden del chequeo impedía antes.
  - En cadena con el punto 7, el re-enlace de la rama 1 podría colgar ese link de la reserva de otro link.
- Hoy no es provocable a voluntad (el lock se retiene solo durante 2 llamadas HTTP a PayPal). Por eso es Bajo.
- Remedio: una bandera por referencia `&$orderValidated`, puesta a `true` después de `:255`, y recuperar solo si está en `true`. En `persistOrphanCapture`, exigir `$link->paypal_order_id === $orderId`.

**NF-2 [BAJO · residual de N-2] `createOrder` falla abierto si el `getOrder` de la orden anterior revienta**
- Ubicación: el catch de `PaymentLinkController.php:156-164` sigue de largo y crea una orden nueva, que pisa `paypal_order_id` (`:187-192`).
- Cuándo pasa: tienen que darse tres fallos seguidos. (1) El capture da timeout con el cobro ya hecho. (2) El `getOrder` de recuperación también falla, y el comprador ve "pago fallido". (3) Al reintentar, falla también este `getOrder`.
- Impacto: un segundo cobro, y el webhook del primero termina en `no_match`, porque el `order_id` ya se pisó.
- Remedio: distinguir un 404 de PayPal (orden vencida → crear otra) de un error de red o un 5xx (→ responder 503 "inténtalo en un minuto", sin crear orden).

**NF-3 [BAJO · NO VERIFICADO, requiere sandbox] `PayPal-Request-Id` fijo por orden, sumado a la reutilización de B-6, puede dejar un link impagable después de un `INSTRUMENT_DECLINED`**
- Mecanismo:
  - Si la tarjeta se rechaza, el link sigue `pending` con la misma orden (no se limpia `paypal_order_id`).
  - El siguiente `createOrder` reutiliza esa orden, porque sigue `APPROVED` con el mismo importe (`:115-133`).
  - El nuevo capture sale con la MISMA clave de idempotencia (`PayPalService.php:232`).
  - Si PayPal devuelve la respuesta guardada para esa clave, el comprador vuelve a ver "rechazada" hasta que la orden venza.
- Alcance: el header también afecta ahora al checkout normal. Como allí cada intento crea una orden nueva, la afectación es menor.
- El header no aporta idempotencia dentro del proceso, porque el cliente HTTP no tiene `->retry()`. Entre requests, el doble capture ya lo rechaza PayPal con `ORDER_ALREADY_CAPTURED`.
- Remedio:
  - En el `catch (PayPalCardDeclinedException)` del link, limpiar `paypal_order_id`.
  - O bien, incluir en la clave un contador de intentos.
- Verificarlo en sandbox.

**NF-4 [BAJO · hardening] `Booking::where('payment_reference', $captureId)` con `$captureId` nulo compila a `IS NULL`**
- Ubicación: `PaymentLinkController.php:299` acepta un `captureId()` nulo (una respuesta `COMPLETED` sin `captures[0].id`), y `:465` haría `where payment_reference IS NULL`. Enlazaría el link a cualquier reserva sin referencia (pay_later o Culqi) y la mostraría en la sesión del comprador (`:525`).
- Probabilidad: casi nula, porque PayPal siempre devuelve el id.
- Remedio: `if (! $captureId) throw new \RuntimeException('capture_without_id')` justo después de `:299`.

**NF-5 [BAJO · operativo] Las rutas de recuperación dejan el cobro sin reserva, y en createOrder sin aviso al admin**
- `persistOrphanCapture` no guarda `buyer_*` (`:629-637`), así que el webhook de ese cobro termina en `link_missing_customer_data` (`WebhookController.php:410-423`) y no crea la reserva.
- En el catch de `captureOrder` sí se avisa al admin por correo (`:356,383`). En la rama `COMPLETED` de `createOrder` (`:148-153`) queda solo el log `orphan_capture_persisted`.
- Remedio: pasar `$validated` a `persistOrphanCapture` desde `captureOrder` para que guarde `buyer_*`, y llamar a `notifyAdminOfCaptureWriteFailure` también desde `createOrder`.

### No verificado
- Que PayPal acepte `verify-webhook-signature` con eventos reales, y cómo trata `PayPal-Request-Id` ante un 422 (NF-3). No hay sandbox.
- Todo lo anterior sale de trazar el código. No ejecuté ninguna PoC ni la suite (árbol congelado por QA).
- `composer audit` no se volvió a correr. `composer.lock` no cambió, así que la **CVE-2025-54068 de Livewire** sigue abierta (preexistente).
