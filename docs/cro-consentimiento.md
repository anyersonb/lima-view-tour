# CRO consentimiento c4915c7 (base f34c3e8) - 2026-10-06

Estado: EN CURSO (se escribe punto a punto). Entorno: http://lima-tour.test, IDs de analitica vacios (seo_gtm_id="", seo_google_analytics_id=""), cookie_banner_enabled=1. Instrumento: playwright-core + Chrome headless propio (MCP Playwright no usado). Clics reales con `page.mouse.click` en el centro del elemento tras `scrollTo({behavior:'instant'})` y `elementFromPoint`. Sin sesion, sin pago, sin reserva real (ver nota de datos de prueba en punto 1).

## 1. Reserva con el banner SIN responder (375 y 1440)

Flujo: /es -> /es/tours/detalle/city-tour-cusco -> clic real en el input de fecha -> clic real en un dia del calendario (flatpickr, 2026-10-10) -> clic real RESERVAR (`button.m-cta` a 375, `button[form=form-reservar]` a 1440) -> /es/carrito -> "Continuar a Datos y Pago" (paso Datos) -> campos -> "Continuar a pago" (paso Pago). Se llego al paso Pago en las dos anchuras.

| Medida | 375x812 | 1440x900 |
|---|---|---|
| Banner visible al cargar (sin responder) | top 660, alto 152px = 18,7% del viewport | top 824, alto 76px = 8,4% |
| `--cookie-banner-h` | 152px | 76px |
| Clic real en RESERVAR (elementFromPoint en el centro) | BUTTON.m-cta, no banner; navega a /es/carrito | BUTTON de reserva, no banner; navega a /es/carrito |
| Clic real "Continuar a Datos y Pago" | boton propio, no banner; paso -> datos | idem |
| Clic en campo nombre | foco en `customer_name` | idem |
| "Continuar a pago" con campos vacios | no avanza; alert nativo "Completa tu nombre, correo, telefono y fecha de viaje antes de continuar." (validacion preexistente, checkout.blade.php:2065) | idem |
| "Continuar a pago" con datos ficticios | paso -> pago | paso -> pago |
| Elementos interactivos cuyo centro cae bajo el banner con la pagina en scroll MAXIMO (ficha, home, contacto, carrito, datos, pago) | 0 | 0 |
| Calendario abierto: controles capturados por el banner | solo los +/- de pasajeros en la zona 705-729 (pre-scroll, desaparecen al desplazar) | 0 |

Que queda tapado (y por que no bloquea):
- Mientras el banner esta sin responder, todo lo que cae en la franja inferior queda debajo (a 375: y>660; a 1440: y>824). Es el comportamiento de una barra fija; con scroll instantaneo cada control sale de esa franja y responde (verificado con clic real en CTA reservar, Continuar x2, campo nombre).
- Al final del scroll a 375 (home/ficha/contacto) las ultimas ~136px del pie (Metodos de pago, copyright) quedan debajo del banner: `footer.bottom=812`, ultimo texto del pie bottom=796, banner top=660; `body padding-bottom`=0 (no hay espacio reservado). Captura: scratchpad/cro-max-375_es.png. Ningun interactivo del pie queda inaccesible ("Preferencias de cookies" y enlaces quedan sobre el banner). Ver D-1.
- CTA RESERVAR en ficha 375: sin scroll esta en y 820-858, fuera del viewport (812); al desplazar entra primero por la zona del banner (660-812) y queda libre al subir ~150px. Transitorio, no bloqueado.
- Paso Pago a 375: sticky "Ver opciones de pago" [576-648] y WhatsApp [580-636, x 299-355] se solapan (el circulo tapa el extremo derecho del boton, texto cortado "pag"). Captura scratchpad/cro-pagostep-375-ignore.png. NO es del diff: en f34c3e8 `.cart-sticky bottom:calc(12px + var(--cookie-banner-h))` (checkout.blade.php:483) y el WhatsApp estaba en bottom:24px; ambos suben igual con el banner, la relacion es la misma que sin banner (sticky 728-800 vs WA 732-788 a 375). Preexistente, parecido al "WhatsApp roza RESERVAR" ya conocido.

