# QA lote PayPal + cards + admin Reservas (2026-10-05)

Veredicto: NO APTO (1 defecto ALTO F-1 en /tours a 375px). Bloques 3 y 4 se re-verifican tras el fix de F-1 por indicación del coordinador.

## Entorno (LIMPIADO al terminar)
- Servidores 8231 (WT), 8232 (HEAD) y 8233 (WT con PAYPAL_CLIENT_ID=QA_STUB_CLIENT) apagados por PID (9060,2060 / 13176,18852 / 6416,20288); netstat confirma los 3 puertos libres.
- SQLite Temp/qa_lima.sqlite borrada; Temp/qa_head borrada (primero rmdir de la junction vendor; vendor del proyecto intacto, comprobado).
- .env intacto; sin tocar usuarios existentes (usuario qa-tmp creado solo en la SQLite ya borrada). Efecto colateral: storage/logs/laravel.log (ignorado por git) recibió líneas de error cURL 60 de mis pruebas.

## Bloque 1 · Suite completa — HECHO
`php -d memory_limit=512M vendor/bin/phpunit` (salida literal): `OK, but some tests were skipped! Tests: 406, Assertions: 3133, Skipped: 13.` Time 09:33. Sin fallas. No comparé contra HEAD (no hay falla que comparar). Los 13 skips no los desglosé (Culqi=4 según backend).

## Bloque 3 · Filtros /es/tours — PARCIAL (es en 375/768/1440; faltan en/pt)
Clic real sobre cada filtro visible (10 inputs: 3 destinos, 3 duraciones, es, en, grupal, oferta); oráculo = atributos data-* de las cards del contenedor correspondiente. 375/768/1440: todos OK (destino lima 2, ica 3, cusco 1, medio-dia 1, full-day 4, 2-dias 1, idiomas/grupal/oferta 6), texto de conteo coherente, desmarcar vuelve a 6, sin overflow (scrollWidth=clientWidth), 0 errores de consola/red. Slider de precio y orden NO probados.

### DEFECTO ALTO F-1 — a 375px (<640) se ven las cards DUPLICADAS (12 en vez de 6)
- Medido: a 375 `[data-tour-cards]` #1 (`flex flex-col sm:hidden`) display=flex Y #2 (`sm:grid ...`) display=block; baseline 12 cards visibles con el texto "6 tours encontrados". Tras filtrar (ej. Lima) 4 visibles con texto "2 tours encontrados". A 768 es correcto (#1 none, #2 grid).
- Causa: el HTML trae `class="hidden sm:grid..."` (resources/views/tours/index.blade.php:432) y tour-filters.js `applyFilters()` hace `container.classList.toggle('hidden', visible === 0)` (resources/js/tour-filters.js ~línea 100): con visible>0 QUITA el `hidden` del contenedor de escritorio y en móvil pasa a display:block. Se ejecuta ya en init.
- El informe del front reporta "12 (row)" a 375: eran las 12 cards duplicadas, no 12 tours (hay 6).
- Devolver a: maquetador-frontend. Pendiente verificar en en/pt (misma lógica).
- Check al revés: a 768 el mismo script da 6 (no es artefacto del instrumento).

## Bloque 4 · Cards — PARCIAL (a re-verificar tras F-1)
Barrido es/en/pt, home, /tours, ficha, 375 y 1440, solo cards con enlace a /tours/detalle: textos prohibidos (BEST SELLER, OFERTA ESPECIAL, MÁS VENDIDO, DESTACADO, INCLUYE) = 0 en en/pt y en es; en es mi regex 'inclui' dio falso positivo con 'Recojo incluido' (es un feature legítimo, innerText leído). 4 features visibles por card en todas. Carrusel Experiencias del home sin MÁS VENDIDO/DESTACADO. Card de HEAD tenía 'CUPOS LIMITADOS', 'Oferta especial' y rating inventado; el WT muestra rating solo si hay reseñas reales (en mi BD no hay): no es defecto.
- Truncado 'Cancelación gratuita' (span 90/105 px a 375): IDÉNTICO en HEAD (puerto 8232) -> PREEXISTENTE, Bajo. Truncado del título de card-row: no reproducido en las 2 primeras cards; no comparado vs HEAD en el resto.
- Ficha 375: scrollWidth 384>375 por .m-rec-side, idéntico en HEAD -> PREEXISTENTE, Bajo (no del lote).
- A 375 en /tours el listado sale duplicado (ver F-1; 12 cards en vez de 6).

