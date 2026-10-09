# QA post-fix lote 2026-10-09 (local)
Línea base: bookings=36 (auto_inc 20041, checksum 660432385), payment_links=1 (checksum 1693348261). payment_links NO tiene columna `locale` en BD local (migración 2026_10_08_000001 sin aplicar).
Trampa de medición: `now()` de MySQL = hora local de la máquina (Lima), no UTC; PHP/Laravel = UTC. Fechas de prueba se insertaron con utc_timestamp().

## A · Recordatorio urgente
Reservas QA-T1 (mañana, 1h), QA-T2 (mañana, 3h), QA-T3 (+2 días, 3h).
- `--urgent --dry` → solo QA-T2 (T1 excluida por <2h, T3 por fecha +2d). PASS.
- `--dry` diario → QA-T2 y QA-T3 (T1 excluida por retraso 2h; documentado). PASS.
- `schedule:list`: `0 13 * * *` y `*/15 * * * * --urgent`. Ventana 07–22 y tz Lima por lectura de Kernel (ver abajo).

## B · Botón reenviar recordatorio
- Visible en pendientes (QA-T1, QA-T2); NO visible en pagadas (7 filas pagadas en tab Pagadas, 0 botones) ni cancelada (QA-T3 puesta a cancelled; solo "Reenviar correo"). PASS.
- Modal: email editable precargado. Se cambió a qa2-edit@example.test → log `To: qa2-edit@example.test`, asunto "Your booking is not confirmed yet — complete…" (reserva locale en). `payment_reminder_sent_at`=2026-10-09 02:15:17 UTC (T1/T3 siguen NULL). PASS. Toast de éxito no capturado (dura ~2s); sin evidencia visual, solo el efecto.
## C · Tab Mañana por defecto: PASS (/admin/bookings abre en Mañana con 2: QA-T1, QA-T2).
- "Reenviar correo" en QA-T2 (en): log `Subject: Booking Confirmation — Lima View Tours`. PASS.
- Columna "Recordatorio enviado": activable en Alternar columnas; QA-T2 muestra `08/10/2026 21:15` (UTC 02:15 → Lima 21:15); T1/T3 "—". PASS.

## D · /pagar/{link 4}
- BD local: `payment_links.locale` NO existe (migración sin aplicar, no se corrió). Sin 500 por eso: /pagar/{code} da 200 (es/en/pt); `$link->locale` null cae a ?lang/Accept-Language.
- SDK PayPal: sin lang `locale=es_PE`; ?lang=en `en_US`; ?lang=pt `pt_BR`. PASS.
- JS: createUrl/captureUrl vía @json válidas con `?lang=`; mensajes es/en/pt traducidos ("Please fix the following errors:", "Não foi possível iniciar o pagamento." etc.). PASS.
- /en/pagar/{code} da 404: ruta vive fuera del grupo {locale} por diseño (comentario del controlador), no es defecto.
- Select de idioma: `->in(['es','en','pt'])` en PaymentLinkResource.php:155 (por código). Guardar NO probado (la columna no existe local → fallaría por BD, no por el fix). Opción "Automático" (null) no verificada en vivo.
- Gracias: no se renderizó la página completa (necesita sesión `last_bookings`; render directo falla por $errors fuera de request). Verificada la misma expresión Blade (trans_choice) por locale: es "1 persona (1 adulto, 0 niños)" / 2/0 "(2 adultos, 0 niños)"; en "(1 adult, 0 children)" / "(2 adults…)"; pt "(1 adulto, 0 crianças)" / "(2 adultos…)". PASS a nivel de traducción.

## LIMPIEZA
Borradas QA-T1/T2/T3 (ids 20043-20045). Verificado con SELECT: bookings=36, payment_links=1, QA-T% restantes=0. Checksum bookings 660432385 (línea base) / payment_links 1693348261 (ver salida en el informe final). AUTO_INCREMENT de bookings quedó más alto (20046) — no afecta datos.

## E · Frontend
- Footer aria-labels (curl): /es "Lima View Tours — Inicio" x2 + "Métodos de pago"; /en/contact-us "… — Home" x2 + "Methods of payment"; /pt/contato "… — Início" x2 + "Métodos de pagamento". PASS.
- reCAPTCHA (apagado local, sin tocar settings): por código `partials/recaptcha.blade.php:31,45` y `filament/auth/recaptcha-field.blade.php:11,42,79` usan `$rcHl` es→es, en→en, pt→pt-BR, otro→es. PASS por lectura; no renderizado con clave.
- Login admin (host localhost, sin sesión): carga "Acceso - Lima View Tours", consola 0 errores / 0 warnings. PASS.

## F · Tests (SQLite :memory: confirmado en phpunit.xml:24-25)
`--filter 'PaymentLink|Checkout|Booking|Reminder|Thanks|Paypal'`: 164 passed, 4 skipped (preexistentes), 0 fallos, 653 assertions. BD lima_tours intacta tras la corrida (36/1).

## SEO gate (curl local, 127.0.0.1:8811)
/es, /en, /es/tours, ficha huacachina: 200; sin X-Robots-Tag; meta robots index,follow; canonical propio (host local por Request::root, no staging); 10 hreflang; JSON-LD presente (ficha: TravelAgency, Offer, AggregateRating, Review…); 1 H1 en home/en/tours. Ficha: 2 H1 (`m-title` + `#tour-title`) en tours/show.blade.php, archivo NO tocado por el lote (preexistente).
robots.txt local = `Disallow: /` por APP_ENV!=production (SitemapController:176), esperado; producción no verificable aquí. 301 de slugs: el lote no cambia slugs.

## Veredicto: APTO (con 2 puntos no verificados y 1 Baja preexistente)
