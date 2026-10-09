# QA post-fix · lote 2026-10-08 (Estado 3)
Entorno: `php artisan serve` 127.0.0.1:8792 (lima-tour.test no responde), MAIL_MAILER=log forzado por env (el .env local trae smtp). BD local lima_tours. Línea base: bookings=36, payment_links=1 (id 4, locale NULL).

## Bloque A · Recordatorio 13:00 Lima
- `schedule:list`: `0 13 * * *  bookings:send-payment-reminders`. Tinker sobre el Schedule: expression `0 13 * * *`, timezone `America/Lima`. PASS.
- Momento de la prueba: UTC 2026-10-09 00:27, Lima 2026-10-08 19:27 (el día UTC ya iba un día adelantado: caso que discrimina el bug anterior). `BookingCalendar::today()` = 2026-10-08.
- Reservas de prueba insertadas (pay_later/pending/pending): QA-TEST-MANANA (10-09), QA-TEST-HOY (10-08).
- `--dry`: detecta las 2 ("Simulación: 2 reserva(s)"), `payment_reminder_sent_at` sigue NULL. PASS.
- Sin --dry (mailer log): "Recordatorios enviados: 2", ambas con `payment_reminder_sent_at` marcado; log `booking.payment_reminder.sent` x2. PASS.
- 2.ª corrida: "No hay reservas por recordar." (no reenvía). PASS.
- Las 4 pay_later pendientes preexistentes (ids 30,33,34,20040) son de fechas pasadas: no entran en la ventana, no se les envió nada.

## Bloque B · Admin Reservas tab "Mañana"
Playwright 1440x900, login admin, carga directa de /admin/bookings (sin query):
- Orden de tabs: Mañana, Todas, Pagadas, Pago pendiente, Fallidas, Pago en riesgo, Salidas de hoy, Salidas de esta semana (a11y tree). PASS.
- Activo por defecto: al cargar sin `?activeTab` la tabla muestra solo QA-TEST-MANANA (fecha 9 oct) con badge "Mañana 1". SQL `travel_date='2026-10-09'` = 1. PASS (el badge cuadra; fecha Lima, el día UTC ya era 9).
- Clic Todas (`?activeTab=all`, badge 38 = 36 + 2 de prueba), Pagadas (10 filas, todas "Pagado"), Salidas de hoy (solo QA-TEST-HOY = SQL), Pago pendiente, y vuelta a Mañana: todos responden. PASS.
- Nota de medición: a viewport <1024 el overlay del sidebar bloquea los clics en los tabs (preexistente de Filament); medido a 1440.