## Bloque 5 · Admin /admin/bookings — HECHO, PASS
Setup: reloj en el momento de la prueba Lima 2026-10-05 19:38, UTC 2026-10-06 00:38 (días distintos). 7 reservas sembradas: hoy-Lima 20:00 (created_at 01:00 UTC, pagada), hoy-b, mañana, mañana-pagada, +2 (fallida), ayer, y una con fecha = fecha UTC (06/10). Login real con clic, 1440 y 375.
Tabs/contadores leídos de la UI y filas contadas con espera tras cada clic (la 1a pasada leyó filas obsoletas por lectura temprana: artefacto de medición, descartado y repetido con 3.5-9 s):
Todas 7 -> 7 filas | Pagadas 2 -> 2 | Pago pendiente 4 -> 4 | Fallidas 1 -> 1 | Salidas de hoy 2 -> 2 (hoy-lima-20h, hoy-b; la de fecha UTC NO entra) | Mañana 3 -> 3 (manana, manana-paga, hoy-utc) | Salidas de esta semana 6 -> 6 (todas menos ayer). Coinciden con lo esperado calculado aparte con fechas de Lima. 375: Todas 7, Mañana 3, Hoy 2, Semana 6, sin overflow; 0 errores consola/red. Tab 'Mañana' existe; 'Todas' trae contador.
No verificado: reserva creada con hora real >20:00 Lima en otro huso del servidor (solo se usó la divergencia real UTC/Lima de este momento); tab con datos de MySQL.

## Bloque 2 · Checkout es/en/pt — HECHO con SDK de PayPal SIMULADO (PASS parcial)
Flujo con clic real: ficha -> (fecha puesta vía store Alpine) Reservar -> /carrito -> Continuar -> datos (nombre/correo/tel) -> Continuar -> pago. Servidor 8233 con client-id falso; el SDK de paypal.com se interceptó con un stub (FUNDING.CARD/PAYPAL, isEligible=true) porque no hay client-id ni red válida.
es/en/pt x 1440/375 (6 combinaciones, resultados idénticos): 3 pasos avanzan; #card-buttons (top 1437/1598 px) queda ARRIBA de #paypal-buttons (1524/1685), 1 hijo cada uno, separador visible, sin overflow horizontal (1440/1440, 375/375); sin marcar terms, clic en tarjeta -> alert de términos y NO se envía create (validation_failed); con terms -> POST /checkout/paypal/create con customer_name/email/phone (+51987654321) y accept_terms=1 en ambos botones.
Backend vía fetch en sesión (es): sin accept_terms -> 422; nombre >255 -> 422; payer vacío con terms -> 500.
Los 500 vienen de 'cURL error 60: SSL certificate problem' hacia api-m.sandbox.paypal.com/v1/oauth2/token (log): entorno (Avast), no código; no se pudo ejercer el reintento sin payer ni el prellenado real contra PayPal (cubierto solo por PaypalPayerSanitizationTest en la suite).
Observaciones Bajo/preexistentes: el alert de términos sale en español también en en/pt (el literal 'Debes aceptar los términos' ya está en HEAD, 1 ocurrencia); mensaje de validación 422 del nombre en inglés bajo /es. Consola: solo los 500 anteriores.

