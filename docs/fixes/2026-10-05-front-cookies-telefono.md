# Fix front: banner de cookies vs CTAs fijos + teléfono en checkout (2026-10-05)

## Archivos tocados
- resources/views/components/cookie-banner.blade.php: el banner publica su alto real en `--cookie-banner-h` (:root) vía ResizeObserver + $watch('show'); vuelve a 0px al aceptar/rechazar. Lógica de consentimiento intacta.
- resources/views/checkout.blade.php: `.cart-sticky` bottom y `.cart-sticky-spacer` suman `var(--cookie-banner-h,0px)`; teléfono `maxlength="15"` + `inputmode="tel"`; `showServerFieldError()` y `ppCreateOrder` muestran el primer `data.errors.<campo>[0]` bajo el campo (name/email/phone/accept_terms; enfoca el campo, vuelve al paso datos, se limpia al escribir); `ppOnError` no pisa ese mensaje con el genérico.
- resources/views/tours/index.blade.php: panel del drawer de filtros con `bottom: var(--cookie-banner-h,0px); height:auto` (el pie con "Ver N tours" queda sobre el banner).
- Build: view:cache + npm run build (public/build regenerado).

## Verificación (Chrome headless vía playwright-core, SQLite desechable, contexto limpio = primera visita)
- elementFromPoint en el centro de #cart-cta-main (scroll instantáneo, paso pago): es el propio CTA/hijo en es/en x 375/768 (banner 146px / 74px). Clic real con mouse avanza de paso.
- CONTROL: forzando `--cookie-banner-h:0px` el mismo punto lo intercepta otro elemento (DIV/P) -> el check sí falla cuando debe.
- Drawer /tours: "Ver 6 tours" = elementFromPoint OK en es/en x 375/768; clic real cierra el drawer. Banner visible debajo (top 521 h146 a 375).
- Banner 1440: top 832 h68 igual que antes; sticky y drawer no se muestran a >=1024. Aceptar -> granted, Rechazar -> denied, ambos ocultan el banner (var 0px) y no reaparece tras recargar.
- Teléfono: 20 dígitos escritos -> value.length 15 (es/en x 375/768).
- 422 `errors.customer_phone` (simulado con page.route; ppCreateOrder no es global, se expuso solo en la prueba): rechaza con fieldError y el texto aparece en `[data-server-field-error]` del campo; mismo en es/en.

## Pending / no verificado
- No hay captura 1440 comparada píxel a píxel con el estado previo; solo medidas (rect del banner, display del sticky).
- El idioma del mensaje 422 depende del servidor (Request/CheckoutController, fuera de alcance: no se tocó app/).
- Banner a 375 ocupa 146px (22% del alto); no se compactó, se evita el solape por desplazamiento.