Consola/red en el flujo (los 4 recorridos):
- 404 `/storage/tours/Green-gardens-and-palm-trees-line-the-historic%E2%80%A6.jpeg` (dato de contenido local, ya listado por QA, ajeno al diff).
- 400 `paypal.com/sdk/js?client-id=sb-test-client-id-CRO-AUDIT` solo en el recorrido sin bloqueo de red: client-id ficticio de la BD LOCAL. No es de produccion ni del diff.
- 0 `pageerror`, 0 violaciones CSP en consola (no hubo mensajes "Content Security Policy").
- Nota de datos: en el paso Datos se escribieron valores ficticios (Prueba CRO / cro@example.invalid / 999999999) solo para alcanzar el paso Pago. El POST de contacto abandonado (`/carrito/guardar-contacto`), `/checkout/procesar` y PayPal se abortaron con `page.route`; no se envio nada.
- NO verificado: boton de pago final de PayPal (SDK abortado/ID falso) y el submit de /checkout/procesar.

Conclusion punto 1: sin bloqueo ni captura de clics por el banner; la UX en la franja inferior mejora al responder. Solo la observacion D-1.

## 2. Consentimiento RECHAZADO (375 y 1440)

Clic real en "Rechazar" (home). Cookie `lvt_consent=denied`, banner oculto en home, ficha y carrito, y `--cookie-banner-h` pasa a 0.
- Flujo ficha -> fecha -> RESERVAR -> carrito -> Datos -> Pago: se completa a 375 y 1440. Clic real, `elementFromPoint` = el propio boton, nunca el banner. Mismos resultados que el punto 1. Banner ausente en todas las rutas.
- WhatsApp flotante, clic real (12 casos: 375/1440 x ignorar/rechazar x /es, ficha, /es/carrito; wa.me interceptado con stub): en los 12 el `elementFromPoint` es el enlace (no el banner), se abre popup `https://wa.me/51935542384` (target=_blank) y se empuja `whatsapp_click {ubicacion:flotante}` a dataLayer. Posicion WA: sin responder 375 [580-636] / 1440 [744-800]; rechazado 375 [732-788] / 1440 [820-876] (vuelve a bottom:24px).

## 3. Banner en movil

- Alto a 375x812: 152px = 18,7% del viewport (top 660). A 1440x900: 76px = 8,4%.
- CTA hero home "EXPLORAR EXPERIENCIAS": 375 [301-351] frente al banner desde 660: solape 0; `elementFromPoint` = el CTA. 1440 [353-397] vs banner 824: solape 0.
- Atributos: `<div role="dialog" aria-label="Aviso de cookies" aria-live="polite">`, sin `aria-modal` (correcto, no es modal).
- Teclado: orden Tab = ultimo del DOM, se llega tras 101 Tab (375) / 87 (1440) desde el inicio. Foco visible en enlace "Politica de privacidad" (anillo box-shadow blanco+naranja, 2px) y en Rechazar/Aceptar (outline solid 2px). Enter en Rechazar guarda `denied` y cierra; el foco pasa a BODY. Esc no cierra el banner (no es modal).
- Medicion: dos capturas con foco tras 100 Tab salieron corruptas (scroll suave en curso); descartadas como artefacto, valen los `getBoundingClientRect` (banner estable 660-812).

## 4. Contrato CMS -> front (cookie_banner_enabled)

