# Auditoría CRO móvil A — Lima View Tours (PRODUCCIÓN)

Fecha: 2026-11-18 · Alcance: home /es /en /pt, listado /es/tours, categorías Lima/Cusco/Ica, 5 fichas, /es/resenas.
Solo lectura: no se enviaron formularios, no se reservó, no se tocó código/BD/FTP. Unas 24 cargas de página en total.

## Cómo se midió (leer antes de comparar números)
- Playwright 1.57 (el 1.65 no estaba en `lima-view-tour/node_modules`; se usó el de `academia_acero_web`) con el Chrome instalado en la PC. Red sin throttling, desde Perú. Los LCP reales en 4G serán peores.
- Perfil "iPhone 13" de Playwright = **390×664 visibles** (no 844). Segundo perfil 360×800. Los "fold" de este informe son esos.
- LCP/CLS con PerformanceObserver, 5 s de espera, una sola pasada por URL (varianza alta: el mismo /es dio 2.37 s y 2.78 s).
- Peso: suma de `content-length`; subestima (respuestas chunked/comprimidas sin cabecera). Úsalo solo para comparar entre páginas.
- Capturas (fold + full-page) en `C:\Users\anyer\AppData\Local\Temp\claude\7904188b2751399a\7d5cdca2-ec06-4824-b932-1f0bdf6d3fd9\scratchpad\cro-lima\A\` (ej.: `es_390_fold.png`, `fd_560.png`, `fd_3300.png`, `home_dia.png`, `resenas_390_fold.png`).

## Tabla priorizada

| # | Prior. | URL | Problema | Evidencia | Mejora | Esfuerzo | Impacto conv. |
|---|---|---|---|---|---|---|---|
| 1 | Crítico | Fichas (5/5 medidas), ej. /es/tours/detalle/full-day-lima-ancestral-colonial-y-moderna | Botón principal "RESERVAR ›" blanco sobre naranja: contraste 2.53:1 (mínimo 4.5 AA; 7 para CTA). Además 38 px de alto y texto de 12 px. | Computed: color rgb(255,255,255) sobre rgb(220,147,67); h=38 px, font 12 px. `fd_560.png` | Fondo naranja oscuro (el de la barra sticky "Ver disponibilidad" ya es más oscuro) o texto azul petróleo; subir a 48 px de alto y 14-16 px. Mismo color en todos los CTA de reserva | S | Alto |
| 2 | Alto | Fichas (5/5) | En el primer pantallazo no hay CTA ni precio visible: el botón "RESERVAR" está en y=854-986 px (fold 664 px); la tarjeta de reserva empieza en y≈583 y el precio queda en el borde. La barra sticky con precio y "Ver disponibilidad" sí existe, pero solo aparece al hacer scroll. | Full-day: y=948; Humantay/Cusco: 854; Machu/Huacachina: 912. `es_tours_detalle_..._390_fold.png` | Mostrar la barra sticky (precio + CTA) desde la carga, o subir el bloque precio/fecha/CTA sobre la galería. Probar con A/B | M | Alto |
| 3 | Alto | Global (todas) | WhatsApp flotante sin mensaje precargado: `https://wa.me/51925886725` sin `?text=`. Llega un chat vacío y no se sabe desde qué tour escriben. | href leído en ficha y home | `?text=` contextual por página ("Hola, quiero info del tour {nombre} - {url}"), en cada idioma | S | Alto |
| 4 | Alto | /es (360 y 390), /es/tours, fichas | El botón de WhatsApp (56 px, fijo abajo a la derecha) tapa contenido: 4.º dato de la franja de cifras ("+11 años") en el home, precio tachado en tarjetas del listado, flecha de "Ver todas las reseñas en Google", cabecera "CANCELACIÓN GRATUITA" de la tarjeta de reserva. | `es_390_fold.png`, `es_tours_390_fold.png`, `home_op.png`, `fd_900.png` | Reducir a 48 px y moverlo fuera de la zona de datos (o ocultarlo en el fold del home); en ficha ya se desplaza cuando aparece la barra sticky: aplicar ese comportamiento siempre | S | Medio |
| 5 | Alto | /es (LCP) | LCP en /es: 2368 ms a 390 (H1) y **2776 ms a 360** (img hero). Pasa de 2.5 s. La imagen hero (`media/home/01m6za70...webp`, 333 KB) no tiene preload ni `fetchpriority`; solo hay preload de CSS y JS. Fuentes desde fonts.googleapis.com (3 familias). EN/PT dieron 1.25 s (caché caliente: no concluyente). | LCP 2368/2776 ms; solo `<link rel=preload>` de CSS/JS en el HTML; fonts: Albert Sans, Hedvig Letters Serif, Instrument Serif | `<link rel=preload as=image fetchpriority=high>` del hero con `imagesrcset`; autoalojar fuentes (criterio Webtilia) y dejar 2 familias | M | Medio |
| 6 | Alto | Fichas (5/5) | El elemento LCP es un `DIV` con imagen de fondo (la galería), no un `<img>`: no puede recibir `fetchpriority` ni `srcset`. Full-day = 2584 ms, Machu = 2564 ms (ambos >2.5 s); Humantay usa el JPG original `humantay_1-1-1-1-1.jpg` (1200 px, 190 KB). | LCP por ficha: full-day 2584, Machu 2564, Humantay 1340/2184 (390/360), Cusco 1568, Huacachina 1620 | Primera foto como `<img fetchpriority=high width height>`; convertir los JPG de Humantay/Cusco/Machu a WebP | M | Medio |
| 7 | Alto | /es, /en, /pt, tarjetas de tours, listados | Botón "Reservar" de cada tarjeta mide 27 px de alto (mínimo 44). Es el CTA más repetido del sitio (8 en el carrusel del home, 6+ por listado). En Cusco algunas tarjetas lo dejan en 114 px de ancho y otras en 190. | Home y listados: h=27, w=162-190; Cusco x=256 w=114 en 4 tarjetas | Subir el botón a 44-48 px de alto; unificar ancho | S | Medio |
| 8 | Medio | /es/resenas | Desbordamiento horizontal: scrollWidth 399 a 360 y 390 px. Las tarjetas de reseña (`ARTICLE.rounded-2xl`) miden 379 px y llegan a x=399; el header se estira a 399 y el layout viewport queda mayor al de pantalla. | sw=399 con innerWidth=360/390; `ARTICLE... r=399 w=379` | Limitar la rejilla de reseñas (`min-w-0`, `w-full`, quitar ancho fijo). QA debe verificarlo en el inventario | S | Medio |
| 9 | Medio | /es/resenas | La página de reseñas no tiene ningún CTA de reserva ni enlace a tours fuera de header/footer; "Ver más" mide 45×16 px. | Enlaces fuera de header/footer: solo "Inicio" y un enlace a Google | Bloque final "Reserva el tour que más gusta" + enlaces a los tours reseñados; ampliar "Ver más" a 44 px | S | Medio |
| 10 | Medio | /es/resenas y home | Cifras de confianza inconsistentes: home dice "4.9" estrellas y "+8,000 visitantes felices"; reseñas dice "5.0/5 · 13 reseñas verificadas" (Google 4, Tripadvisor 2, web 7). Con 13 reseñas, "+8,000" y "Miles de viajeros" quedan sin sustento visible. | `es_390_fold.png` vs `resenas_390_fold.png` | Unificar la fuente de la cifra (una sola variable) y mostrar el recuento real, o aclarar que "+8,000" son viajeros atendidos, no reseñas | S | Medio |
| 11 | Medio | /es (sección "Opiniones de nuestros viajeros") | "Ver todas las reseñas en Google" apunta a una búsqueda de google.com con parámetros de sesión (`sca_esv`, `ved`, `biw=1920`), no al perfil de Maps. Puede caducar o mostrar un resultado distinto. Texto con falta de espacio: "nosotros.Estas". | href del enlace en la sección; `home_op.png` | Enlace corto a la ficha de Google Maps; corregir texto en ES/EN/PT | S | Bajo |
| 12 | Medio | /es | Sección "Tours de un día desde Lima" con un solo tour único (3 enlaces al mismo slug), título en plural y ~330 px de vacío bajo la tarjeta. | `h2` + 3 `a[href*=/tours/detalle]` = 1 slug; `home_dia.png` | Añadir 2-3 tours de un día (Pachacamac 4 h, catacumbas, centro histórico) u ocultar la sección hasta tener 3 o más | S | Medio |
| 13 | Medio | Fichas (5/5) | H2 duplicados en el DOM (6 títulos x2 = 12 H2: "¿Qué vivirás…?", "Itinerario…", "Información importante", "Opiniones…", "Otros viajeros…", "Reserva con confianza"), versión desktop+mobile. Ensucia el esquema de encabezados para lectores de pantalla y buscadores. | 12 H2 en Full-day, Humantay, Machu, Cusco; 14 en Huacachina | Renderizar una sola versión (CSS responsivo) o `hidden` real en la copia no visible | M | Medio (SEO/a11y) |
| 14 | Medio | Tarjetas de tour (home, listados) | Duración repetida dos veces ("11 horas" en naranja + "11 horas" en la lista) y títulos cortados con "…" ("Full Day Lima: Centro Histórico, Pachacama…"). Cada tarjeta ocupa ~230 px: en 664 px solo se ven ~2 tours. | `es_tours_390_fold.png` | Quitar la repetición; dejar 2 líneas de título completo; recuperar altura para ver más tours por pantalla | S | Medio |
| 15 | Medio | Fichas Humantay y Cusco | Alt no descriptivos: Humantay usa horas ("5:00 am", "8:30 am", "9:00 am") como alt de las fotos del itinerario; Cusco repite "CITY TOUR CUSCO" en 4 fotos. | Volcado de `document.images` | Alt descriptivo por foto ("Laguna Humantay a las 5 am desde la caminata") | S | Bajo (SEO/a11y) |
| 16 | Medio | Todas | Terceros pesados: gtag 198 KB + gtm 165 KB + AW 154 KB + Hotjar + Clarity + Bing + doubleclick (18 hosts externos en el home). `fonts.googleapis.com` en lugar de fuentes propias. | `ext` por URL en el JSON de resultados | Cargar Hotjar/Clarity tras interacción o consentimiento; autoalojar fuentes | M | Medio (perf) |
| 17 | Bajo | Cabecera (todas) | Carrito (40×40) y hamburguesa (40×40) por debajo de 44 px; teléfono del footer 110×20, redes 20×20. | `small` por URL | Ampliar el área de toque a 44×44 con padding | S | Bajo |
| 18 | Bajo | /es, /en, /pt hero | H1 ocupa 5 líneas en ES y 7 en EN. El CTA "EXPLORAR EXPERIENCIAS" va en estilo fantasma (fondo `rgba(0,0,0,.15)`) sobre foto; es el único CTA, pero con menos peso visual que las tarjetas "Reservar" oscuras. En EN el CTA baja a y=541-591. | `en_390_fold.png` | Reducir tamaño del H1 en móvil (clamp) y dar fondo sólido al botón del hero | S | Bajo |
| 19 | Bajo | /es | Meta description de 160 caracteres (límite 155). En /es/tours y categorías cumple (98-120). | Longitudes medidas | Recortar meta del home a 155 o menos | S | Bajo |
| 20 | A verificar | Fichas | Textos de urgencia: "30 viajeros reservaron este tour esta semana", "Más reservado", "Últimos cupos". No pude confirmar si son datos reales o fijos. | `fd_900.png` | Confirmar con el cliente que salen de datos reales; si son fijos, retirarlos (riesgo de confianza) | S | Medio |

