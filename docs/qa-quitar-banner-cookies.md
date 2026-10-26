# QA post-fix: quitar banner de cookies (rama fix/quitar-banner-cookies-2026-10-26, c64a596, base 2eff857)

Estado parcial (volcado intermedio). Veredicto provisional: sin defectos Critico/Alto/Medio hasta ahora.

## Entorno
- Laragon estaba apagado y sin entrada en hosts para lima-tour.test. Arranque httpd y mysqld de Laragon a mano; navegador con Chrome + playwright-core y `--host-resolver-rules=MAP lima-tour.test 127.0.0.1` (host real, no artisan serve). curl con `--resolve`.
- Cambios ajenos sin commitear (ImagePath, RouteConflict, RouteRegistry): no tocados, sin stash.

## Punto 5 Tests: VERIFICADO
phpunit.xml usa sqlite `:memory:` (lineas 24-25). `view:clear` previo. ConsentModeTest + AnalyticsHealthTest + SecurityHeadersCspTest + ConversionEventsTest: OK 24 tests, 189 assertions.

## Punto 7 Huerfanos: VERIFICADO
Grep en resources/ app/ lang/ public/js routes config de `cookie_banner|cookie-banner|lvt_consent|lvtConsent|cookie_preferences|common.cookie|data-cookie-pref`: solo quedan `var(--cookie-banner-h, 0px)` (checkout.blade:494,647,648; app.blade:290; tours/index:346; tours/show:284), todos con fallback 0px, inocuos. Fuera de esas rutas: migracion 2026_10_12_000005_force_cookie_banner_enabled.php (historica, escribe un Setting inexistente ya; inocua) y un test que afirma ausencia de campos.
Control: en la base 2eff857 el mismo patron da 7 coincidencias en app.blade y 1 en footer; ahora 0.

## Punto 1 HTML renderizado: VERIFICADO (rama "sin ID")
Paginas: /es, /en, /pt, ficha city-tour-cusco, /es/carrito (/es/checkout redirige a carrito), /es/contacto, privacidad es/en/pt, /es/tours: todas 200, `gtag('consent','default'` x1 con granted (los 4), 0 "denied", 0 legado, `window.lvtTrack =` presente. default aparece en byte ~6.7k-9k, antes de cualquier tag.
IDs de GTM/GA4/Pixel (`seo_google_analytics_id`, `seo_gtm_id`, `seo_facebook_pixel`) vacios en local: no se emite gtag/js, gtm.js ni fbevents (rama "sin ID" confirmada, Settings sin rellenar). Rama "con ID" cubierta solo por ConsentModeTest (sqlite), NO en HTML vivo.

## Punto 2 Navegador: PARCIAL
- 10 URLs x 375 y 1366: 0 pageerror, 0 violaciones CSP (listener securitypolicyviolation), 0 banner visible, 0 `li` de footer con "cookie", sin overflow horizontal, `--cookie-banner-h` vacio (cae a 0px).
- WhatsApp flotante: 24px del borde inferior / 20px derecha en todas; en ficha a 375 sube a 92px sobre la barra de reserva (captura revisada: sin solape).
- Flujo con clic (1366): ficha -> fecha -> RESERVAR AHORA -> /es/carrito -> "Continuar a Datos y Pago": dataLayer persistido entre paginas muestra view_item, add_to_cart, begin_checkout (gtag y dl). 0 CSP.
- Consola: 404 de `/storage/tours/Green-gardens...jpeg` en home/tours (dato local, ajeno al fix) y 400 de PayPal sdk con client-id de prueba `sb-test-client-id-CRO-AUDIT` (entorno local, ajeno).
- PENDIENTE: clic en WhatsApp -> whatsapp_click; checkout (paso pago) a 375; footer capturado (hueco).

## Punto 3 Privacidad es/en/pt: PARCIAL
Las tres rutas responden 200 sin errores. PENDIENTE: leer el texto s4 renderizado y confirmar que no hay claves crudas.

## Punto 6 Gate SEO: PENDIENTE
## Punto 4 Filament: PENDIENTE (sesion no comprobada)

## Defectos
Ninguno atribuible al fix hasta ahora. Ajenos/entorno: 404 de imagen local (Bajo), PayPal 400 con client-id de prueba (entorno).

---
# Cierre (actualiza lo anterior)

## Punto 2: VERIFICADO salvo 2 huecos
- Clic real en boton flotante WhatsApp (home, 1366): dataLayer recibe `whatsapp_click` (navegacion cancelada en el test para no salir).
- Footer 1366 capturado: columna Enlaces termina en "Politica de privacidad", sin hueco ni boton de cookies.
- NO verificado: paso de pago del checkout a 375 px con captura; barra de reserva movil fuera de la ficha (solo ficha a 375 revisada).

## Punto 3: VERIFICADO
es/en/pt: texto s4 renderizado nombra GA, GTM, Clarity, Hotjar y Meta Pixel en cada idioma; sin claves crudas (`privacy_s|legal.|common.` = 0).

## Punto 6 Gate SEO: PASS (en local)
Ficha, home es/en/pt, contacto, privacidad, tours: 1 H1, title y description presentes, canonical propio (host lima-tour.test, no staging), 4 hreflang, JSON-LD parsea (ningun PARSE_ERR; ficha con Product/TouristTrip), robots `index,follow` salvo /es/carrito `noindex,nofollow` (comportamiento previo, el diff no toca meta). Ningun X-Robots-Tag. robots.txt local da `Disallow: /` por entorno (SitemapController, no tocado por el diff): robots de PRODUCCION NO verificado. 301/slugs: el diff no cambia rutas.

## Punto 4 Filament: NO VERIFICADO en vivo
No habia sesion reutilizable (el navegador MCP estaba tomado y mi Chrome es perfil limpio; no se piden credenciales). Cubierto solo por AnalyticsHealthTest::test_settings_has_no_banner_fields_and_saves_with_legacy_zero (Livewire: sin campos cookie_*, `save` sin errores, pagina sin banner). El diff de Settings.php solo borra la pestana y el relleno de cookie_text_*.

## No verificado
- Admin /admin en navegador real.
- HTML vivo con IDs de GTM/GA4/Pixel (solo test sqlite); orden real de carga con IDs y GTM dentro de produccion.
- robots.txt de produccion y canonicals en produccion.
- Checkout a 375 px, pago real, purchase.
- Hotjar/Clarity: cargan via contenedor GTM ajeno, no probable en local.

## Defectos
- Critico/Alto/Medio: ninguno atribuible al fix.
- Bajo, ajeno: 404 de `/storage/tours/Green-gardens-and-palm-trees-line-the-historic….jpeg` (dato local, home y listado).
- Entorno, ajeno: PayPal SDK 400 con client-id de prueba `sb-test-client-id-CRO-AUDIT` en carrito.
- Observacion (no defecto): quedan `var(--cookie-banner-h, 0px)` en 6 sitios, inocuos; puede limpiarlos maquetador-frontend cuando convenga.
- Cambios ajenos sin commitear (ImagePath, RouteConflict, RouteRegistry): no causaron ningun fallo.

## Veredicto: APROBADO (condicionado a los NO verificados de arriba; el admin vivo conviene mirarlo antes de desplegar)
