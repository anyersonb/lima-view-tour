# QA 2 · baaa98e (post c64a596) · CERRADO
## Bloque A (D1, flujo real carrito→Datos→Pago)
- view:clear hecho. Chrome propio (playwright-core launch, perfil temporal), clics reales.
- 375/414/768/1023 es + 375/768 en/pt: WhatsApp vs .cart-sticky-inner y vs CTA: solape 0 px² en carrito, Datos (vacío/lleno) y Pago. --sticky-bar-h=72px; hueco WA-borde 96px. CTA sin recorte (scrollWidth<=clientWidth). Sin overflow horizontal.
- Errores: solo PayPal SDK 400 (client-id sandbox de pruebas CRO), preexistente, no del diff. CSP: 0 violaciones.
- Tests: ConsentMode+AnalyticsHealth+SecurityHeadersCsp+ConversionEvents 24/24 (189 aserciones); PiiMasking 1/1.

## Bloque B (bordes, 1023/375, Chrome propio)
- Carrito vacío (1023 y 375): sbh=0px, WA a 24 px del borde (waGap=24), barra display:none.
- Resize en vivo 1023→1100: sbh 72→0, WA 96→24 px. 1100→1023: vuelve a 72/96. 700: 72/96. OK.
- Check al revés (375): forzando --sticky-bar-h=0 el solape WA/barra es 56x56 px (el check SÍ detecta). Evento pageshow(persisted:true) simulado: recupera a 72/96. Back real desde /contacto: bfcache NO se activó en este Chrome (pageshow sin persisted), la recarga normal sincroniza OK; la rama bfcache real queda probada solo por simulación.
- Quitar ítem (375, clic real en Eliminar): barra desaparece, sbh=0, WA baja a 24 px, pantalla "carrito vacío" correcta, sin pageerror.
- Nota: a 1023 WA (x 947-1003) y CTA (x 471-796) no se cruzan en horizontal; el solape real del D1 ocurre bajo ~700px. Con el fix igual sube 96 px.

## Bloque C (regresión, popup, CSP, SEO)
- Ficha 375 con scroll (rueda): .lvt-resbar is-on, sbh=68px, WA bottom 708 <= barra top 732 (24 px libres), screenshot revisado. Ficha 1366: barra display:none, sbh vacío, WA a 24 px.
- Home/listado/contacto/blog a 375 y 1366: waGap=24, sin overflow-x, 0 violaciones CSP. Panel de filtros 375 (tours/index): top 0, bottom 0, alto 800=viewport, screenshot revisado.
- Consola: sin pageerror. Único 404 imagen storage/tours/Green-gardens…jpeg (dato local en la ficha, no del diff) y PayPal SDK 400 (client-id de prueba).
- CSP: script-src 'self' 'unsafe-inline' (header de /es y /es/carrito); el script nuevo es inline sin nonce, igual que los demás; ejecutó (sbh=72px) con 0 violaciones.
- Popup: popup.blade.php:35 lleva data-hj-suppress + data-clarity-mask="true" (confirmado renderizando <x-popup/> con Blade::render). OJO: el componente no está incluido en ninguna vista (código muerto hoy), así que no hay prueba en DOM vivo.
- Gate SEO (8 URLs es/en/pt): sin X-Robots-Tag; robots index,follow salvo /carrito noindex (diseño); canonical propio; 4 hreflang; 1 H1; JSON-LD parsea (graph/Product/TouristTrip). robots.txt local "Disallow: /" (entorno local; el de prod no es de este diff). El diff solo toca estilos, JS inline en body y un atributo: sin cambios en head.
- grep cookie-banner-h en resources/app/public: sin restos.

## Cierre
Veredicto: APROBADO (baaa98e)

Defectos por severidad: Crítico 0 · Alto 0 · Medio 0 · Bajo 0. (Fuera de alcance: textos del carrito en español en /en y /pt, no viene de este diff.)

No verificado:
- bfcache real (solo simulado el evento pageshow persisted=true; la vuelta atrás normal sí).
- en/pt en 414 y 1023 (solo 375 y 768 en esos idiomas).
- Artículo individual de blog.
- Dispositivos reales con safe-area (iOS).
- robots.txt de producción.
- Popup en DOM vivo (el componente no está incluido en ninguna vista; verificado por render).
