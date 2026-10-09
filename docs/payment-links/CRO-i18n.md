# CRO i18n — /pagar/{code} en EN/PT (Estado 1, pre-fix)

Fecha: 2026-10-08. Alcance acotado. Medición: HTTP real contra `php artisan serve` (:8765) con la BD local, link id 4 (`pending`, tour 2, US$33.00, sin fecha), header `Accept-Language` en/pt/es. NO se usó navegador (PayPal SDK no renderizado) ni se creó/modificó ningún dato. MySQL local estaba apagado: se arrancó mysqld de Laragon para poder leer.

## Resultado: RECHAZADO para el objetivo "cliente extranjero lo ve completo en su idioma"

El cuerpo de la página sí sale en EN/PT (verificado). Fallan: idioma no controlable por el admin, la página de gracias hardcodeada en español, SDK PayPal en es_PE, y varios textos sueltos.

## Defectos

### Crítico
1. **No hay forma de fijar el idioma del link (caso real: cliente extranjero con navegador en español, o link reenviado).**
   - `app/Http/Controllers/PaymentLinkController.php:581-594` (`resolveLocale`): solo Accept-Language; sin `?lang=`, sin campo en `payment_links`, sin selector en `PaymentLinkResource`. Cualquier navegador/dispositivo en español (o inglés no soportado, p.ej. `fr`) cae a `es` (`config('app.locale')`). Probado: Accept-Language `es` → todo en español.
   - Consecuencia en cadena: la reserva guarda ese locale (`:218`, `:497` -> `BookingCreationService.php:78`), por lo que el correo de confirmación también sale en español.
   - Asignar a: backend-laravel (columna `locale` en payment_links + selector en admin + `resolveLocale` que lo prioriza; opcional `?lang=` en la URL copiable).

### Alto
2. **Página de gracias tras pagar 100% en español, solo en `checkout/thanks.blade.php`** (la redirección sí lleva el locale, `PaymentLinkController.php:441,530`; el título/h1 sí traducen). Textos fijos:
   - `:10` description "Tu reserva en Lima View Tours ha sido confirmada. Recibirás un email con los detalles."
   - `:50` "Detalle de tu reserva"; `:76-77` "N personas" / "(N adultos, N niños)"; `:90` "Referencia:"; `:101` "Confirmada" (y `:103` "Pendiente de pago"); `:120` "Volver al inicio"; `:128` "No hay detalles de reserva disponibles."; `:129` "Ver tours".
   - Afecta también al checkout normal. Asignar a: maquetador-frontend (claves en lang/{es,en,pt}).
3. **PayPal SDK con `locale=es_PE` fijo** — `resources/views/payment-links/show.blade.php:152` (confirmado en el HTML servido en EN y PT). Botones y ventana de PayPal salen en español para el extranjero. Debe derivarse de `$locale` (en_US / pt_BR / es_PE). No verificado visualmente (SDK no renderizado). Asignar a: maquetador-frontend.
4. **Mensajes JS en español hardcodeados** — `show.blade.php`:
   - `:203` `'No se pudo iniciar el pago.'`
   - `:237` `'No pudimos completar el pago.'`
   - `:241` `'Ocurrió un error con PayPal. Por favor recarga la página e inténtalo de nuevo.'` (el que verá un extranjero ante cualquier fallo del SDK/popup)
   Usar `@json(__('...'))` como ya se hace en `:181`. Asignar a: maquetador-frontend (+ claves lang).

### Medio
5. **Texto de confianza nombra al proveedor equivocado** — `lang/{es,en,pt}/checkout.php:54`: "100% secure payment with Culqi" / "Pago 100% seguro con Culqi" / "Pagamento 100% seguro com Culqi", mostrado en `show.blade.php:142` donde el pago es PayPal. Afirmación falsa al cliente. Asignar a: backend-laravel/maquetador (copy; decide Anyerson el texto).
6. **No existen `lang/en|pt/validation.php`**: los errores 422 de `captureOrder` (`PaymentLinkController.php:221+`, `data.message` mostrado en `show.blade.php:237`) salen en inglés con atributo crudo ("The customer email field must be a valid email address.") también en PT y ES (probado con tinker en locale pt). El JS solo valida nombre/email vacíos (`:180`), no formato de email. Asignar a: backend-laravel.
7. **Fecha del viaje con mes siempre en inglés** — `show.blade.php:100` `format('d M Y')` y `checkout/thanks.blade.php:69` (Carbon `format`, no `isoFormat`): en ES/PT sale "08 Oct 2026"/"May" etc. Para PT/ES debería usar `->locale($locale)->isoFormat('D MMM YYYY')` (el correo ya lo hace). No medido en vivo (el link de prueba no tiene fecha: lectura de código). Asignar a: maquetador-frontend.
8. **Mensajes "captured_booking_pending" y errores de servidor**: sí traducidos (lang `payment_links`/`booking` con paridad es/en/pt verificada), pero heredan el defecto 1 (idioma = navegador).

### Bajo
9. "1 Adults" — plural fijo (`show.blade.php:95`, `lang/en/ui.php:27`). Verificado en EN/PT ("1 Adultos"). Sin singular.
10. Etiqueta de teléfono "Phone (Peru)" / "Telefone (Peru)" (`lang/*/checkout.php:19`) en un formulario para extranjeros (campo opcional).
11. Reenvío de correo desde admin (`BookingNotifier.php:61`): `__('payment_links.date_to_be_arranged')` en `emails/bookings/confirmed.blade.php:95` usa el locale de la app (es en admin), no el de la reserva, cuando no hay fecha. El resto del correo sí usa `$locale` de la reserva.
12. `/pagar/{code}` página no-usable/404: no verificada con dato real (no se crearon links expirados/pagados); las claves existen en los 3 idiomas y la vista las usa (`show.blade.php:62-79`). El 404 de código inexistente en EN/PT sí responde con textos traducidos.

Conteo: Crítico 1 · Alto 3 · Medio 3 (+1 derivado) · Bajo 4.

## Verificado OK (con evidencia)
- EN y PT en `/pagar/{code}`: `<html lang>` correcto, `<title>`, meta description/og/twitter, h1 "Complete your payment"/"Complete seu pagamento", etiquetas Full name/Email address/Phone, Passengers/Passageiros, Total, nombre del tour traducido ("Lima City Tour with Catacombs" / "City Tour Lima com Catacumbas"), cabecera, menú y footer traducidos.
- Paridad de claves `lang/{es,en,pt}/payment_links.php`.
- Correo de confirmación (`app/Mail/BookingConfirmed.php:29-46`, `emails/bookings/confirmed.blade.php`): asunto y cuerpo EN/PT/ES según `bookings.locale` (que es el locale de la petición de captura). Correcto salvo defecto 1 y bajo 11.

## No verificado
- Render real de botones PayPal, flujo de pago/captura (CSRF, credenciales PayPal; client-id local es `sb-test-client-id-CRO-AUDIT`), página de gracias renderizada con una reserva real (leída por código), links pagado/expirado/cancelado, contraste/foco/responsive (fuera de alcance).

## Contrato SEO
No aplica: `/pagar/{code}` es noindex,nofollow privado (X-Robots-Tag verificado en código `PaymentLinkController.php:79`); no hay superficie SEO.