## Bloque C · Links de pago traducibles
- Admin (/admin/payment-links, 1440px): columna "Idioma" presente en la tabla; en editar id 4 aparece el Select "Idioma del cliente" (vacío = "Automático por navegador"); elegí English, Guardar -> SQL `locale='en'`; la tabla muestra "English". PASS.
- "Copiar enlace" (clic real en la tabla): no navega a Editar (la URL queda en la lista). No pude leer el portapapeles/toast (sin evaluate): el copiado en sí queda NO VERIFICADO; el defecto previo (navegar a Editar) no reaparece.
- Crear link nuevo desde el formulario: NO probado (reutilicé id 4; evité crear filas). El Select es el mismo componente de crear/editar.
- /pagar/{code} con link locale=en y Accept-Language es, en Playwright: `<html lang=en>`, título "Pay Lima City Tour with Catacombs", H1 "Complete your payment", "1 Adult", "Full name *", "100% secure payment with PayPal"; menú y selector en inglés. SDK pedido con `locale=en_US`. PASS.
- `?lang=pt`: html lang=pt, "Complete seu pagamento", "1 Adulto", "Nome completo *", SDK `pt_BR`, mensaje JS "Ocorreu um erro com o PayPal…". `?lang=es`: es_PE, texto español. Sin parámetro con link en: en (el link manda sobre el navegador). PASS.
- Link en automático (locale NULL), curl con Accept-Language: es->es/es_PE, en->en/en_US, pt-BR->pt/pt_BR, fr->es (fallback). PASS.
- Botones PayPal renderizados: NO VERIFICADO. El SDK responde 400 en consola porque el client-id local es de prueba (`sb-test-client-id-CRO-AUDIT`): es de entorno, la URL sí lleva `locale=en_US`. Por eso tampoco se dispara el error JS en el navegador; se verificó el texto JS embebido en el HTML servido (en/pt) y la respuesta del servidor.
- Validación en /pagar/{code}/paypal/capture con cuerpo vacío: pt -> "O campo pedido é obrigatório.", "O campo nome completo é obrigatório.", "O campo endereço de e-mail é obrigatório."; en -> "The order field is required.", "The full name field is required.", "The email address field must be a valid email address." Nombres de campo legibles. PASS. `/paypal/create` vacío devuelve error de pago traducido (pt/en/es). Prueba es en capture: throttle de la ruta (429 por mis propias pruebas); es verificado en contacto.
- Regresión global validation.php: POST /es/contacto con datos vacíos -> "El campo nombre es obligatorio.", "El campo email debe ser un correo electrónico válido.", "El campo mensaje es obligatorio." Sin claves crudas. PASS. /en/contact y /pt/contato vía contacto: bloqueados por el throttle (429) tras mis pruebas, NO VERIFICADOS por esa ruta (en/pt sí cubiertos en capture).
- Página de gracias: renderizada con la reserva QA-TEST-MANANA (vista `checkout.thanks` con el layout completo) en es/en/pt: textos traducidos ("Thank you for your booking!", "Your booking details", "Status: Payment pending", "Back to home" / pt "Obrigado pela sua reserva!", "Status: Pagamento pendente") y fechas localizadas ("09 Oct 2026"/"09 out 2026"). Cero claves crudas (regex `checkout|payment_links|validation.xxx`) en los 3 idiomas; es conserva su texto original. /es y /en `checkout/gracias` sin sesión: 200 y sin claves crudas. PASS.
- BAJO: "1 persona (1 adultos, 0 niños)" / "(1 adults, 0 children)" / pt "(1 adultos, 0 crianças)": `checkout.pax_breakdown` no usa singular. Archivo: lang/*/checkout.php (clave pax_breakdown) + resources/views/checkout/thanks.blade.php:76. Agente: backend-laravel. Preexistente en es.
- BAJO: footer de /pagar en inglés conserva `aria-label`/alt "Lima View Tours — Inicio" en español (compartido en todo el sitio, no del fix). Agente: maquetador-frontend.

## Bloque SEO · Gate de regresión (curl, local 127.0.0.1:8792)
Alcance del fix: Kernel, comando artisan, ListBookings, PaymentLinkResource/Controller, lang/*, vistas `checkout/thanks` y `payment-links/show`. `git diff --stat` sobre layouts, components, public y routes: vacío (no se tocó nada global del front público). Aun así se midió:
1. noindex: `/es`, `/en`, `/es/tours`, ficha `/es/tours/detalle/tour-de-dia-completo-al-oasis-de-huacachina-islas-ballestas-en-paracas-2`: 200, `meta robots = index,follow,max-image-preview…`, sin cabecera X-Robots-Tag. `/` responde 302 a `/es` (redirección de idioma, esperada). PASS.
2. robots.txt: local devuelve `Disallow: /` por `APP_ENV=local` (SitemapController::robots, líneas 170-180, rama no-producción; archivo no está en el diff). La rama de producción y que no bloquee CSS/JS NO SE PUEDE verificar localmente: queda para el post-deploy (deployer). Parcial.
3. Canonical: presente y autorreferente en las 4 URLs (apunta al host de la petición; en producción saldrá del host real). PASS.
4. 301: no se cambió ningún slug en este lote. N/A (PASS).
5. H1/jerarquía: 1 H1 en /es, /en, /es/tours y ficha (el conteo `grep -c` de la ficha daba 2 por líneas; extracción directa devuelve un solo `<h1 class="m-title">`). Saltos de nivel en h2-h6: no auditados (no es alcance del fix). PASS.
6. Datos estructurados: JSON-LD presente (/es 2 bloques, /en 2, /es/tours 1, ficha 2). Parseo con validador de schema: NO verificado; solo presencia.
7. Hreflang/title/meta: es/en/pt/x-default correctos en /es, /es/tours y ficha; titles y descriptions con contenido. PASS. Fix global: no aplica ampliar al sitio completo (no hay cambio global de layout/CSS/functions), declarado.
