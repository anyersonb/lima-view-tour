# CRO pre-entrega: quitar banner de cookies (rama fix/quitar-banner-cookies-2026-10-26, c64a596, base 2eff857)

Estado: VOLCADO PARCIAL 1 (bloque conversion). Navegador: Chrome propio + playwright-core (`--host-resolver-rules=MAP lima-tour.test 127.0.0.1`), perfil nuevo por contexto. El Chrome de Playwright MCP (PID 2288) no se toco. Scripts en el scratchpad `.../scratchpad/cro/`.

## Bloque 1. Flujo de conversion (375 px, es): home -> ficha -> fecha -> reservar -> carrito -> Datos -> Pago (sin pagar)
- Sin overflow horizontal en home, ficha, carrito, datos, pago (scrollWidth = clientWidth = 375). `--cookie-banner-h` vacio, 0 nodos de banner/preferencias, 0 violaciones CSP, 0 pageerror.
- dataLayer (gtag y dl): view_item, add_to_cart, begin_checkout, add_payment_info (ficha -> carrito -> Pago).
- Consola: solo 404 de imagen local `/storage/tours/Green-gardens...jpeg` (dato local, ajeno) y 400 del SDK PayPal con client-id de prueba (entorno, ajeno).

### DEFECTO D1 (alto, preexistente, no causado por el diff): el boton flotante de WhatsApp tapa el CTA fijo del carrito a 375 px
- WhatsApp: rect [299,720,56,56]. CTA `.cart-cta-btn`: [168,727,180,50]. Solape 49x49 px = 27 % del CTA, en los tres pasos (Carrito, Datos, Pago).
- En el paso Pago el CTA dice "Ver opciones de pag..." y la "o" final y la flecha quedan bajo el circulo verde (captura `scratchpad/cro/375_es_6pago.png`).
- Causa (lectura): app.blade.php:290 desplaza WhatsApp con `--sticky-bar-h` (solo lo fija la ficha); la barra `.cart-sticky` de checkout.blade.php:491-494 no lo fija. El diff no toca estas reglas; antes con el banner ya cerrado el resultado era igual. Era algo preexistente que el banner enmascaraba solo a visitantes nuevos.
- Asignar a: maquetador-frontend.

---
## VOLCADO 2 (tras aviso de limite de turnos). Estado por punto

| # | Punto | Estado |
|---|---|---|
| 1 | Conversion 375/1366 hasta paso de pago | 375 es/en/pt VERIFICADO hasta paso Pago (add_payment_info emitido). 1366 pt VERIFICADO hasta Pago. 1366 es/en: carrito verificado; Datos->Pago PENDIENTE (mi selector fallo, instrumento; ya corregido a `[data-step-next]`). Barra de reserva fuera de la ficha: la unica barra fija fuera de la ficha es `.cart-sticky` del carrito (medida, ver D1); `.lvt-resbar` existe solo en tours/show |
| 2 | Footer | PENDIENTE (script g2.js listo) |
| 3 | Privacidad es/en/pt | PENDIENTE (script g3.js listo; QA ya cubrio texto, yo no lo he repetido) |
| 4 | Medicion dataLayer | VERIFICADO: view_item, add_to_cart, begin_checkout, add_payment_info (es/en/pt, 375 y 1366 pt) y whatsapp_click (clic real, 375 por tap y 1366; navegacion abortada). `consent default` = 4 x granted es el primer elemento del dataLayer. IDs GTM/GA4/Pixel vacios en local: no se emite gtag/js, gtm.js ni fbevents; no se rellenaron Settings |
| 5 | Accesibilidad footer / foco | PENDIENTE (g2.js) |

Comun a 375 y 1366: sin overflow horizontal, `--cookie-banner-h` vacio, 0 nodos de banner, 0 violaciones CSP, 0 pageerror. Consola: 404 imagen local `/storage/tours/Green-gardens...jpeg` y 400 SDK PayPal con client-id de prueba (ambos ajenos, entorno/datos).

## Defectos
- D1 (alto, preexistente, ajeno al diff, maquetador-frontend): WhatsApp flotante tapa 27 % del CTA fijo del carrito a 375 px en los 3 pasos y 3 idiomas; en Pago el texto del CTA queda cortado.
- Bajo, ajeno: 404 imagen local. Entorno: PayPal 400.
- Cambios ajenos (ImagePath, RouteConflict, RouteRegistry): sin efecto observado.

## Tabla A. Lighthouse por URL
| URL | Perf | A11y | Best Practices | SEO | LCP | CLS | INP |
|---|---|---|---|---|---|---|---|
| todas | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO |
(Sin Lighthouse en el entorno; no se inventan cifras. El diff no cambia assets ni CSS.)

## Tabla B. On-page por URL
Pendiente de volcado de mi lado (el gate SEO lo cubrio QA en su informe: 1 H1, title/description, canonical propio, JSON-LD valido). Yo no lo repeti.

## Tabla C. Gates
| # | Gate | Estado | Evidencia |
|---|---|---|---|
| 1 | robots.txt | ❌ | local da `Disallow: /` por entorno (segun QA); produccion no verificada |
| 2 | sitemap.xml | ❌ | no verificado por mi |
| 3 | canonical propio | ❌ | solo evidencia de QA, no mia |
| 4 | sin noindex | ❌ | no medido por mi |
| 5 | 0 4xx / cadenas | ❌ | no medido por mi |
| 6 | title y H1 | ❌ | no medido por mi |
| 7 | datos estructurados | ❌ | no medido por mi |

---
## VOLCADO 3 (cierre). Todos los puntos

- Punto 1: VERIFICADO. 375 y 1366 x es/en/pt completos hasta paso Pago, sin overflow, 0 CSP, 0 pageerror. `lvt-resbar` solo existe en tours/show.blade.php (grep), asi que la barra de reserva fuera de la ficha no existe; la barra fija del carrito (`.cart-sticky`, solo <1024) es la que WhatsApp tapa (D1). A 1366 no hay solape.
- Punto 2: VERIFICADO. Footer 375 y 1366 x 3 idiomas: 15 enlaces/botones, 0 con href vacio/#/javascript, el unico boton es ENVIAR (newsletter), "cookie" no aparece en el texto del footer; lista Enlaces termina en Politica de privacidad, sin hueco (captura foot_375_es.png; la barra fija aparece a media imagen por artefacto de captura de elemento).
- Punto 3: VERIFICADO. Clic real desde footer a /es/privacidad, /en/privacy, /pt/privacidade a 375 y 1366: 1 H1, seccion "Cookies" con el texto nuevo (GA, GTM, Clarity, Hotjar, Meta Pixel), 0 claves crudas, sin overflow.
- Punto 4: VERIFICADO (ver tabla del volcado 2) tambien en 1366 es/en.
- Punto 5: VERIFICADO con Tab real: orden footer newsletter > logo > enlaces > telefono > redes, 17 focusables, todos con anillo de foco, ninguno oculto del banner atrapa el foco; restos en DOM: `#mobile-menu` y un modal, ambos display:none y fuera del orden de Tab. NO verificado: contraste.

## NO verificado
Lighthouse (tabla A), tablas B y C propias (cubiertas por QA), pago real/purchase, /admin vivo, HTML con IDs reales de GTM/GA4/Pixel (solo en test sqlite), robots/canonicals de produccion, contraste WCAG, Safari/Firefox.

## Veredicto: APROBADO CON OBSERVACIONES. El diff no introduce defectos. D1 (alto) es preexistente y ajeno al diff: no bloquea el despliegue de este fix, pero va a maquetador-frontend.