## Bloque 6 · Gate de regresión SEO — HECHO (con huecos)
Comparación HEAD (8232) vs WT (8231), es/en/pt × home, /tours, ficha machu-picchu-full-day: title, meta description, robots, og:*, canonical, hreflang (es/en/pt/x-default) IDÉNTICOS (diff vacío en 9 pares, 19 líneas cada uno, extracción no vacía). robots meta = index,follow en todas. Sitemap 200 con 51 <loc> (igual que HEAD). X-Robots-Tag: ausente.
- robots.txt: con APP_ENV=local sale `Disallow: /` (rama no-production de SitemapController::robots, sin cambios en el lote). Rama production leída: Allow: /, no bloquea CSS/JS. No ejecutada con APP_ENV=production.
- H1: home 0, tours 1, ficha 2 (móvil+escritorio) — idéntico a HEAD, preexistente (home con 0 H1 en esta BD sin settings; no concluyente).
- JSON-LD: 0 bloques en ambos (viene de Settings no sembrados): NO VERIFICABLE aquí. schema-raw.blade.php y layouts no están en el diff.
- Slugs: routes/web.php solo elimina /_diag/mail (positivo); sin slugs cambiados.
- og:image apunta a /assets/banners/banner-hero.jpg (200 local).
- Resultado: PASS parcial; no hay FAIL.

## No verificado (alcance numerado)
1. Botones reales de PayPal (FUNDING.CARD/PAYPAL) y prellenado del payer contra sandbox: sin client-id ni TLS (stub).
2. create-order que llegue a PayPal: reintento sin payer, application_context (backend no migró a payment_source, sin probar).
3. Filtros en en/pt, slider de precio y orden (bloque 3 en es OK a 375/768/1440 salvo F-1).
4. Cards: truncado del título de card-row en el resto de cards, y comparación HEAD completa; re-verificar tras F-1.
5. JSON-LD (0 bloques en BD sin settings) y robots.txt en APP_ENV=production (solo código leído).
6. 13 skips de la suite no desglosados.
7. Admin con MySQL, y reserva con hora > 20:00 Lima en servidor real.
8. Que F-1 exista también en en/pt (misma lógica, no medido).

## Defectos
- ALTO F-1 (maquetador-frontend): resources/views/tours/index.blade.php:432 + resources/js/tour-filters.js (applyFilters, toggle de 'hidden' sobre contenedor): a <640px el contenedor de escritorio pierde 'hidden' y se ven 12 cards en vez de 6, conteo dice 6.
- Bajo preexistentes: 'Cancelación gratuita' truncado a 375; overflow 9px en ficha 375 (.m-rec-side); H1 home=0 en BD sin settings y ficha=2 (móvil+escritorio), idénticos a HEAD.

## Re-verificación 2026-10-05

Entorno: SQLite desechable en %TEMP% (migrate+seed, 6 tours), serve 127.0.0.1:8341, Chrome headless + playwright-core, scroll por pasos, medición getComputedStyle/getBoundingClientRect (visible = rect>0 y ningún ancestro display:none). `.env` intacto.

### B3' Filtros /tours es/en/pt x 375/768/1440 — PASS (F-1 CERRADO)
Clic REAL (mouse de Playwright): botón "Filtros" [data-filters-open] a 375/768 (panel aria-hidden=false), checkbox destino "lima", checkbox de duración. Formato visibles / contador:
- Sin filtro: 6 / "6 tours encontrados|found" en los 9 casos; contenedores móvil/escritorio = flex/none a 375 y none/grid a 768/1440 (ya no hay duplicado).
- Destino lima: 2 / 2 en los 9 (el botón del drawer dice "Ver 2 tours|See 2 tours").
- lima + duración 2-dias (0 coincidencias): 0 visibles, contador "No se encontraron tours" / "No tours found" / "Nenhum tour encontrado", mensaje vacío visible en su idioma ("No encontramos tours...", "We could not find tours...", "Não encontramos tours..."); contenedores none/none.
- Desmarcar lima: vuelve a 6 / 6 en los 9 (contenedores restituidos).
- scrollWidth = clientWidth en todos los casos (sin overflow). 0 errores de consola/red.
- es 1440: slider (arrastre real a ~40%) -> "Hasta US$205", 3 tours (100,100,65) = correcto; orden precio desc 420,300,220,100,100,65 y asc 65,100,100,220,300,420. (Un primer clic mío sobre el slider cayó fuera de pantalla y no movió nada: error de instrumento, repetido con scrollIntoView.)
- Observación (Bajo, no del fix): a 375 el banner de cookies tapa el botón "Ver N tours" del pie del drawer (z-index sobre el panel); el cierre por la X del encabezado funciona. Medido solo con banner de primera visita.
- Check al revés: el contador del instrumento da 0 en el caso vacío (no es siempre >0) y 6 (no 12) a 375.

