# CRO — CSP Google Ads ccTLD (fix/csp-google-ads-cctld-2026-10-26, d063392 + e59fc63)

Estado 1, pasada corta. Local http://lima-tour.test, `view:clear` previo. Chrome propio (playwright-core, perfil temporal), clic real.
Scripts y salidas: `scratchpad/cro/csp.js`, `out_csp.txt` (rama), `out_base.txt` (línea base).

## Método
- Recorrido por clic: home -> clic en tarjeta de tour -> ficha -> fecha (flatpickr) -> Reservar -> carrito -> Datos (relleno) -> paso Pago (sin pagar) -> footer -> Contacto -> pie -> botón WhatsApp flotante.
- 8 corridas: 375 y 1366 px x es y en, rama (NEW) y línea base (BASE).
- BASE = mismas páginas de la rama pero reescribiendo la cabecera CSP del documento para quitar los 50 `https://www.google.<ccTLD>` (equivale a baaa98e: el diff solo añade a connect-src). No se hizo checkout de baaa98e (hay cambios ajenos en app/Support).
- Instrumento: `securitypolicyviolation`, `pageerror`, `response>=400`, `requestfailed`, imágenes rotas visibles, `document.fonts`, desbordamiento horizontal, hooks sobre `dataLayer.push`.

## Resultados
| Check | Rama | Línea base |
|---|---|---|
| Violaciones CSP en home/ficha/carrito/datos/pago/contacto/pie (4 combos) | 0 | 0 |
| pageerror | 0 | 0 |
| Recursos propios bloqueados / requestfailed propios | 0 | 0 |
| Imágenes rotas visibles | 0 | 0 |
| Fuentes cargadas (fonts.gstatic) | igual: 11-15 req, mismo conteo de caras cargadas por página | igual |
| Desbordamiento horizontal | 0 | 0 |
| Hosts externos | cdnjs, fonts.googleapis, fonts.gstatic, paypal (idénticos) | idénticos |
| dataLayer | view_item, add_to_cart, begin_checkout, add_payment_info, whatsapp_click (4 combos) | idéntico |
| Mapa / iframes / reCAPTCHA | no aparecen en ninguna página recorrida (0 iframes) | igual |

Errores de consola en ambas ramas, idénticos y previos: 404 `/storage/tours/Green-gardens-...historic….jpeg` (archivo local ausente; HTTP 404, no CSP) y PayPal SDK 400 por client-id de prueba (conocido). Por eso `window.paypal` queda `undefined` en ambas.

## Prueba directa de la conversión (sonda de `fetch` no-cors desde la página, ya con el recorrido hecho)
| Destino | Rama | Línea base |
|---|---|---|
| https://www.google.com.pe/pagead/1p-conversion/AW-1/ | sale (petición hecha) | BLOQUEADO por connect-src |
| https://www.google.de/pagead/x | sale | BLOQUEADO |
| https://www.google.com.vn/pagead/x (no está en la lista) | BLOQUEADO | BLOQUEADO |
Las violaciones de la sonda son de la propia sonda, no de la página. El control negativo (.vn bloqueado) confirma que la prueba puede fallar. Se confirma el fix y su límite declarado: países fuera de las 50 siguen perdiendo la conversión.

## No verificado
- SDK de Culqi: no se carga en el paso Pago sin elegir tarjeta; `window.Culqi` es `undefined` en rama y en base (sin diferencia). No se probó el SDK de Culqi con la CSP nueva.
- PayPal SDK: 400 local por client-id de prueba (conocido); la CSP de script-src no cambió.
- Conversión real de Google Ads (gtag hacia googleadservices -> redirección a google.com.pe): solo se simuló con `fetch`; no hay etiqueta real en local.
- Mapa y reCAPTCHA: no existen en las páginas recorridas.
- Solo es/en; pt y otras páginas (blog, listados) no se recorrieron en esta pasada.
- Contraste, foco por teclado y SEO: fuera del alcance de esta pasada.

Defectos introducidos por el cambio: ninguno.

Veredicto: APROBADO