## Quick wins (menos de 1 día, alto retorno)
1. #1 contraste y tamaño del botón RESERVAR de ficha.
2. #3 `?text=` contextual en el WhatsApp (ES/EN/PT).
3. #7 botones "Reservar" de tarjeta a 44-48 px.
4. #12 ocultar o completar "Tours de un día desde Lima".
5. #10/#11 unificar cifras (4.9 vs 5.0), arreglar enlace "ver reseñas en Google" y texto "nosotros.Estas".
6. #8 corregir el desbordamiento de /resenas.

## Pendientes QA previos
- Reseñas de Google: **no se ve el fallo en el front**. /es/resenas muestra Google 5.0 con 4 reseñas y el home muestra reseñas "Google" (probablemente guardadas). Sin mensajes de error visibles.
- Flechas de carrusel con 1 slide: **no reproducido** en el home (el carrusel de un día no muestra flechas; solo el de más vendidos, que tiene 8 slides). Las flechas de la galería de ficha (7 fotos) están correctas.
- H2 duplicados en ficha: **confirmado** (#13), en las 5 fichas.
- "Tours de un día desde Lima" con un solo tour: **confirmado** (#12).

## Métricas por URL (390×664 salvo indicación)

| URL | LCP ms (elemento) | CLS | Peso KB* / reqs | Overflow | Primer CTA reserva |
|---|---|---|---|---|---|
| /es | 2368 (H1) · 360 px: 2776 (IMG hero) | 0.056 · 0.005 | 3014 / 51 | no | Hero "Explorar" y=397 (dentro) |
| /en | 1284 (H1) | 0 | 3014 / 51 | no | Hero y=541 (dentro) |
| /pt | 1248 (H1) | 0 | 3014 / 51 | no | Hero y=445 (dentro) |
| /es/tours | 1704 (IMG tarjeta) · 360: 1232 | 0 | 1149 / 46 | no | "Reservar" y=423 |
| /es/tours/categoria/lima | 1228 (IMG) | 0.0003 | 943 / 42 | no | y=423 |
| /es/tours/categoria/cusco | 1404 (IMG) | 0 | 1330 / 46 | no | y=369 |
| /es/tours/categoria/ica | 1312 (IMG) | 0 | 1129 / 40 | no | y=423 |
| /es/resenas | 1416 (IMG banner JPG) | 0 | 764 / 36 | **sí: sw 399** | ninguno |
| Ficha full-day-lima | **2584** (DIV fondo) | 0 | 2098 / 51 | no | y=948 (fuera) |
| Ficha laguna-humantay | 1340 (DIV); 360: 2184 | 0 | 1498 / 49 | no | y=854 (fuera) |
| Ficha huacachina-picnic | 1620 (DIV) | 0 | 1303 / 52 | no | y=912 (fuera) |
| Ficha city-tour-cusco | 1568 (DIV) | 0 | 1211 / 49 | no | y=854 (fuera) |
| Ficha machu-picchu-2-dias | **2564** (DIV) | 0 | 1322 / 49 | no | y=912 (fuera) |

*Peso = suma de content-length, subestimado. Imágenes más pesadas del home: hero WebP 433 KB, 333 KB, 261 KB. Ficha full-day: galería 409/274/241 KB.
Sin overflow en 360 ni 390 salvo /resenas. El flotante de WhatsApp mide 56×56 (cumple 44) y no oculta el CTA principal en ficha, pero sí datos (#4).

## Lo que funciona (no tocar)
- /llms.txt responde 200; sitemap con hreflang ES/EN/PT; CLS cercano a 0 en casi todas las páginas; precio con descuento claro y "Cancelación gratuita" en listado y ficha; un H1 por página; barra sticky con precio en ficha; reseñas con barras por estrellas.

## Qué no se midió
- Lighthouse: `npx --no-install lighthouse` no está instalado → **no medido** (no se instaló nada).
- INP, y LCP con throttling 4G/CPU; solo lab sin throttle, una pasada por URL.
- Umbral exacto en que aparece la barra sticky de ficha y su contraste de color (se ve oscura en captura).
- Contraste del CTA del hero sobre la foto (ghost, depende de la zona de la imagen).
- Teclado/foco, lectores de pantalla, JSON-LD, OG/Twitter (dominio del agente SEO), carrito/checkout/contacto/legales/blog (agente B), /en y /pt más allá del home.
- Landscape 667×375; viewports 320/768/1920; 1440 no aportó.
- Si "30 viajeros esta semana", "Últimos cupos" y "+8,000" son datos reales.