### B4' Cards es/en/pt x 375/1440 (home, /tours, ficha machu-picchu-full-day) — PASS
Cards = article.tour-card / article.tour-card-row visibles; regex (BEST SELLER, OFERTA ESPECIAL/SPECIAL OFFER, MÁS|MAIS VENDIDO, DESTACADO/FEATURED, INCLUYE/INCLUDES/INCLUI) sobre innerText Y textContent (detecta ocultos) de cada card: 0 coincidencias en las 18 combinaciones. Features visibles: 4 en todas las cards (home 8 cards, /tours 6, ficha 3 relacionados a 1440). /tours a 375: 6 cards (antes 12 por F-1). Sin overflow nuevo: scrollWidth=clientWidth salvo ficha a 375 = 384 (idéntico a mi medición previa, PREEXISTENTE .m-rec-side). 0 errores de consola.
- Aclaración: en la ficha a 375 los relacionados no son article.tour-card (0 cards; patrón móvil distinto), pero el body completo no contiene ningún texto prohibido. En en/ aparece "special offer" solo en el pie (lang/en/footer.php:5, newsletter), no en cards.
- Check al revés: el regex sí detecta (la 1a pasada previa dio falso positivo con "Recojo incluido"; aquí \bincluye\b no lo toca y 'incluido' queda fuera a propósito).

### B2' Checkout /en y /pt a 375 (+ /es de control) — PASS
Flujo con clic real a 375px: ficha machu-picchu-full-day (fecha +10 días puesta vía store Alpine) -> RESERVAR -> /carrito -> #cart-cta-main (datos) -> nombre/email/teléfono -> #cart-cta-main (pago) -> "pagar después" -> clic en #cart-cta-main SIN marcar términos (#accept_terms checked=false). alert() capturado por el handler de diálogos:
- es: "Debes aceptar los términos y condiciones para continuar."
- en: "You must accept the terms and conditions to continue."
- pt: "Você deve aceitar os termos e condições para continuar."
Coincide con lang/{es,en,pt}/checkout_paypal.php:29 y con html lang. Check al revés: cada idioma devuelve su texto (no el español fijo de antes); si el literal fuera fijo, en/pt habrían dado español.
- No verificado: los otros 4 alert() (validate_datos_fields, validate_email, validate_empty_cart, validate_customer_fields; solo comprobado que las claves existen vía el diff) ni la línea `data.errors?.accept_terms?.[0]` de ppCreateOrder (exige SDK PayPal real); el corredor del front tampoco la ejecutó.
- Observación (Bajo, preexistente/no del fix): en primera visita el banner de cookies cubre el CTA sticky #cart-cta-main a 375 (clic interceptado hasta aceptar cookies). Lo hice aceptar con clic real antes de medir.

### Suite completa (re-verificación)
`php -d memory_limit=512M vendor/bin/phpunit` salida literal: `Time: 09:09.271, Memory: 156.00 MB` / `OK, but some tests were skipped!` / `Tests: 406, Assertions: 3133, Skipped: 13.` Idéntico a la corrida previa (406/3133/13), sin fallas. Los 13 skips siguen sin desglosar.

### Limpieza del entorno
serve 8341 apagado por PID (5620, hijo de 4180; taskkill /T), netstat: 0 LISTEN en 8341. Chrome headless: cada script cerró su browser (browser.close sobre instancia propia, no CDP). SQLite %TEMP%\qa_lima2.sqlite borrada (ls confirma inexistente). `.env` intacto; sin cambios de código míos (árbol congelado).

### Veredicto final del lote: APTO
F-1 cerrado (B3' en 9 combinaciones con clic real), B4' y B2' PASS, suite 406/0 fallas; bloques 1, 2, 5, 6 ya PASS del informe. Observaciones Bajas no bloqueantes: banner de cookies tapa el botón "Ver N tours" del drawer y el CTA sticky del checkout a 375 en primera visita; ficha 375 overflow 9px preexistente. No verificado: pago real con PayPal/sandbox, `errors.accept_terms` en ppCreateOrder en navegador, JSON-LD, robots.txt en production, 13 skips.
