# Humo prod quitar banner de cookies 26/10/2026 (solo lectura)

Perfil Chrome nuevo por ejecucion (playwright-core). Salida: scratchpad/humo-prod/*.json

## Bloque 1 /en perfil limpio, 8-12 s, sin clics
Cookies propias: _ga, _ga_8101KYW9CJ, _ga_YBQXFYRK7H, _gcl_au, _clck, _clsk (todas .limaviewtours.com). _hjSession* AUSENTE (hj cargado, sin endpoints).
Terceros: CLID/MUID/ANONCHK/SM/MR clarity.ms y bing.com; test_cookie doubleclick.
Peticiones: gtm.js GTM-NR3GFXNC 200; gtag/js G-8101KYW9CJ 200; gtag/js AW-16662327751 200; analytics.google.com/g/collect 204 (gcs=G111); stats.g.doubleclick.net/g/collect 204 (G111); clarity.ms/tag/yga2mmooit 200; scripts.clarity.ms/0.8.72/clarity.js 200; i.clarity.ms/collect 204 x2; static.hotjar.com 200; facebook.com/tr: 0.
page_view a G-8101KYW9CJ: 1. A G-YBQXFYRK7H: 1 (tambien page_view). dataLayer consent default = 4 granted.

## Bloque 2 errores (CSP console+securitypolicyviolation, pageerror)
/en, /es, /pt, ficha city-tour-cusco: 0 CSP, 0 pageerror, 0 console error.

## Bloque 3 UE emulada (de-DE, Berlin, geo) /en
NO cambia la IP. Mismo resultado: gcs=G111, mismas cookies, sin consent/ de Clarity.

## Bloque 4 movil 375 (ficha city-tour-cusco -> carrito -> Datos)
Rectangulos (viewport 812): WhatsApp 56x56 en x299 y660-716; .cart-sticky x12 y728-800; boton .cart-cta-btn x168 y739-789. Separacion vertical 12 px: NO tapa el CTA (capturas m_cart.png, m_datos.png). En ficha (sin sticky) WhatsApp y732.
dataLayer: view_item, add_to_cart, begin_checkout (+view_cart). gtag collect 204 para G-8101KYW9CJ: page_view, view_item, add_to_cart, form_start, form_submit, view_cart, begin_checkout; todos gcs=G111. Mismos eventos tambien a G-YBQXFYRK7H.
HALLAZGO: CSP bloquea 'https://www.google.com.pe/pagead/1p-conversion/16662327751/...en=conversion' (redireccion desde googleads.g.doubleclick.net/viewthroughconversion y www.google.com/pagead/1p-conversion). connect-src no incluye google.com.pe ni otros ccTLD. Ocurre en add_to_cart y carrito/begin_checkout.
Carrito de prueba vaciado (Vaciar carrito; 0 tours, insignia 0).

## Bloque 5 footer 375 y 1366 (es y en)
Sin "Preferencias de cookies": 0 coincidencias de cookie/preferencias en texto del footer y de la pagina; 0 elementos con id/class cookie.

## Post-deploy CSP e59fc63 (26/10/2026, solo lectura, prod)
Recorrido identico al que dio las violaciones: 375 px, ficha city-tour-cusco -> fecha -> reservar -> /es/carrito -> Continuar a Datos (sin rellenar). 2 ejecuciones, Chrome nuevo con perfil temporal cada una (script scratchpad/humo-prod/post.js).

Veredicto: OK.
- Violaciones CSP (consola + evento securitypolicyviolation, registrado tambien en sessionStorage para sobrevivir a la navegacion): 0 en ambas ejecuciones. 0 pageerror, 0 console error.
- Conversion: cada ejecucion lanza 4 cadenas en add_to_cart/carrito. Todas terminan: googleads.g.doubleclick.net/pagead/viewthroughconversion (302) -> www.google.com/pagead/1p-conversion (302) -> www.google.com.pe/pagead/1p-conversion (200). Ademas googleadservices.com/pagead/conversion (200, 4 por ejecucion), www.google.com.pe/pagead/1p-user-list (200), rmkt/collect (200). 0 requestfailed.
- GA4 G-8101KYW9CJ: collect 204, gcs=G111, eventos page_view, view_item, add_to_cart, form_start, form_submit, page_view, view_cart, begin_checkout. dataLayer: view_item, add_to_cart, view_cart, begin_checkout.
- /en, /es, /pt (375 px, 8 s): 0 CSP, 0 pageerror, 0 console error.
- Carrito de prueba vaciado ("0 tour") en ambas ejecuciones.

| Peticion | Antes (humo previo) | Ahora |
|---|---|---|
| www.google.com.pe/pagead/1p-conversion/16662327751 (en=conversion) | BLOQUEADA por connect-src (8 mensajes consola, 4 securitypolicyviolation) | 200, 4 por ejecucion, 0 violaciones |
| googleads.g.doubleclick.net/viewthroughconversion -> www.google.com/pagead/1p-conversion | 302 en cadena, rematada bloqueada | 302 -> 302 -> 200 |
| googleadservices.com/pagead/conversion | no registrado en informe previo | 200 |

No verificado: que Google Ads registre la conversion en su panel (requiere acceso de Anyerson; tarda horas); host de pais distinto de .pe (la IP de prueba solo redirige a google.com.pe, los otros 49 ccTLD no se ejercitaron); desktop/tablet en este recorrido.
