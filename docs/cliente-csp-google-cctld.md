# Validación del cliente: APROBADO CON OBSERVACIONES

Lote: ajuste de la regla de seguridad para dominios de Google por país (rama fix/csp-google-ads-cctld-2026-10-26, commits d063392 + e59fc63). Validado en local (http://lima-tour.test) con un Chrome propio manejado por un operador; yo miré las capturas.

BASE de capturas: C:\Users\USUARI~1\AppData\Local\Temp\claude\C--Users-USUARIO-WEBTILIA\84399fae-f45e-4fc5-a4e7-d45862f08cde\scratchpad\cliente-csp\

## Lo que funcionó bien
- ✓ **Celular 375 px en inglés, todo con clics reales:** home, tarjeta del tour, ficha "Lima City Tour with Catacombs", calendario, fecha 31/10, BOOK, carrito ("Tour added to cart."), Continuar, Datos, Ir al pago, Pago ("Secure payment", US$33, sin pagar). Cada paso avanzó.
  - Capturas: BASE + A_en_0home, A_en_1c_ficha_widget, A_en_3b_reservar_2s, A_en_4b_carrito_fondo, A_en_6c_datos_fondo, A_en_7a_pago (.png).
- ✓ **Igual que en mi validación anterior:** el WhatsApp queda sobre la barra de total/botón sin taparla; sin avisos de cookies; el pie con sus enlaces, contacto, redes y medios de pago está completo.
- ✓ **Computadora 1366, home en inglés:** arriba, mitad y fondo se ven completos, con menú, cifras, boletín y pie. Capturas: BASE + B_1_top, B_2_mid, B_3_bottom (.png).
- ✓ **Sin errores visibles ni de seguridad:** ningún mensaje de bloqueo por la regla de seguridad en consola, ni imágenes rotas, ni peticiones fallidas nuevas.

## Observaciones
1. **[menor, ya conocido]** Textos en español en /en: "Nuestros tours / Más Comprados", "Continuar", "Ir al pago", "Ver opciones de pago", "4 Horas · 1 adulto". Ya estaban en la validación anterior.
2. **[menor, ya conocido]** En el celular, la barra de total tapa en parte el copyright al fondo del carrito/datos, y el WhatsApp roza el pie.
3. **[menor, ya conocido]** Enlace "Us" en vez de "About us" en el pie en inglés.

## No verificado
- **El objetivo real del cambio:** que el aviso de conversión de Google Ads salga al añadir al carrito. En local no hay identificador de Google Ads, así que no sale ninguna petición a Google y no pude ver el aviso. Solo puedo afirmar que el turista no nota diferencia. Hay que comprobarlo en producción (Tag Assistant o informe de conversiones de Google Ads tras añadir un tour).
- Los campos "name" y "email" ocultos que fallan la validación del navegador en Pago (ya anotado la vez anterior): el clic en "Ver opciones de pago" no se probó.
- PayPal (identificador de prueba en local) y la imagen faltante de un tour: conocidos, no reportados.

## Cumplimiento del brief ("que todo siga midiendo", turista sin cambios)
- [✓] El turista no nota nada distinto en el recorrido de compra ni en la home.
- [~] Que la conversión de Google Ads se registre: no comprobable en local, queda para producción.

## Decisión
- [ ] OK, autorizo a producción
- [x] OK con observaciones: autorizo a producción. Seguimiento: confirmar en producción que la conversión de añadir al carrito llega a Google Ads; observaciones menores de textos y barra fija en un lote próximo.
- [ ] No OK
