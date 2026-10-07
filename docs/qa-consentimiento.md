# QA consentimiento 524de6d (base f34c3e8) - 2026-10-06
Estado: DEVUELTO (parcial; ver punto 6 y no verificado). Local restaurado: cookie_banner_enabled=1, seo_gtm_id="", seo_google_analytics_id="".

6. cookie_banner_enabled=0
 - f34c3e8 app.blade.php L41: $cookieBannerEnabled = (bool) Setting::get('cookie_banner_enabled', true); L405: <x-cookie-banner/> solo si true; L249-254 y L337-343: con false carga GTM sin restricciones + noscript.
 - 524de6d L331/L305-308: con false `window.lvtLoadGtm()` inmediato. MISMO comportamiento que antes (no regresion).
 - Medido local con IDs falsos y Setting=0: gtm.js pedido en /es,/en,/pt,ficha (1 peticion c/u), cookies _ga/_ga_FAKE creadas, banner ausente, sin consent default. Con Setting=1: 0 peticiones gtm.js.
 - Produccion (banner no sale, 0 "cookie" en HTML): el unico camino en f34c3e8 es Setting cookie_banner_enabled falsy en la BD de prod (componente no incluido). Con el valor por defecto true saldria banner. Conclusion: el fix NO sirve en prod mientras ese Setting este en 0; hay que ponerlo en 1 (decision del dueno / backend-laravel) o hacer que GTM nunca cargue sin consentimiento.
 - Observacion: local con Setting=0 el HTML tiene 3 coincidencias "cookie" (no investigadas); prod tiene 0.
7. ConsentModeTest 8/8 (54 aserciones). Suite completa: 403 pasados, 13 saltados, 0 fallos (igual a linea base).
1. /es,/en,/pt y ficha, visitante nuevo: 0 peticiones gtm.js/clarity/hotjar/facebook; cookies solo XSRF y sesion (sin _ga/_clck); dataLayer[0]=consent default denied, antes de js/config. gtag/js de GA4 si carga (diseno, bajo denied). CSP violations 0.
2. Aceptar: gtm.js pedido sin navegacion extra, cookie lvt_consent=granted 365d, dataLayer default denied -> update granted. Recarga: gtm.js y sin banner. Footer "Preferencias de cookies" reabre banner (es/en/pt textos ok). Revocar tras aceptar: recarga 1 vez, 0 gtm.js, _ga/_clck purgadas, lvt_consent=denied. Rechazar fresco y recargar: 0 gtm.js, sin banner.
3. Clics reales (contexto nuevo por clic, 1440 y 375; es/en/pt, contacto, ficha): phone_click footer/contacto, email_click contacto, maps_click contenido/contacto, whatsapp_click flotante. phone_click header (dentro del menu abierto) en 1440 y 375. Nota de medicion: tras un clic en tel: los clics siguientes en la misma pagina no registran; es artefacto de headless, no del codigo.
4. NO verificado: view_item/add_to_cart/begin_checkout con value en vivo, carrito/checkout, banner vs WhatsApp/CTAs por getBoundingClientRect, /admin/login. (view_item/begin_checkout no tocados por el diff; solo cubiertos por la suite.)
5. NO verificado: gate SEO por diff (head, canonical, hreflang, JSON-LD, robots, title). Diff solo toca layouts/app, cookie-banner, footer, lang, test.