IDs ficticios temporales: seo_gtm_id=GTM-CROFAKE1, seo_facebook_pixel=111222333444555 (red de terceros interceptada con stub).
- Setting=1 (375 y 1440; /es /en /pt ficha /es/carrito): banner visible (152px / 76px), `--cookie-banner-h` 152/76px, boton footer `[data-cookie-preferences]`=1, consent default denied en dataLayer, 0 peticiones gtm/fb/clarity/hotjar, sin cookies de tracking. Aceptar: gtm.js + fbevents pedidos, `lvt_consent=granted`, banner se cierra. Igual que QA.
- Setting=0 (375 y 1440; /es /en /pt ficha /es/carrito): banner NO visible (h 0, `--cookie-banner-h` 0px), boton footer presente (1), consent default denied, 0 peticiones gtm/fb/clarity/hotjar, sin cookies de tracking (falla cerrado). Abrir desde el footer + Aceptar: gtm.js y fbevents pedidos, `lvt_consent=granted`. Coincide con qa-consentimiento-2.md punto 1.
- Restauracion (leida de nuevo en BD tras guardar): cookie_banner_enabled="1", seo_gtm_id="", seo_google_analytics_id="", seo_facebook_pixel="" (valores originales).
- Nota: el Setting=0 sigue sin mostrar banner en prod, pero ya no carga GTM sin consentimiento; sigue pendiente activarlo a 1 en prod (decision del deploy).

## 5. Tablas del contrato (acotadas)

Tabla A Lighthouse: NO VERIFICADO (no se ejecuto Lighthouse en esta pasada; el diff no toca CSS/imagenes del LCP, pero no hay medicion).
Tabla B On-page: NO MEDIDO por este informe. Evidencia ajena (qa-consentimiento-2.md punto 5): title, 26 metas, canonical, hreflang, JSON-LD y orden de h1-h4 identicos entre f34c3e8 y c4915c7 en /es /en /pt ficha /es/carrito. Ficha con 2 h1 (conocido, preexistente).
Tabla C gates: 1 y 2 (robots/sitemap) NO VERIFICADO; 3, 4, 6, 7 solo por la evidencia de QA (identico a la base, no medicion propia); 5 (4xx/redirecciones) NO VERIFICADO. Sin evidencia propia, ninguno se da por cumplido aqui.

## Defectos

Bloqueantes: ninguno. Altos: ninguno.
- D-1 [bajo, nuevo del diff] Sin espacio reservado bajo el pie: con el banner sin responder a 375 las ultimas ~136px del pie (Metodos de pago, copyright) quedan tapadas al final del scroll (footer.bottom 812, ultimo texto 796, banner top 660, body padding-bottom 0). Asignar a maquetador-frontend (padding-bottom: var(--cookie-banner-h) en body/footer). Se arregla solo al responder.
- D-2 [bajo, nuevo del diff] Banner = ultimo nodo del DOM: 101 Tab (375) / 87 (1440) para llegar; tras Enter el foco cae en BODY. role=dialog sin gestion de foco. maquetador-frontend (opcional).
- D-3 [bajo, preexistente] Paso Pago a 375: WhatsApp flotante cubre el extremo del boton "Ver opciones de pago" del sticky; misma relacion en f34c3e8. No lo causa el diff.
- Conocidos y no atribuibles al diff (no medidos de nuevo): "Us" en footer EN, scroll horizontal en ficha y /pt, WhatsApp rozando RESERVAR, 2 h1 en ficha, item_name en mayusculas en begin_checkout.
- Ruido local descartado: 404 imagen Green-gardens (dato local), 400 PayPal con client-id ficticio local.

## NO VERIFICADO
Lighthouse; robots/sitemap/redirecciones; boton final de pago PayPal y submit de checkout (no se pagan ni envian); Firefox/Safari; movil real (`isMobile` con la pagina desbordada a 389px hizo saltar el scroll de Playwright, se uso 375 sin emulacion movil y un clic real validado con elementFromPoint); pago con 2+ adultos; produccion.

## VEREDICTO: APROBADO CON OBSERVACIONES NO BLOQUEANTES
Ningun control de reserva ni WhatsApp queda bloqueado o capturado por el banner (ignorado, rechazado, 375 y 1440). Contrato Setting 1/0 coincide con QA. Navegador: cerrado.
