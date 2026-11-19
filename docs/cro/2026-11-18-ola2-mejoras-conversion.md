# Lima View Tours: ola 2 de mejoras de conversión (móvil)

Fecha: 2026-11-18. Autores: webtilia-cro (hipótesis) y webtilia-disenador (specs), coordinados por jefe. Esta ronda NO edita código: es plan y specs para que después los implemente `webtilia-maquetacion` / `webtilia-programacion`.
Base: auditorías `2026-11-18-cro-mobile-A.md` y `-B.md`, código leído en solo lectura, local 127.0.0.1:8010 a 390 px, y la propuesta de home móvil del PDF `LIMA_VIEW_TOURS_HOME_FINAL_MOVIL_TARJETAS_INTEGRADAS.pdf`.
Fuera de alcance (ya hecho el 18-nov, commit 125ae47): contraste y tamaño del CTA, sticky de ficha, WhatsApp con ?text, img del hero de ficha, H2 sin duplicar, tap targets, reseñas sin overflow, validación inline, 404, Libro de Reclamaciones, política de devoluciones, datos legales. Culqi lo integra otro agente. Cookies: sin banner (decisión del dueño).

## 1. Resumen ejecutivo

- Lo más urgente no es diseño, es HONESTIDAD de datos. Hay prueba social falsa en el sitio: "30 viajeros reservaron esta semana" sale SIEMPRE (`show.blade.php:73`, `?? 30`, el atributo no existe), "Últimos cupos" se pinta sin condición, el rating sale como 4.8 / 4.9 / 5.0 según el sitio y "+2,000 / +8,000 / Miles de reseñas" no tienen sustento (hay 13 reseñas). "Atención 24/7" contradice el horario real (9:00 a 18:30). Esto es riesgo INDECOPI y de aprobación de Culqi, y además resta confianza. Se despliega ya, sin A/B.
- Con tráfico bajo un A/B clásico no es viable (7.000 a 10.000 sesiones de ficha por variante). Todo se despliega y se mide antes/después (4 a 6 semanas) con GA4 + Clarity + Hotjar. Primero hay que crear los eventos GA4 que faltan (H13).
- La palanca de fondo es conseguir reseñas reales (H16): 13 reseñas es muy poco para un negocio de 8 años.
- Checkout: de 8-9 campos y 6 pantallas a 4-5 campos (H5) y bloque de confianza junto al pago (H6). El atajo "Reservar ahora" (H14) va al final, cuando Culqi esté estable.
- Home: la estructura del PDF se adopta como base, pero NO puede publicarse tal cual: tiene precios tachados, "más vendido", contador de 30:00 y distribución de estrellas inventados (el propio PDF dice "maqueta visual"). Ver sección 4.
- Regla visual nueva: el naranja #a8430f queda SOLO para el CTA; garantías y urgencia en verde suave #eaf5ec con texto #145212 (8.36:1); el dorado #c8944a nunca como texto sobre fondo claro (2.38:1).

## 2. Tabla ICE priorizada (I, C, F de 1 a 10; ICE = promedio)

| Rank | ID | Página | Cambio | Métrica de éxito (cómo se mide) | ICE | Esfuerzo | Quién implementa |
|---|---|---|---|---|---|---|---|
| 1 | H1 | Ficha | Quitar "30 viajeros" y "Últimos cupos" falsos; contador real de personas reservadas en 30 días (mínimo 5) o nada | Sin subida esperada: es honestidad. Control: clics en Reservar/sesión de ficha; evento nuevo `social_proof_view` | 8.3 | S | programacion (+ maquetacion variante sin aviso) |
| 2 | H12 | Ficha/global | Cambiar "24/7" por horario real y tiempo de respuesta | Consistencia; `whatsapp_click` por sesión | 8.0 | S | programacion (textos ES/EN/PT) + clienta |
| 3 | H2 | Home/reseñas | Una sola fuente de cifras (rating, conteo y fuente con enlaces a Google/Tripadvisor); quitar "+8,000" y "Miles" | Clic en "Ver reseñas", scroll 75% del bloque; evento `review_source_click` | 7.7 | S | programacion + maquetacion |
| 4 | H8 | Ficha | Resumen de valoración pegado al título; reseñas generales con fuente si el tour tiene menos de 3; fuera "Sé el primero" | add_to_cart/sesión de ficha; `review_summary_click` | 7.7 | S | maquetacion + programacion (fallback) |
| 5 | H16 | Post-venta | Email 24 h después del tour con enlace directo de reseña Google/Tripadvisor (ES/EN/PT) | Reseñas nuevas por mes; evento `review_request_click`, UTM | 7.7 | M | programacion + clienta |
| 6 | H6 | Checkout, paso Pago | Bloque de confianza (logos, "pago seguro", cancelación 24 h, política, WhatsApp, horario), "pagar después" aclarado | add_payment_info/begin_checkout, cierre; `payment_help_click`, `checkout_step_view` | 7.3 | S | maquetacion (coordinar con agente Culqi) |
| 7 | H4 | Ficha | Garantías en un solo bloque de 4 + FAQ de 5 objeciones + "¿Dudas? WhatsApp" | add_to_cart/sesión; `faq_open`, `whatsapp_click` con tour_id | 7.0 | M | maquetacion + clienta (respuestas) |
| 8 | H13 | Medición | Eventos GA4 faltantes (select_item, cta_click, view_cart, checkout_step_view, checkout_field_error, faq_open, etc.) y dimensiones personalizadas | Habilitador: embudo view_item a purchase en móvil | 7.0 | S | programacion |
| 9 | H3 | Home | Hero que diga qué se vende, dónde y con qué garantías; CTA sólido; H1 de 3 líneas máx. | `cta_click` hero / sesiones home; CTR home a listado/ficha; rebote | 6.7 | S | maquetacion + clienta (copy) |
| 10 | H5 | Checkout, paso Datos | De 8-9 a 4-5 campos, fecha e idioma pasan a resumen, hotel único, nota plegada | Datos a Pago (`add_payment_info/begin_checkout`), `checkout_field_error`, tiempo en Clarity | 6.7 | M | programacion + maquetacion |
| 11 | H7 | Carrito | WhatsApp en recuperación: botón en el email, botón "Abrir WhatsApp" en Filament, sin envío automático | % carritos recuperados (`converted_at`), UTM `cart_recovery` | 6.3 | M | programacion |
| 12 | H10 | Ficha/listado | Urgencia honesta: próxima salida y hora de corte real (19:00 Lima) | add_to_cart con salida próxima; `urgency_view` | 6.0 | M | programacion + maquetacion |
| 13 | H14 | Carrito | Atajo "Reservar ahora": de 6 a 4 pantallas con 1 tour | begin_checkout/add_to_cart; único candidato a A/B si hay más de 100 add_to_cart/semana | 6.0 | L | programacion + maquetacion (después de Culqi y H5) |
| 14 | H9 | Listado/home | Tarjeta compacta (4 tours por pantalla) y chips "Mejor para..." | `select_item`, scroll del listado | 5.7 | M | maquetacion + clienta (etiquetas) |
| 15 | H15 | Ficha/home | Caras reales: guías, grupo, frase de la dueña | Scroll a "Reserva con confianza", clic en reservar (Clarity) | 5.7 | M | clienta (fotos) + maquetacion |

TOP 8 con spec visual: H1, H2, H8, H6, H4, H3, H5, H10. Sin diseño (van directo): H12, H13, H16. Tercera ola: H7, H9, H14, H15.

## 3. Orden de implementación por sprints

- **Sprint 0 (hoy / mañana, sin diseño, sin riesgo):** H1 quitar "30" y "Últimos cupos" falsos; H12 texto de horario; H13 eventos GA4, dimensiones personalizadas y exploración de embudo. Pedir a la clienta los datos de la sección 6.
- **Sprint 1 (checkout, coordinar con el agente Culqi):** H6 y H5 juntos. No pisar el contenedor del botón de pago. QA verifica el flujo completo ES/EN/PT, en 390 y 360, incluido "pagar después". Lectura del embudo a 4 semanas.
- **Sprint 2 (ficha en un solo pase):** H8 + H4 + H10 (comparten la zona bajo el título y la tarjeta de reserva) y la variante visual de H1. Necesita respuestas de FAQ y la regla de corte confirmada.
- **Sprint 3 (home, coordinar con la sesión que toca `home.blade.php` / `HomeDefaults` / Settings):** estructura del PDF validada (sección 4) con H3 (hero) y H2 (cifras). Tarjeta compacta H9 y chips en el listado.
- **Sprint 4:** H16 (email post-tour), H7 (WhatsApp en recuperación), H15 (caras), cuando llegue el contenido.
- **Sprint 5:** H14 (atajo Reservar ahora) solo con Culqi cerrado y H5 estable; QA completo; A/B solo si el volumen lo permite.

Cada sprint cierra con `webtilia-qa` (inventario completo de URLs, ES/EN/PT, 390 y 360; si hay cambios de backend, validar que se ven reflejados en el front) y luego `webtilia-pm` commitea. No hay Figma, así que no aplica el gate de `webtilia-ojo`; el cierre visual lo hace Anyerson con las capturas de los mockups.
Medición honesta: no concluir antes de 4 semanas (6 para H10). Excluir semanas con campañas Ads atípicas. Revisar 30 a 50 grabaciones móviles de Clarity por cambio.

## 4. Home móvil: estructura del PDF validada y mejorada

Nota de coordinación: el spec H3 de la sección 5 (hero) se escribió antes de ver el PDF. Para la home manda el PDF; del spec H3 se aprovechan la jerarquía, las medidas, los chips de garantía y el microcopy ES/EN/PT. El H1 del PDF ("Tu próxima aventura empieza aquí") es más corto que el de H3 y cumple el objetivo de 2-3 líneas; el subtítulo debe nombrar tours, destinos y garantías (no "Tours increíbles..."). La implementación se coordina con la otra sesión que edita el home.


Base de la ola 2 para la home. No se reemplaza: se valida bloque por bloque, se corrige lo que no se puede publicar tal cual y se añade lo que falta. La implementación se coordina después con la sesión que toca `HomeDefaults` / `home.blade.php`.

| # | Bloque del PDF | Veredicto | Qué se queda | Qué se cambia o se quita |
|---|---|---|---|---|
| 1 | Header + barra de confianza | Ajustar | Header compacto teal, selector ES, hamburguesa | Poner el LOGO REAL. Añadir carrito con contador (44x44). La barra dice "4.9 en nuestra web · Guías locales · WhatsApp": reemplazar por "Cancelación gratis 24 h · Pago seguro · Guías en ES/EN/PT" (hechos ya ciertos en el sitio). El icono roto del PDF (4.9 con caja) no se copia. |
| 2 | Hero de texto + buscador + chips | Se queda, con mejoras | H1 corto "Tu próxima aventura empieza aquí" (baja de 5-7 líneas a 2-3: arregla el problema de la auditoría), buscador, chips | Subtítulo con propuesta de valor concreta (qué, dónde, qué garantía) en lugar de "Tours increíbles...". Chips que filtren de verdad el listado (4 horas / Con recojo / Día completo / Privados) con evento GA4. Añadir chip Cusco / Ica para no ocultar destinos. CTA principal visible sin scroll. |
| 3 | Grilla 2x2 de tarjetas con precio y "Ver fechas" | Se queda, corregir | Tarjeta compacta con foto, 2 chips de datos, precio grande, botón. Resuelve duración repetida y títulos truncados | QUITAR "ANTES US$40", "25% descuento" y "Más vendido / Más elegido" salvo que haya precio anterior real y dato de ventas real (riesgo INDECOPI). Usar etiquetas verificables (duración, "Con recojo", "Guía ES/EN"). Las estrellas con nota solo si hay reseñas reales de ese tour. Botón "Ver fechas" a 44-48 px, contraste AA con #a8430f. Añadir un tour de Cusco y uno de Ica (2x2 con mezcla de destinos) o un selector de destino. |
| 4 | Banner "Beneficio de bienvenida" con contador 30:00 | Rechazado tal cual | La idea de un beneficio de bienvenida | El contador de 30:00 es falso (el propio PDF dice "ejemplo"). Alternativa honesta: beneficio permanente y claro ("Reserva online y recibe US$5 de descuento en Lima" solo si el dueño lo aprueba y existe el cupón real) SIN reloj, o fecha de vigencia real. Si no hay descuento real: sustituir el banner por la garantía "Cancelación gratis hasta 24 h antes". |
| 5 | Experiencia estrella Paracas + Huacachina | Se queda, corregir | Bloque editorial con foto grande, 3 puntos y CTA. Buen ritmo visual | "El tour más vendido en Lima" sin respaldo y además es de Ica: cambiar a "Nuestra experiencia estrella" o "La favorita de la casa" solo si la clienta lo confirma; usar un dato real. Mostrar duración, recojo, idiomas. El CTA dorado actual contrasta mal con texto teal pequeño: validar AA o usar el naranja #a8430f con texto blanco. |
| 6 | Quiz "Tu concierge" (2 preguntas) | Se queda como hipótesis de prueba | 2 preguntas, chips, un solo resultado | Resultado = un tour del catálogo real con enlace a su ficha (sin pedir datos). Evento GA4 por paso. Si no hay tiempo, es la pieza que se puede posponer al sprint 3. |
| 7 | Comparador "¿Lima o Pachacámac?" | Se queda, ajustar | Dos tarjetas lado a lado con VS: ayuda a decidir | Tabla corta de 4 filas (duración, entradas incluidas, recojo, precio) y un botón por tour; el botón "Ver comparativa completa" del PDF se solapa con el texto: separar. Comparar con datos reales de la ficha. |
| 8 | Franja de confianza (4.9 / Condiciones claras / 24h / ES) | Rehacer | Franja oscura de 4 datos | Contenido: "Cancelación gratis 24 h" · "Pago seguro (tarjeta/PayPal)" · "Guías en ES/EN/PT" · "Respuesta por WhatsApp". Quitar "4.9 Valoración web" y "Cambios según tour". |
| 9 | Reseñas con distribución de estrellas y filtros | Reducir | Tarjetas de reseña, filtros por fuente | La barra 82/12/4/1/1 % está INVENTADA: usar la distribución real calculada desde las reseñas aprobadas (hoy 13) o no mostrarla. Reseñas TEXTUALES de clientes con nombre, fecha y fuente enlazada (Google/Tripadvisor); nunca parafraseadas ("Destacó la puntualidad..."). Mostrar el recuento real ("5.0 · 13 reseñas") y de dónde sale. Filtro Viator solo si hay reseñas de Viator. |
| 10 | FAQ (3 preguntas) | Se queda, ampliar | Acordeón de pilares | Preguntas de objeción: cancelación y cambios de fecha, qué incluye, recojo, idiomas, pago seguro, niños. Respuestas cortas con enlace a política de devoluciones. Marcado FAQPage lo valida webtilia-seo. |
| 11 | CTA final | Se queda | "El Perú te espera. Nosotros te guiamos." | Añadir WhatsApp como segundo botón ("¿Dudas? Escríbenos") con ?text contextual. |
| 12 | Falta en el PDF | Añadir | (n/a) | Botón flotante de WhatsApp (48 px, sin tapar contenido). Carrito en header. Destinos Cusco/Ica visibles. Enlaces de footer: Libro de Reclamaciones, política de devoluciones. |

Restricciones de datos: no publicar precios tachados, descuentos, "más vendido", contador ni distribución de estrellas sin respaldo comprobable. El pie "MAQUETA VISUAL" del PDF confirma que son datos de ejemplo.

## 5. Specs del diseñador y detalle de hipótesis

Mockups HTML y capturas 390/360 en scratchpad\ola2\mockups (m1 ficha, m2 hero home, m3 tarjeta compacta, m4 checkout, m5 comparador). Copiarlos a docs/cro/mockups-ola2/ si se quieren conservar.

### 5.A Specs visuales (webtilia-disenador)

#### Lima View Tours: specs de diseño, segunda ola (móvil 390/360)

Fecha: 2026-11-18. Autor: webtilia-disenador. Reconciliado con `cro-hipotesis.md` (TOP 8 del CRO: H1, H2, H8, H6, H4, H3, H5, H10).
No se tocó el repo ni prod. Local visto solo para navegar (127.0.0.1:8010).

Mockups HTML (390 px) y capturas 390/360, en `...\scratchpad\ola2\mockups\`:
- `m1-ficha-confianza.html` (+ `-390.png`, `-360.png`): H8 + H1 (sin dato) + H10 + H4. La barra fija inferior y el WhatsApp aparecen "pegados" en la captura full-page; en uso real van fijos al fondo.
- `m2-home-hero.html`: H3 (+ H2 en la franja de cifras).
- `m3-tarjeta-compacta.html`: tarjeta de listado compacta (H9, tercera ola; la dejo lista porque ya estaba pedida).
- `m4-checkout.html`: H5 (paso Datos) + H6 (bloque de confianza en Pago).
- `m5-comparador.html`: "¿Qué tour me conviene?" (no priorizado por el CRO; solo boceto, ver sección final).
- `base.css`: tokens usados (teal, dorado, naranja CTA, crema). Los mockups no usan clases Tailwind compiladas; son solo referencia visual y de medidas.

##### 0. Sistema visual respetado y ratios de contraste (calculados)

Colores existentes: teal `#0f3438` (texto/botón oscuro), teal `#15474b` (foco, precio en barra), dorado `#c8944a`, naranja CTA `#a8430f`, crema de fondo `#f5f0ed`, verde de confirmación `#145212` sobre `#eaf5ec`. Fuentes: Albert Sans (UI), Hedvig Letters Serif (títulos y precios).

| Combinación | Ratio | Veredicto |
|---|---|---|
| Teal `#0f3438` sobre crema `#f5f0ed` | 11.84 | AAA, texto general |
| Teal sobre blanco | 13.39 | AAA |
| Blanco sobre CTA `#a8430f` | 6.04 | AA (no llega a AAA 7; aceptable, ya está en uso) |
| Naranja `#a8430f` como texto sobre crema | 5.34 | AA, solo para texto de 14 px o más y estrellas |
| Teal al 70 % (`text-teal-800/70`) sobre crema | 4.96 | AA justo; no bajar de 70 % |
| Teal al 70 % sobre blanco | 5.22 | AA |
| Teal al 60 % sobre blanco | 3.90 | FALLA para texto chico: no usar |
| Dorado `#c8944a` sobre teal `#0f3438` | 4.97 | AA solo sobre fondo oscuro |
| Dorado sobre crema/blanco | 2.38 / 2.70 | FALLA: dorado nunca como texto sobre claro (solo filetes/adornos) |
| Verde `#145212` sobre `#eaf5ec` | 8.36 | AAA |
| Rojo error `#b42318` sobre blanco | 6.57 | AA (siempre con icono y texto) |

Regla: la urgencia y la garantía NO se pintan en dorado ni en naranja relleno (el naranja se reserva para el CTA). Garantías = verde suave `#eaf5ec` con texto `#145212`; urgencia honesta = mismo verde. Así el único elemento naranja de cada pantalla es el botón.

Clases nuevas: la mayoría de los bloques se resuelve con utilidades ya compiladas (`rounded-2xl`, `bg-white`, `text-teal-800`, `ring-teal-800/10`, `h-[48px]`-tipo arbitrarias solo si ya están compiladas). Marco con **[CSS nuevo]** lo que probablemente exige recompilar o un bloque `<style>` en el blade (como ya hace `show.blade.php` con `.lvt-resbar`). Recomendación: BEM `lvt-*` en el mismo `<style>` de la vista y así no hace falta recompilar en prod.

---

##### H1. Contador de reservas honesto (y fin del "30" y "Últimos cupos" falsos) [hipótesis H1]

**Objetivo y métrica.** Quitar un dato falso (`$tour->bookings_this_week ?? 30`, `show.blade.php:73`; el atributo no existe). Métrica: `social_proof_view` (type = `bookings_30d` | `none`); CTA/sesión de ficha como control, no se espera subida.

**Jerarquía.** Es un dato secundario: una línea bajo el precio, nunca compite con el botón. Si no hay dato real, no se muestra nada (no se rellena con otra cosa en ese sitio; el hueco lo ocupa el bloque de garantías, H4).

**Estructura conceptual.**
```blade
@if($bookings30d >= config('lvt.social_proof_min', 5))
  <p class="lvt-proof-line" role="status">
    <svg aria-hidden="true" .../> {{ trans_choice('ui.travelers_booked_30d', $bookings30d, ['n'=>$bookings30d]) }}
  </p>
@endif
```
Estilo: `text-[13px] text-teal-800/70` (4.96:1 sobre crema), icono teal de 16 px, sin emoji de fuego, sin fondo de color. Altura de línea 20 px; margen 8 px arriba y abajo.

**Microcopy.**
- ES: "12 viajeros reservaron este tour en los últimos 30 días"
- EN: "12 travelers booked this tour in the last 30 days"
- PT: "12 viajantes reservaram este passeio nos últimos 30 dias"
- Singular (n=1): no se muestra (umbral mínimo 5).

**"Últimos cupos".** Solo si existe cupo restante real por fecha (el calendario ya mostró "5 cupos de 20"): "Quedan 5 cupos para el 23 nov". Sin cupo real: se retira la insignia (también "CUPOS LIMITADOS" de la galería, que hoy sale siempre).

**Estados.** Cargando: no aplica (se calcula en servidor, cache 1 h). Vacío / sin dato / menos de 5: no se imprime nada. Error de consulta: igual que vacío (silencioso). Hover/focus: no interactivo.

**Medidas.** 390 y 360: la línea cabe en 2 líneas máximo (13 px, ~42 caracteres por línea). Sin tap target (no es enlace).

**Accesibilidad.** `role="status"` no hace falta si es estática; usar `<p>` simple. Icono `aria-hidden`.

**Dato de la clienta.** Confirmar qué estados de reserva cuentan (pagadas, "pagar después") y el umbral de 5; si quiere cupo real, el cupo máximo por tour/fecha.

---

##### H2. Cifras de confianza con una sola fuente y fuente visible [hipótesis H2]

**Objetivo y métrica.** Eliminar 4.8 / 4.9 / 5.0 y "+2,000 / +8,000 / Miles de reseñas". Métrica: `review_source_click` (source google|tripadvisor|web) y scroll al 75 % en home.

**Jerarquía.** En la franja del home (la tarjeta blanca bajo el hero) la primera cifra es la valoración con su conteo y fuente; las demás cifras solo si son verificables.

**Estructura.** Reutilizar la tarjeta de cifras actual (`home.blade.php:408`), alimentada por una única variable `$reviewStats` (la misma de `/es/resenas`):
```blade
<a href="{{ $googleProfileUrl }}" rel="noopener" target="_blank" class="lvt-stat lvt-stat--rating">
  <b class="font-display text-[22px]">5.0 <span aria-hidden="true">★</span></b>
  <span class="text-xs text-teal-800/70">13 reseñas verificadas</span>
</a>
```
Debajo del carrusel de reseñas del home: línea "Google 4 · Tripadvisor 2 · Web 7" con cada plataforma como enlace al perfil real (no a una búsqueda con `sca_esv`).

**Microcopy.**
- ES: "5.0 · 13 reseñas verificadas" / pie: "Reseñas reales de Google, Tripadvisor y de nuestra web."
- EN: "5.0 · 13 verified reviews" / "Real reviews from Google, Tripadvisor and our website."
- PT: "5.0 · 13 avaliações verificadas" / "Avaliações reais do Google, Tripadvisor e do nosso site."
- "Viajeros felices": solo con dato sustentable. Si no: "Desde 2013" (verificable) en vez de "+8,000". Cifra 3 de la franja: "+50 tours únicos" solo si hay 50 publicados; hoy el listado muestra 6, así que debe leerse "tours y experiencias en Perú" sin número o con el número real.
- Quitar "Miles de reseñas verificadas" (`home.blade.php:1226`).

**Estados.** Sin reseñas (0): se oculta la cifra de valoración y se muestran las otras dos. Menos de 5 reseñas: se muestra "{n} reseñas verificadas" sin promedio. Datos de plataforma sin URL: el texto de plataforma queda sin enlace (nunca un `#`). Focus: anillo `outline-teal-700` 2 px, offset 2 px (clase ya compilada: `focus-visible:outline-teal-700`).

**Medidas.** Franja del home a 390: tarjeta de 350 px de ancho, 3 columnas de ~110 px (en el mockup `m2`), cifra 22 px, texto auxiliar 12 px (4.96:1), altura ≥ 72 px; la columna de valoración es enlace: tap ≥ 44 px (toda la celda). A 360: columnas de ~100 px, el texto auxiliar pasa a 2 líneas; nada se corta.

**Accesibilidad.** Enlace con texto "5.0, 13 reseñas verificadas. Abre Google en pestaña nueva" (aria-label). Estrellas decorativas `aria-hidden`.

**Dato de la clienta.** URL del perfil de Google Maps y de Tripadvisor; conteo real por plataforma; permiso para mostrar nombre y país; año de inicio ("Desde 2013" si es cierto) y base del número de viajeros.

---

##### H8. Prueba social en la ficha, sin estado vacío negativo [hipótesis H8]

**Objetivo y métrica.** Quitar "Sé el primero en dejar una reseña" y poner un resumen de valoración pegado al título. Métrica: `add_to_cart` / sesiones de ficha; secundaria `review_summary_click` (placement title|section).

**Jerarquía (ver `m1-ficha-confianza`).** Título H1 → línea de valoración (1 línea, 44 px de alto táctil) → tarjeta de reserva. Es lo primero que se lee tras el título.

**Estructura.**
```blade
<a href="#opiniones" class="lvt-rating-line">   {{-- min-h-[44px] flex items-center gap-2 text-sm --}}
  <span class="lvt-stars" aria-hidden="true">★★★★★</span>   {{-- color #a8430f, 15 px --}}
  <b>5.0</b>
  <span>· <u>13 reseñas verificadas</u> (Google, Tripadvisor)</span>
</a>
```
Estrellas en naranja oscuro `#a8430f` (5.34:1 sobre crema), no dorado (2.38:1).

Sección "Opiniones de nuestros viajeros" más abajo: 
- Con 3 o más reseñas del tour: las del tour, con nombre, país, fecha y fuente.
- Con menos de 3: 3 reseñas de la empresa con etiqueta "Reseñas de Lima View Tours" + fuente con enlace (reutilizar `components/review-card.blade.php`), y el formulario baja a un enlace pequeño "Cuéntanos tu experiencia" (solo texto, 44 px de alto).

**Microcopy.**
- ES: "5.0 · 13 reseñas verificadas (Google, Tripadvisor)" · etiqueta de sección "Reseñas de Lima View Tours" · enlace "Cuéntanos tu experiencia".
- EN: "5.0 · 13 verified reviews (Google, Tripadvisor)" · "Reviews of Lima View Tours" · "Tell us about your trip".
- PT: "5.0 · 13 avaliações verificadas (Google, Tripadvisor)" · "Avaliações da Lima View Tours" · "Conte-nos sobre sua viagem".

**Estados.** Sin ninguna reseña en toda la web: la línea de valoración no se imprime; la sección de reseñas desaparece de la ficha (no "sé el primero"). Cargando: no aplica. Hover: subrayado ya presente; focus: outline teal 2 px.

**Medidas.** 390 y 360: línea de valoración 14 px, envuelve a 2 líneas a 360 (altura total ≤ 48 px, tap ≥ 44 px). Margen 8 px sobre la tarjeta de reserva; añade ~52 px al fold de la ficha (aceptable: el CTA sticky ya está desde la carga).

**Accesibilidad.** Enlace ancla `#opiniones` con foco gestionado (`tabindex=-1` en el título de la sección). Reseñas en `<article>` con `<blockquote>`.

**Dato de la clienta.** Ya listado en H2; además, autorización de mostrar reseñas con nombre y país, y asignación de reseñas a tours si se puede.

---

##### H6. Confianza alrededor del botón de pago (sin tocar Culqi) [hipótesis H6]

**Objetivo y métrica.** Pasar de "Pago seguro" en gris 12 px a un bloque visible. Métrica: paso Datos→Pago (`add_payment_info`/`begin_checkout`) y cierre; nuevo `payment_help_click` (channel whatsapp|phone|policy).

**Jerarquía (ver `m4-checkout`, parte inferior).** De arriba abajo: (1) resumen de una línea tour/fecha/personas/total; (2) elección pagar ahora / después; (3) **bloque de confianza** (candado + texto + logos); (4) línea de garantía + política; (5) botón de pago (contenedor del agente Culqi, no se toca); (6) ayuda: WhatsApp + teléfono + horario.

**Estructura.**
```blade
<section class="lvt-trust rounded-2xl bg-white p-4 ring-1 ring-teal-800/10" aria-labelledby="trust-t">
  <h3 id="trust-t" class="text-[15px] font-bold">🔒 {{ __('ui.pay_secure_title') }}</h3>
  <p class="text-[13px] text-teal-800/70">{{ __('ui.pay_secure_body') }}</p>
  <ul class="flex flex-wrap gap-2 my-3" aria-label="{{ __('ui.pay_methods') }}">
    <li>@include('partials.pay-logo-visa')</li> ... Mastercard, Amex, PayPal (Culqi solo si está activo)
  </ul>
  <p class="lvt-guarantee">🛡️ Cancelación gratis hasta 24 h antes · <a href="{{ route('refund-policy') }}">Política de cambios</a></p>
</section>
{{-- botón de pago: slot del agente Culqi --}}
<p class="lvt-help">¿Dudas antes de pagar? <a href="https://wa.me/51925886725?text=..." data-wa-placement="payment">Escríbenos por WhatsApp</a> · +51 925 886 725 · 9:00–18:30</p>
```
Logos: reusar los 4 del footer (ya existen como assets), cada uno en chip de 28 px de alto, borde `#dfe8e8`. Garantía: fondo `#eaf5ec`, texto `#145212` (8.36:1), 13 px, radio 10 px. **[CSS nuevo]** menor (`.lvt-trust`, `.lvt-guarantee`), cabe en el `<style>` de la vista.

**Microcopy.**
- ES: título "Pago seguro" · cuerpo "Tus datos de tarjeta viajan cifrados. No guardamos tu tarjeta." · ayuda "¿Dudas antes de pagar? Escríbenos por WhatsApp" · wa text "Hola, necesito ayuda con mi reserva de {tour} del {fecha}".
- EN: "Secure payment" · "Your card details are encrypted. We never store your card." · "Questions before paying? Message us on WhatsApp" · "Hi, I need help with my booking for {tour} on {date}".
- PT: "Pagamento seguro" · "Os dados do seu cartão viajam criptografados. Não guardamos seu cartão." · "Dúvidas antes de pagar? Fale conosco no WhatsApp" · "Olá, preciso de ajuda com minha reserva de {tour} em {data}".
- Opción "pagar después" (corrige informe B #18): ES "No se cobra ahora. Te enviaremos el enlace de pago antes del {fecha}." / EN "Nothing is charged now. We'll send you a payment link before {date}." / PT "Nada é cobrado agora. Enviaremos o link de pagamento antes de {data}." (sin doble punto).

**Estados.** Culqi/PayPal cargando: el bloque de confianza ya está (no depende de la pasarela); el botón muestra su propio estado. Error de pago: mensaje rojo `#b42318` + icono + texto encima del botón, y la línea de ayuda por WhatsApp pasa a destacarse ("No pudimos procesar el pago. Escríbenos y te ayudamos."). Sin logo activo de un medio: no se muestra ese logo (no prometer medios no disponibles). Horario: fuera de horario, la línea dice "Te respondemos mañana desde las 9:00".

**Medidas.** 390: bloque 358 px de ancho (16 px de margen lateral), padding 16 px, título 15 px, cuerpo 13 px (5.22:1 sobre blanco). Logos 28 px alto. Enlaces de ayuda: `min-height: 44px; display:inline-flex`. Altura total del bloque ≈ 150 px. 360: logos pasan a 2 filas si no caben (flex-wrap). La barra fija inferior (`.cart-sticky`, 72 px) exige `padding-bottom: 96px` en el contenedor (informe B #9, ya corregido; verificar que el bloque de ayuda no queda tapado).

**Accesibilidad.** Logos como lista con `aria-label` y cada logo con `alt` ("Visa"). Candado decorativo. Contraste verificado en la tabla 0.

**Dato de la clienta.** Texto de "pago seguro" aprobado, logos de medios realmente activos en Culqi, horario y tiempo de respuesta real, razón social/RUC para el pie legal.

---

##### H4. Garantías en un solo bloque + FAQ de objeciones cerca del CTA [hipótesis H4]

**Objetivo y métrica.** Reemplazar las garantías repetidas 4 veces por un bloque único de 4 íconos y añadir un acordeón corto. Métrica: `add_to_cart`/sesión de ficha; `faq_open`; `whatsapp_click` (placement tour).

**Jerarquía (ver `m1`).** Tarjeta de reserva → una línea de garantía dentro de la tarjeta (ya existe: "Cancelación gratis hasta 24 h antes · Pago seguro", 13 px, 4.96:1) → rejilla 2×2 de garantías → título "Antes de reservar" → acordeón → botón WhatsApp.

**Estructura.** Rejilla 2×2 (reusa `.m-trust`/`.m-features` del blade actual; es **reordenar y unificar**, no inventar):
```blade
<ul class="grid grid-cols-2 gap-2">   {{-- 4 <li> --}}
  <li class="flex gap-2 rounded-[14px] bg-white p-3 min-h-[64px]"> ICONO <div><b class="text-sm block">Cancelación gratis</b><span class="text-[13px] text-teal-800/70">Hasta 24 h antes</span></div></li>
  Pago seguro · Guía en ES/EN/PT (solo idiomas reales del tour: $tourLanguage) · Confirmación inmediata
</ul>
<h2 class="font-display text-[22px] mt-6">Antes de reservar</h2>
@foreach($tourFaqs as $faq)
  <details class="rounded-[14px] bg-white mb-2">
    <summary class="min-h-[48px] px-4 py-3 font-semibold text-[15px] flex justify-between items-center">{{ $faq['question'] }}</summary>
    <p class="px-4 pb-3.5 text-sm text-teal-800/80">{{ $faq['answer'] }}</p>
  </details>
@endforeach
<a class="lvt-wa-doubt" href="{{ $waTourUrl }}">¿Dudas? Pregunta por WhatsApp</a>
```
Usar `<details>/<summary>` nativo (sin JS, accesible, el acordeón actual `.m-faq` ya existe: reutilizarlo y alinear estilos). Máximo 5 preguntas, cerradas por defecto salvo la primera. Reusar `components/faq-section.blade.php` si encaja; si usa Alpine, conservar. **[CSS nuevo]** solo para marcador `+`/`–` (`summary::after`), va en el `<style>` de la vista.

**Preguntas sugeridas (5) y respuestas base, a validar por la clienta.**
1. ¿Y si cambian mis planes? ES: "Cancelas gratis hasta 24 horas antes de la salida y te devolvemos el pago. Ver política de cambios." EN: "Cancel free up to 24 hours before departure and we refund your payment. See our change policy." PT: "Cancele grátis até 24 horas antes da saída e devolvemos seu pagamento. Veja a política de alterações."
2. ¿Me recogen en mi hotel? ES: "Sí, el recojo está incluido [zonas]. Te confirmamos la hora por correo." EN: "Yes, pickup is included [areas]. We confirm the time by email." PT: "Sim, o translado está incluído [zonas]. Confirmamos o horário por e-mail."
3. ¿Qué incluye el precio? "Transporte, guía y lo indicado en Incluye. Sin cargos extra." (EN/PT equivalentes.)
4. ¿Puedo ir con niños? (por tour; edad mínima y altitud en Machu/Humantay).
5. ¿Qué pasa si hay mal clima? (por tour).

**Estados.** Tour sin FAQs propias: mostrar solo las 3 genéricas (1 a 3). Sin respuesta confirmada por la clienta: no publicar esa pregunta (nunca respuesta inventada). Focus: `summary:focus-visible` outline teal 2 px. Abierto: icono `–`.

**Medidas.** 390: rejilla de 2 columnas de 171 px, padding 12 px, título 14 px, texto 13 px; a 360 columnas de 156 px: el texto envuelve a 2-3 líneas, `min-height: 64px` evita saltos. Cada `summary` ≥ 48 px de alto, texto 15 px. Botón WhatsApp 48 px de alto, ancho completo (borde verde `#1f7a45` 1.5 px, texto `#145212` 8.36:1).

**Accesibilidad.** `<details>` nativo; el `summary` es el único elemento focusable por fila; no duplicar H2 (resuelto hoy: una sola versión del árbol). JSON-LD FAQPage lo valida `webtilia-seo`.

**Dato de la clienta.** Respuestas reales por tour (niños, edad mínima, altitud, clima, hora y puntos de recojo, idioma de guía, cuándo se paga, equipaje).

**Aviso H12.** El texto "Atención al cliente 24/7" (`lang/es/ui.php:110`) contradice el horario del footer: en cualquier bloque nuevo usar "Respondemos por WhatsApp de 9:00 a 18:30 (hora de Lima)" hasta que la clienta confirme otra cosa.

---

##### H3. Propuesta de valor en el hero del home [hipótesis H3]

**Objetivo y métrica.** Pasar el test de 5 segundos: que se entienda "tours en Perú, con guía, cancelación gratis". Métrica: `cta_click` hero_primary / sesiones home; CTR home→listado/ficha.

**Nota.** En el local el H1 ya es "Descubre lo que te transforma." en 3-4 líneas y el CTA tiene fondo teal con círculo naranja; la propuesta sigue sin decir qué se vende. Este spec parte de lo local.

**Jerarquía (ver `m2-home-hero`).** Eyebrow dorado en mayúsculas (sobre foto oscurecida, 4.97:1+) → H1 (3 líneas máx.) → subtítulo de una frase → 3 chips de garantía → CTA primario sólido → enlace secundario → franja de cifras.

**Estructura.**
```blade
<section class="hero relative ... min-h-[calc(100svh-72px)] max-h-[664px]">
  <p class="eyebrow text-[12px] tracking-[.14em] uppercase text-[#f1d3a6]">Tours en Perú con guía local</p>
  <h1 class="font-display text-[32px] leading-[1.08] text-white">Conoce Perú con guías locales y sin sorpresas</h1>
  <p class="text-base text-white/90 max-w-[34ch]">Tours en Lima, Cusco e Ica. Cancelas gratis hasta 24 h antes y reservas en minutos.</p>
  <ul class="chips"> 3 chips de 36 px: Cancelación gratis · Guía ES/EN/PT · Confirmación inmediata </ul>
  <a class="btn-cta bg-[#a8430f] text-white min-h-[52px] rounded-full font-bold">Ver tours y precios ›</a>
  <a class="underline min-h-[44px] text-white">¿No sabes cuál elegir? Te ayudamos</a>   {{-- ancla a #comparador o WhatsApp --}}
</section>
```
Capa de contraste: degradado `from-teal-900/55 to-teal-900/90` sobre la foto (clases `from-teal-900/45` y `to-teal-900/80` ya compiladas; si 55/90 no está, usar 45/80: texto blanco ≥ 7:1 en la mitad inferior).

**Microcopy.**
- ES: eyebrow "Tours en Perú con guía local" · H1 "Conoce Perú con guías locales y sin sorpresas" · sub "Tours en Lima, Cusco e Ica. Cancelas gratis hasta 24 h antes y reservas en minutos." · CTA "Ver tours y precios" · secundario "¿No sabes cuál elegir? Te ayudamos".
- EN: "Peru tours with local guides" · "Discover Peru with local guides, no surprises" · "Tours in Lima, Cusco and Ica. Cancel free up to 24 h before and book in minutes." · "See tours and prices" · "Not sure which one? We'll help".
- PT: "Passeios no Peru com guias locais" · "Conheça o Peru com guias locais e sem surpresas" · "Passeios em Lima, Cusco e Ica. Cancele grátis até 24 h antes e reserve em minutos." · "Ver passeios e preços" · "Não sabe qual escolher? Ajudamos você".
- Ojo: EN es más largo (el H1 en EN llegó a 7 líneas en prod): a 390 máximo 4 líneas con 32 px; si pasa, bajar a 28 px con `clamp(28px, 8vw, 34px)`.
- La garantía "100 % garantizada" actual es una promesa vaga: sustituir por las 3 concretas.

**Estados.** Imagen cargando: fondo teal `#0f3438` ya presente (evita salto de CLS). Sin conexión a la foto: el degradado teal sigue dando contraste. Hover (desktop): botón `#9f430f`; focus: anillo blanco 2 px + offset (sobre foto oscura) para el CTA y los enlaces.

**Medidas.** 390×664 visibles: header 72 + hero; H1 32 px (≈ 3 líneas, 108 px); sub 16 px (≈ 3 líneas); chips en 2 filas (36 px); CTA 52 px; enlace 44 px; franja de cifras al borde inferior del fold (alto ≈ 76 px). Suma ≈ 600 px: caben las cifras sin scroll. A 360 el CTA queda 328 px de ancho (16 px de margen); los chips pasan a 2+1 filas; verificar que la franja de cifras siga en el fold (si no, quitar el eyebrow).

**Accesibilidad.** Un solo H1; chips como `<ul>`; el CTA es un enlace (`<a>`), no botón; el enlace secundario abre el comparador o WhatsApp con texto contextual.

**Dato de la clienta.** Promesa de marca de una línea que prefiera; las 3 garantías que sí cumple; idiomas con guía por tour.

---

##### H5. Checkout, paso Datos: menos campos y sin repeticiones [hipótesis H5]

**Objetivo y métrica.** Pasar de 8-9 controles y ~1.300 px de scroll a 4-5 campos. Métrica: Datos→Pago (`add_payment_info/begin_checkout`), `checkout_field_error` (field, error_type), tiempo hasta completar (Clarity).

**Jerarquía (ver `m4-checkout`, parte superior).** Barra de pasos → resumen compacto (tour, fecha, personas, idioma, total, "Cambiar") → 4 campos → "Añadir nota" plegado → términos → botón "Ir al pago" (barra fija ya existente).

**Estructura.**
- Resumen (nuevo, reusa estilo de tarjeta): `rounded-[14px] bg-white px-3.5 py-3`, tour en 14 px bold, detalle 13 px `text-teal-800/70`, total en serif 20 px, enlace "Cambiar" (44 px de alto, lleva al paso 1). Esto sustituye los campos fecha e idioma del paso 2.
- Campos: Nombre completo · Correo · Teléfono con prefijo · Hotel/recojo (UN solo campo, opcional, ayuda "Puedes completarlo después por WhatsApp"). Cada uno `min-h-[48px]`, `text-base` (16 px evita zoom en iOS), etiqueta visible 13 px bold sobre el campo, `autocomplete` (ya presente), `aria-describedby` al mensaje de error.
- "Comentarios" detrás de un botón "Añadir nota (opcional)" de 44 px de alto (`<details>`).
- Prefijo: `select` actual, con países frecuentes arriba (ya existe); añadir tecleo para saltar (tipo `datalist` o escribir la inicial funciona de forma nativa en `select`); mantener el componente `phone-country-select`.
- Checkbox de términos: caja de 24 px y enlace con `min-height:44px` / `padding-block:12px` (hoy 14 px de alto).

**Microcopy de errores (ya hechos inline hoy; unificar texto).**
- Nombre vacío: ES "Escribe tu nombre para la reserva" / EN "Enter your name for the booking" / PT "Digite seu nome para a reserva".
- Correo: ES "Revisa tu correo, parece incompleto" / EN "Check your email, it looks incomplete" / PT "Confira seu e-mail, parece incompleto".
- Teléfono: ES "Añade un teléfono con WhatsApp para avisarte si hay cambios" / EN "Add a phone with WhatsApp so we can reach you if anything changes" / PT "Informe um telefone com WhatsApp para avisarmos de qualquer mudança".
- Ayuda de recojo: ES "Hotel o dirección. Puedes completarlo después por WhatsApp." / EN "Hotel or address. You can add it later via WhatsApp." / PT "Hotel ou endereço. Você pode completar depois pelo WhatsApp."

**Estados.** Error: borde `#b42318` 1.5 px + icono "!" + texto rojo bold 13 px (6.57:1; nunca solo color), `aria-invalid="true"`, scroll y foco al primer campo con error. Válido: sin check verde (evita ruido). Cargando "Ir al pago": botón con spinner, deshabilitado y `aria-busy`. Hover/focus: anillo `focus:ring-teal-700` (ya compilado). Pre-rellenado: si el visitante vuelve, valores guardados (ya hay `guardar-contacto` al salir del correo).

**Medidas.** 390: contenedor con 16 px de margen lateral (358 px de ancho útil), etiqueta→campo 4 px, entre campos 14 px, campo 48 px. Teléfono: prefijo 96 px + 8 px de hueco + número 254 px. Altura total del paso ≈ 640 px (de 1.300): el botón de la barra fija entra en el primer scroll. 360: prefijo 92 px, número ~236 px; sin overflow. Padding inferior ≥ 96 px por la barra fija (72 px) + WhatsApp.

**Accesibilidad.** `<label for>`, errores en `role="alert"` sólo el del primer campo (los demás por `aria-describedby`); target ≥ 44 px; orden de tab: nombre → correo → prefijo → teléfono → hotel → nota → términos → pago.

**Dato de la clienta.** Confirmar que el recojo puede completarse después, que fecha e idioma no se piden en este paso, y la política de uso del teléfono.

---

##### H10. Urgencia honesta: próxima salida y hora límite [hipótesis H10]

**Objetivo y métrica.** Aprovechar un dato real (la regla "para el día siguiente se reserva hasta las 7:00 p. m. hora de Lima" y las fechas libres del calendario). Métrica: `add_to_cart` de fichas con salida próxima; `urgency_view` (type next_departure|cutoff, hours_left).

**Jerarquía (ver `m1`, dentro de la tarjeta de reserva).** Debajo del precio y sobre "Fecha del tour": una píldora verde de una línea. No compite con el CTA (naranja).

**Estructura.**
```blade
@if($nextDeparture)
  <p class="lvt-next rounded-xl bg-[#eaf5ec] text-[#145212] px-3 py-2.5 text-sm font-semibold flex gap-2">
    📅 {{ __('ui.next_departure', ['when'=>$nextDepartureLabel]) }}
  </p>
@endif
@if($cutoffMinutes !== null && $cutoffMinutes <= 180)
  <p class="lvt-cutoff ..." role="status">{{ __('ui.cutoff_left', ['t'=>$cutoffLabel]) }}</p>
@endif
```
Cálculo en servidor con hora de Lima (America/Lima); el contador de los últimos 180 min se actualiza cada minuto con JS ligero y NO se reinicia al recargar (calculado desde el corte real). Antes de 180 min restantes, solo la línea fija "reserva hasta hoy 19:00".

**Microcopy.**
- ES: "Próxima salida disponible: mañana, 19 nov · 05:00" · "Reserva hasta hoy 19:00 (hora de Lima)" · "Quedan 2 h 10 min para reservar la salida de mañana".
- EN: "Next available departure: tomorrow, Nov 19 · 5:00 am" · "Book by 7:00 pm today (Lima time)" · "2 h 10 min left to book tomorrow's departure".
- PT: "Próxima saída disponível: amanhã, 19 nov · 05:00" · "Reserve até hoje às 19:00 (horário de Lima)" · "Faltam 2 h 10 min para reservar a saída de amanhã".
- Listado: etiqueta "Sale mañana" / "Departs tomorrow" / "Sai amanhã" solo con fecha real.

**Estados.** Sin fecha disponible: no se muestra la píldora; en su lugar, "Consulta fechas por WhatsApp" (enlace con texto de tour). Pasado el corte: el cálculo pasa a la siguiente fecha. Sin dato de cupos: nunca "Últimos cupos". Con cupo real <= 30 %: "Quedan 5 cupos para el 23 nov" (mismo estilo). Cargando: el servidor la imprime ya (sin salto de layout; si va por JS, reservar 44 px de alto).

**Medidas.** 390: píldora 328 px de ancho dentro de la tarjeta (padding 16), texto 14 px semibold, altura 44 px (2 líneas a 360 = 60 px). Verde sobre verde claro 8.36:1.

**Accesibilidad.** El contador usa `role="status"` pero `aria-live="off"` por minuto (no anunciar cada tic; solo al montar). Respeta `prefers-reduced-motion`: sin animación en la píldora.

**Dato de la clienta.** Confirmar que la regla del corte de las 19:00 se respeta y si existe cupo real por fecha.

---

##### Tercera ola: dejadas listas (no TOP 8, sin ID prioritario)

###### Tarjeta compacta del listado/home (H9) — `m3-tarjeta-compacta.html`
- Problema (informe A #14, #7): duración repetida, títulos truncados "…", botón de 27 px, ~230 px por tarjeta.
- Spec: grid `112px 1fr`, altura ~148-160 px (vs ~230), título 17 px Hedvig a 3 líneas (`line-clamp-3`, completo en casi todos), una línea de meta ("11 h · Recojo incluido · Cancelación gratis" con la duración UNA vez), precio 22 px + tachado 12 px + "−20 %" (mismo `bg` teal-100 que hoy), botón "Ver tour ›" 44 px de alto (teal `#0f3438`, texto blanco 13.39:1; el naranja queda para reservar en ficha), insignia "★ 5.0 (n)" solo si el tour tiene reseñas propias. El badge de "Cancelación gratuita" ya es texto verde `#145212`.
- Efecto: de ~2 a ~4 tours por pantalla de 664 px. Cambio dentro de `tour-card-responsive.blade.php` (variante móvil `sm:` ya separada). Sin CSS nuevo salvo `line-clamp-3` (clases `line-clamp-2` ya compiladas; **[CSS nuevo]** si no existe `line-clamp-3`: usar `<style>` inline).
- WhatsApp flotante: 48 px (pedido por el informe A #4) y fuera de la columna de precios (`right:12px; bottom: 88px`).

###### "¿Qué tour me conviene?" — `m5-comparador.html`
- El CRO no lo prioriza (H9 propone chips "Mejor para: …" más barato que un asistente de 3 pasos). Recomendación: **hacer primero los chips** (una fila de 4 chips de 44 px sobre el listado) y dejar el asistente de 3 preguntas para cuando haya más de 10 tours.
- Si se hace: 3 pantallas con barra de progreso, opciones de 56 px de alto, preguntas: "¿Cuánto tiempo tienes?" (medio día / un día / 2 días o más), "¿Qué prefieres?" (cultura e historia / naturaleza y aventura / gastronomía), "¿Con quién viajas?" (solo o pareja / familia con niños / grupo). Resultado: 1-2 tarjetas de tour con el motivo ("Ideal si tienes un día y quieres historia"). Sin registro. Requiere etiquetas por tour en el CMS ("ideal para"), que hoy no existen.

---

##### Qué datos y archivos necesito de la clienta (consolidado, alineado con la lista del CRO)
1. Perfil oficial de Google Maps/Business y Tripadvisor, con enlace directo de "dejar reseña"; conteo real por plataforma; permiso para nombre y país del reseñista (H2, H8).
2. Cifras sustentables: "viajeros" (histórico de reservas), "años" (desde 2013) y cuántos tours son "únicos" (H2, H3).
3. Estados de reserva que cuentan para el contador y su umbral; cupo máximo real por tour/fecha; confirmación del corte de las 19:00 (H1, H10).
4. Respuestas reales por tour: niños, edad mínima, altitud, clima, hora y puntos de recojo, idioma de guía, cuándo se paga, equipaje (H4).
5. Horario real de WhatsApp y tiempo de respuesta; ¿teléfono de emergencia durante el tour? (H12, H6).
6. Logos de medios de pago activos en Culqi; texto de "pago seguro" aprobado; razón social y RUC (H6).
7. Promesa de marca de una línea y las 3 garantías que cumple (H3).
8. Confirmar que el recojo puede completarse después y la política de uso del teléfono (H5).
9. Etiqueta "ideal para" por tour (H9, solo si hacen los chips).

##### Orden de implementación sugerido (diseño)
1. H1 (quitar el "30" y "Últimos cupos"), sin diseño, hoy.
2. H6 y H5 (checkout, van juntos; coordinar con el agente Culqi para no pisar el contenedor del botón de pago).
3. H8 + H4 + H10 en la ficha en un solo pase (comparten la zona bajo el título), porque se ven en `m1`.
4. H2 y H3 en el home en otro pase (franja de cifras + hero).
5. Tarjeta compacta (H9) cuando se cierren las anteriores.

##### Límites de este documento
- Mockups con degradados en lugar de fotos; textos de ejemplo con datos del local (no de prod). Contraste calculado, no medido con herramienta de a11y sobre el render final.
- No se probó nada en iOS Safari real ni en pantalla de 320 px.
- Nada de estos bloques se ha implementado ni probado en el repo.

### 5.B Detalle de hipótesis (webtilia-cro)

#### Lima View Tours: CRO segunda ola (móvil 390/360) - hipótesis priorizadas con ICE

Fecha: 2026-11-18. Solo lectura. Base: auditorías A y B de hoy + lectura del código + navegación del local (127.0.0.1:8010, 390x844). No se tocó prod ni se envió nada.
Capturas locales: `...\scratchpad\ola2\home_fold.png`, `list_fold.png`, `ficha_fold.png`, `ficha_full.png`.

ICE = promedio de Impacto, Confianza y Facilidad (1-10). Confianza baja = hipótesis sin dato propio, solo práctica de la industria.

##### Hallazgos de código que sostienen las hipótesis (verificados)
1. **"30 viajeros reservaron este tour esta semana" es FALSO en el código.** `resources/views/tours/show.blade.php:73`: `$bookingsWeek = $tour->bookings_this_week ?? 30;`. Ese atributo no existe en ningún modelo, migración ni controlador (grep en `app/`, `database/`, `resources/`), así que siempre sale 30 en todos los tours. Se pinta en `show.blade.php:1367` (aviso móvil) y `:1916`.
2. **"Últimos cupos" también es fijo**: `show.blade.php:1225` y `:1772` lo pintan sin condición (no depende de cupos reales).
3. **"Más reservado"** depende de `is_most_booked ?? is_featured`: es un flag manual, no un dato de ventas (aceptable si el dueño lo marca a mano; no presentarlo como "dato").
4. **Cifras con 3 fuentes distintas**: `home.blade.php:408-409` toma de Settings `stats_rating` (defecto 4.8), `home_stat_travelers` (defecto +2,000), `stats_years` (+11); en el bloque de reseñas del home (`home.blade.php:1204,1220`) el "4.9" está escrito a mano; `/es/resenas` calcula 5.0 de 13 reseñas; prod muestra "+8,000". En el local hoy salen 4.8 y +2,000; en prod 4.9 y +8,000. Ninguna sale de datos reales.
5. **"Atención al cliente 24/7"** (`lang/es/ui.php:110`, usado en ficha `:1725` y `:2443`) contradice el footer: "9:00 a.m. – 6:30 p.m. Lun–Dom". Promesa que no se cumple = riesgo de confianza.
6. **Ficha sin reseñas propias muestra "Sé el primero en dejar una reseña de este tour"** (visto en el local): prueba social negativa justo antes del CTA.
7. Ya existe infraestructura útil: tabla `abandoned_carts` (email, teléfono, items, token de recuperación) + comando `SendAbandonedCartReminders` (1er recordatorio a 60 min, 2.º a 24 h) + ruta `/carrito/recuperar/{token}`. Es decir, el carrito abandonado por email YA existe; falta el canal WhatsApp y medirlo.
8. Eventos GA4 existentes vía `window.lvtTrack`: `view_item`, `add_to_cart`, `begin_checkout`, `add_payment_info`, `purchase`/`reserva_pagar_despues` (en thanks), `whatsapp_click`. Faltan los de embudo superior (lista, CTA hero, FAQ, errores de formulario, vista de carrito).
9. El hero del home no dice qué se vende ni dónde: H1 "Descubre lo que te transforma." + botón "EXPLORAR EXPERIENCIAS". Hay que leer más abajo para saber que son tours en Perú. Falla el test de 5 segundos.

##### Honestidad sobre qué se puede probar (tráfico bajo)
- No tengo el tráfico real de GA4 (dato que pide a la clienta/dueño más abajo). Con una conversión ficha→"añadir al carrito" típica de ~4-6% y detectando una mejora relativa de +20%, un A/B necesita del orden de **7.000-10.000 sesiones de ficha por variante**. Con tráfico bajo (unos cientos de sesiones/día entre todas las fichas) eso son meses: **no hacer A/B clásicos**.
- Estrategia realista: **desplegar y medir antes/después** (4 semanas vs 4 semanas previas, mismo canal de tráfico, excluyendo semanas con campañas Ads atípicas) sobre micro-conversiones que ocurren más seguido: clic en CTA, add_to_cart, begin_checkout, clic en WhatsApp. Usar **Clarity/Hotjar** (ya instalados) para validar de forma cualitativa: mapas de clic y grabaciones (mirar 30-50 sesiones móviles por cambio).
- Solo se puede A/B-testear algo con efecto grande (>=40-50% relativo) o con mucho tráfico (home/ficha de Machu Picchu si concentra visitas). Marcado en cada hipótesis.
- Cambios de honestidad/legales/cumplimiento (H1, H2, H12) **no se testean, se despliegan**.
- Ojo: el sitio no tiene banner de cookies (decisión del dueño); Consent Mode está en `granted`, así que GA4 mide a todos. Bien para medir, pero a la clienta hay que decirle que eso es una decisión suya.

##### Tabla ICE (ordenada por puntaje)

| Rank | ID | Página | Hipótesis (resumen) | I | C | F | ICE | Esfuerzo | Quién | TOP 8 diseñador |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | H1 | Ficha | Reemplazar "30 viajeros/Últimos cupos" falsos por contador real de reservas o retirarlos | 8 | 9 | 8 | **8.3** | S | programacion | SI |
| 2 | H12 | Ficha/Global | Corregir promesa "24/7" por horario real de atención + tiempo de respuesta | 5 | 9 | 10 | **8.0** | S | contenido cliente + programacion | no (solo texto) |
| 3 | H2 | Home/Reseñas | Una sola fuente de cifras con conteo y fuente (Google/Tripadvisor) enlazadas | 7 | 8 | 8 | **7.7** | S | programacion + contenido cliente | SI |
| 4 | H8 | Ficha | Sustituir "Sé el primero en reseñar" por prueba social real (reseñas del tour o generales con fuente) | 7 | 8 | 8 | **7.7** | S | maquetacion | SI |
| 5 | H16 | Post-venta | Pedir reseña de Google/Tripadvisor por email/WhatsApp tras el tour (genera volumen real) | 8 | 8 | 7 | **7.7** | M | programacion + cliente | no (email) |
| 6 | H6 | Checkout paso Pago | Confianza alrededor del botón de pago: logos, contacto, política, microcopy (sin tocar Culqi) | 7 | 7 | 8 | **7.3** | S | maquetacion | SI |
| 7 | H4 | Ficha | Garantías + FAQ de objeciones cerca del CTA (colapsable, 4-5 preguntas) | 7 | 7 | 7 | **7.0** | M | maquetacion + contenido cliente | SI |
| 8 | H13 | Medición | Eventos GA4 faltantes (embudo completo) | 5 | 9 | 7 | **7.0** | S | programacion | no (habilitador) |
| 9 | H3 | Home | Propuesta de valor above-the-fold (qué, dónde, por qué confiar) | 7 | 6 | 7 | **6.7** | S | maquetacion + contenido | SI |
| 10 | H5 | Checkout paso Datos | Quitar campos repetidos y fusionar hotel (de 8 controles a 5) | 8 | 7 | 5 | **6.7** | M | programacion + maquetacion | SI |
| 11 | H7 | Carrito/checkout | Recuperación por WhatsApp (clic-para-chatear con resumen; enlace en el email de recuperación) | 6 | 6 | 7 | **6.3** | M | programacion | no |
| 12 | H10 | Ficha/Listado | Urgencia honesta: "próxima salida" y hora límite real de reserva | 6 | 6 | 6 | **6.0** | M | programacion + maquetacion | SI |
| 13 | H14 | Carrito | Atajo "Reservar ahora": ficha directo a Datos+Pago cuando hay 1 tour (6 a 4 pantallas) | 9 | 6 | 3 | **6.0** | L | programacion | no (después de H5) |
| 14 | H9 | Listado | Ayuda para elegir ("¿Cuál es para mí?") en vez de tabla comparativa + tarjeta más compacta | 6 | 5 | 6 | **5.7** | M | maquetacion | no |
| 15 | H15 | Ficha/Home | Caras reales: guía, foto de viajeros, "quiénes somos" | 6 | 6 | 5 | **5.7** | M | contenido cliente + maquetacion | no |

**TOP 8 para el diseñador (especificar primero): H1, H2, H8, H6, H4, H3, H5, H10.**
H12, H13 y H16 no necesitan diseño: salen directo a programación (son texto, eventos y un email). H7, H14, H9, H15 quedan para tercera ola.

##### Detalle por hipótesis

###### H1 - Contador de reservas honesto (reemplaza el "30" falso) [TOP 8]
- Página: ficha `/es/tours/detalle/*` (aviso `.m-notice` móvil y barra de confianza).
- Problema/evidencia: `show.blade.php:73` `?? 30`; el atributo no existe, siempre 30 en los 6 tours del local y en prod. "Últimos cupos" se pinta siempre (`:1225`, `:1772`). Un turista que compare dos fichas ve el mismo "30" y pierde confianza; además es publicidad engañosa (Indecopi) y Culqi revisa el sitio.
- Cambio propuesto: (a) calcular desde `bookings` el número de personas con `status` confirmado/pagado en los últimos 30 días para ese `tour_id` (suma `adults+children`); (b) mostrar solo si >= 5 (umbral configurable): "12 viajeros reservaron este tour en los últimos 30 días"; si es menor, no mostrar nada y dejar el espacio para la garantía de cancelación; (c) "Últimos cupos" solo si el cupo restante real es <= 30% y existe dato de cupo por fecha (el calendario ya parece manejar capacidad: se vio "5 cupos de 20" en el local); si no hay dato de cupo, retirar la etiqueta. (d) El estado vacío no inventa nada. Cachear 1 h.
- Métrica primaria: clic en CTA Reservar / sesiones de ficha (no se espera subida; el objetivo es no perder confianza y quitar riesgo legal). Secundaria: tasa de rebote de ficha.
- Medición: GA4 existentes + nuevo evento `social_proof_view` (params: `tour_id`, `type`=`bookings_30d`|`none`, `value`). Antes/después.
- Muestra/tiempo: no A/B; se despliega ya. Lectura a 4 semanas.
- Esfuerzo S. Implementa programacion (consulta + cache) y maquetacion (variante sin aviso).
- Necesita de la clienta: confirmar si se cuentan reservas con "pagar después" y su estado; validar que el umbral de 5 es aceptable; si quiere cupo real, cupo máximo por tour/fecha.

###### H12 - Promesa de atención coherente
- Página: ficha `show.blade.php:1725` y `:2443`, y cualquier "24/7".
- Problema: "Atención al cliente 24/7" vs footer "9:00-18:30". Dos datos distintos para la misma pregunta.
- Cambio: texto "Te respondemos por WhatsApp de 9:00 a 18:30 (hora de Lima), normalmente en menos de 30 min" (usar el tiempo real que confirme la clienta). Si de verdad hay atención 24/7 en salida del tour, decir "Asistencia durante tu tour".
- Métrica: clic `whatsapp_click` por sesión de ficha (cualitativa); sobre todo consistencia. Despliegue directo.
- Esfuerzo S. Implementa programacion (lang/es,en,pt) + contenido.
- Necesita: horario real y tiempo de respuesta real; ¿hay teléfono de emergencia durante el tour?

###### H2 - Cifras de confianza con una sola fuente y fuente visible [TOP 8]
- Página: home (franja de cifras y bloque reseñas) y `/es/resenas`.
- Evidencia: 4.8 (Settings) / 4.9 (hardcode) / 5.0 (reseñas) y +2,000 / +8,000; "Miles de reseñas verificadas" (`home.blade.php:1226`) cuando hay 13. Un solo dato real contradice las demás.
- Cambio: calcular rating y conteo desde la misma tabla de reseñas (la que alimenta `/es/resenas`) y mostrar "5.0 · 13 reseñas (Google 4, Tripadvisor 2, web 7)" con enlaces al perfil de Google Maps y de Tripadvisor (links directos, no búsqueda con parámetros). "Viajeros felices" solo con una cifra que la clienta pueda sustentar (ej. "Más de X viajeros desde 2013" si lo puede demostrar con histórico de reservas; si no, mostrar "Operando desde 2013" que es verificable). Quitar "Miles de reseñas". Si el conteo es bajo, decirlo con orgullo: "13 reseñas reales, todas verificables".
- Métrica primaria: clic en "Ver reseñas" y scroll hasta la sección reseñas en home (scroll depth 75%); secundaria: CTR a ficha desde home.
- Medición: nuevo `review_source_click` (params: `source`=google|tripadvisor|web, `page`, `placement`); `select_item` ya sugerido en H13. Clarity: mapa de clics de la franja.
- Muestra: no A/B. Antes/después 4 semanas.
- Esfuerzo S. programacion (variable única) + maquetacion (franja).
- Necesita: URL oficial de Google Maps y Tripadvisor, nº real de reseñas por plataforma, año de inicio y base verificable para "viajeros".

###### H8 - Prueba social en ficha sin estado vacío negativo [TOP 8]
- Página: ficha, sección "Opiniones de nuestros viajeros" (en el local: "Sé el primero en dejar una reseña de este tour. Añade una valoración").
- Problema: justo antes del CTA el visitante lee que nadie opinó. Y esa sección se repite en cada ficha.
- Cambio: si el tour tiene < 3 reseñas, mostrar 3 reseñas generales de la empresa (Google/Tripadvisor) con etiqueta "Reseñas de Lima View Tours" + fuente + enlace, y mover el formulario de reseña a un enlace pequeño "Cuéntanos tu experiencia" (solo para quien ya viajó). Con >=3 reseñas del tour, mostrar esas primero con nombre, país, fecha y fuente. Añadir un mini resumen (5.0 · 13 reseñas) pegado al título de la ficha, encima de la tarjeta de reserva (en 390 px una línea de 14 px, tocable hacia la sección de reseñas).
- Métrica primaria: add_to_cart / sesiones de ficha (tour con reseñas propias vs sin). Secundaria: scroll a la sección reseñas, clic en "Ver todas".
- Medición: evento nuevo `review_summary_click` (params `tour_id`, `placement`=title|section); `view_item` ya existe. Hotjar: heatmap en ficha.
- Muestra: no A/B; antes/después. Si Machu Picchu concentra tráfico, comparar solo esa ficha.
- Esfuerzo S. maquetacion (componente) + programacion (fallback de reseñas generales).
- Necesita: autorización de la clienta para mostrar las reseñas con nombre y país tal cual aparecen en Google/Tripadvisor; asignar reseñas a tours si se puede.

###### H16 - Generar reseñas reales (la palanca de fondo)
- Página: post-venta (email + WhatsApp), `/es/resenas`.
- Problema: 13 reseñas es muy poco para una web con 8+ años; toda la prueba social depende de esto. La tabla `bookings` ya guarda email, teléfono, `locale` y `travel_date`.
- Cambio: 24 h después de `travel_date` enviar un email (y enlace a WhatsApp si hay opt-in) con 2 botones: "Dejar reseña en Google" (enlace corto directo de reseña) y "en Tripadvisor". Un tercer paso en la web (reseña propia) solo si ya se hace. Plantilla en ES/EN/PT según `locale`. Comando programado, uno por reserva, nunca a reservas canceladas o sin pago.
- Métrica primaria: nº de reseñas nuevas por mes (Google Business Profile) y clics en el enlace (UTM `utm_source=email&utm_campaign=post_tour`).
- Medición: GA4 con UTM + evento `review_request_click`. Resultado esperado realista: 5-10% de los viajeros dejan reseña; con 20-40 reservas/mes son 2-4 reseñas al mes.
- Muestra: no aplica A/B; se mide por volumen mensual. A los 3 meses se debe ver el rating con conteo > 30.
- Esfuerzo M. programacion (comando + mail) + contenido (texto) + cliente.
- Necesita: enlace corto de reseña de Google Business y de Tripadvisor, remitente de correo oficial, confirmación de que puede escribir a los viajeros tras el tour (aviso en privacidad).

###### H6 - Confianza alrededor del pago (sin tocar la integración Culqi) [TOP 8]
- Página: checkout paso Pago (`/es/carrito`, paso 3) 390/360.
- Evidencia (informe B #12): "Pago seguro · Sin cargos extra" en gris 12 px, logos de tarjeta solo en el footer, sin contacto ni política junto al botón; opción "pagar después" con fecha de cobro ambigua (#18).
- Cambio: encima del botón de pago: fila de logos Visa/Mastercard/Amex/PayPal (Culqi cuando esté activo) + candado + "Pago cifrado. No guardamos tu tarjeta"; debajo: "Cancelación gratis hasta 24 h antes · [Política de cambios y devoluciones]" y "¿Dudas? Escríbenos por WhatsApp" (con `?text=` "Necesito ayuda con mi reserva {referencia}") + horario real. Reescribir la opción "pagar después" a "No se cobra ahora. Te enviaremos el enlace de pago antes del {fecha}." Mostrar el resumen (tour, fecha, personas, total) en una línea compacta sobre el botón. Todo en bloque, sin sobrecargar el 390.
- Métrica primaria: `add_payment_info` / `begin_checkout` (tasa de paso Datos→Pago completado) y compra/begin_checkout (tasa de cierre).
- Medición: eventos existentes + nuevos `payment_help_click` (params `channel`=whatsapp|phone|policy, `step`) y `checkout_step_view` (params `step`=cart|data|payment, `tour_id`, `value`).
- Muestra: no A/B. Con pocas compras mensuales, mirar el embudo paso a paso (más muestras) durante 4-6 semanas y confirmar con grabaciones Clarity de sesiones que llegan a Pago.
- Esfuerzo S. maquetacion; programacion para el `?text=` y eventos. Coordinar con el agente Culqi para no pisar el contenedor del botón.
- Necesita: tiempo real de respuesta, texto del aviso de pago seguro aprobado por la clienta, logos de medios activos en Culqi.

###### H4 - Garantías + FAQ de objeciones cerca del CTA [TOP 8]
- Página: ficha, debajo de la tarjeta de reserva (390).
- Evidencia: las garantías existen (cancelación, pago seguro, recojo, guía bilingüe) pero repetidas 4 veces en bloques distintos; no hay un FAQ visible en el local (la sección tour_faqs depende de datos del tour y no sale en el tour revisado). Las objeciones típicas de turista extranjero: "¿Me recogen en mi hotel? ¿a qué hora?", "¿Qué pasa si no hay clima/si me enfermo?", "¿Puedo ir con niños?", "¿El guía habla mi idioma?", "¿Cuándo pago?".
- Cambio: un solo bloque de garantías de 4 íconos (reemplaza las repeticiones) + acordeón de 5 preguntas con respuestas cortas bajo el CTA (cerrado por defecto, 1 línea cada una). Contenido por tour donde cambie (niños, edad mínima, altitud en Machu/Humantay). Marcar con FAQPage (lo valida `webtilia-seo`).
- Métrica primaria: add_to_cart/sesión de ficha; secundaria: clics a WhatsApp desde ficha (esperado ↓ en consultas repetidas, o ↑ calificadas).
- Medición: eventos nuevos `faq_open` (params `tour_id`, `question_id`) y `whatsapp_click` ya existente (añadir parámetro `page_type`=tour y `tour_id`). Hotjar mapa de clic.
- Muestra: no A/B salvo en la ficha más visitada; antes/después.
- Esfuerzo M. maquetacion + contenido cliente.
- Necesita: respuestas reales a las 5 objeciones por tour (políticas de niños, lluvia/clima, hora de recojo, altitud, cuándo se paga, equipaje), tiempo de espera y puntos de recojo.

###### H3 - Propuesta de valor above-the-fold en el home [TOP 8]
- Página: `/es` `/en` `/pt` 390x664.
- Evidencia: H1 "Descubre lo que te transforma." en 5 líneas (7 en EN), botón fantasma "EXPLORAR EXPERIENCIAS" (informe A #18); no dice tours/Perú/qué se ofrece. Franja de confianza ("Reserva fácil, segura y 100% garantizada") aparece en el fold pero sin concreto.
- Cambio: H1 en 2-3 líneas con la propuesta ("Tours en Perú con guía en español e inglés: Lima, Ica y Cusco"); subtítulo de una línea con 3 garantías concretas ("Recojo en tu hotel · Cancelación gratis 24 h · Pago seguro"); botón primario sólido "Ver tours" + enlace secundario "Escríbenos por WhatsApp" (con texto precargado); chip de reseñas "5.0 · 13 reseñas en Google" (H2) bajo el botón. Mantener la foto.
- Métrica primaria: clic en CTA hero / sesiones home; CTR home→listado/ficha; rebote home. Secundaria: Clarity "rage/dead clicks" en hero.
- Medición: nuevo `cta_click` (params `cta_id`=hero_primary|hero_whatsapp, `page_type`=home, `locale`). Antes/después 4 semanas. Candidato a A/B solo si el home recibe >3.000 sesiones móviles/mes y el efecto esperado es grande.
- Esfuerzo S. maquetacion; copy lo aprueba la clienta.
- Necesita: promesa de marca que ella prefiera, 3 garantías ciertas, idiomas en que hay guía por tour.

###### H5 - Datos del checkout: menos campos y sin repeticiones [TOP 8]
- Página: `/es/carrito` paso Datos, 390.
- Evidencia (B #8): 8-9 controles; fecha e idioma se repiten del carrito; dos campos de hotel con el mismo placeholder; scroll ≈1.300 px; selector de prefijo con ~200 países.
- Cambio: dejar solo nombre, correo, WhatsApp/teléfono (con prefijo por país detectado por idioma) y hotel/recojo en UN campo con texto "Puedes completarlo después por WhatsApp" (opcional). Fecha e idioma pasan a una línea de resumen con "Editar". Comentarios plegados detrás de "Añadir nota". Checkbox de términos con enlace de área >=44 px. Guardar contacto al salir del campo correo (ya existe `guardar-contacto`) para alimentar el recordatorio de carrito.
- Métrica primaria: tasa de paso Datos→Pago (`add_payment_info` / `begin_checkout`) y tiempo hasta completar (Clarity). Secundaria: abandono por campo.
- Medición: nuevo `checkout_field_error` (params `field`, `error_type`) y `checkout_step_view`. Antes/después 4-6 semanas.
- Muestra: bajo volumen: leer el embudo semanal y 30 grabaciones Clarity. No A/B.
- Esfuerzo M. programacion (campos y backend) + maquetacion.
- Necesita: confirmar que el recojo puede completarse después y que no se necesita fecha/idioma en este paso; política de uso del teléfono.

###### H10 - Urgencia honesta: próxima salida y hora límite
- Página: ficha y tarjeta del listado.
- Evidencia: el calendario ya sabe de bloqueos y de la regla "para el día siguiente se reserva hasta las 7:00 p. m. (hora de Lima)" (visto en el local). Ese dato real no se aprovecha como urgencia.
- Cambio: bajo "Fecha del tour": "Próxima salida disponible: mañana 06:00 · reserva hasta hoy 19:00 (hora de Lima)" y, si falta menos de 3 h para el corte, "Quedan 2 h 10 min para reservar la salida de mañana" (contador real, sin reiniciarse). En el listado, una etiqueta "Sale mañana" solo si hay fecha. Nunca cupos inventados.
- Métrica primaria: add_to_cart de fichas con fecha próxima (la mayoría de turistas planifican con días; efecto principal en último minuto). Secundaria: reservas con travel_date <= 3 días.
- Medición: nuevo `urgency_view` (params `tour_id`, `type`=next_departure|cutoff, `hours_left`). Antes/después.
- Muestra: no A/B; se despliega, tamaño de efecto probablemente pequeño. Aceptar sin concluir antes de 6 semanas.
- Esfuerzo M. programacion (zona horaria Lima) + maquetacion.
- Necesita: confirmar la regla del corte (19:00) y que se respeta, cupo real si existe.

###### H13 - Eventos GA4 que faltan (habilitador de todo lo anterior)
- Ya existen: `view_item`, `add_to_cart`, `begin_checkout`, `add_payment_info`, `purchase`, `reserva_pagar_despues`, `whatsapp_click`.
- Crear con `lvtTrack` (mismos nombres GA4 recomendados donde existan):
  - `view_item_list` / `select_item` (params `item_list_name`=home_carousel|tours_list|category_x, `items[0].item_id`, `index`) al ver/clic en tarjeta.
  - `cta_click` (params `cta_id`, `page_type`, `tour_id`, `locale`) para hero, Reservar, barra sticky, "Ver disponibilidad".
  - `view_cart` (params `value`, `currency`, `items`) al abrir el carrito.
  - `checkout_step_view` (params `step`, `tour_id`, `value`).
  - `checkout_field_error` (params `field`, `error_type`).
  - `faq_open` (params `tour_id`, `question_id`) y `review_source_click` / `review_summary_click` (H2/H8).
  - `payment_help_click` (H6), `social_proof_view` (H1), `urgency_view` (H10).
  - `whatsapp_click`: añadir params `page_type`, `tour_id`, `placement`=floating|menu|payment (hoy parece enviar solo el evento).
  - `generate_lead` en envío correcto del formulario de contacto (gate de redirect a /gracias).
- En GA4 Admin: registrar `tour_id`, `cta_id`, `page_type`, `placement`, `step`, `field` como dimensiones personalizadas de evento; marcar como evento clave `begin_checkout`, `add_payment_info`, `purchase`, `reserva_pagar_despues`, `generate_lead`.
- Construir una exploración de embudo en GA4: view_item → add_to_cart → begin_checkout → add_payment_info → purchase, filtrado por dispositivo móvil.
- Esfuerzo S/M. programacion. Necesita: acceso a GA4 para registrar dimensiones.

###### H7 - Recuperación por WhatsApp (tercera ola, por facilidad media)
- Página: carrito/checkout y correo de recuperación.
- Evidencia: existe email de recuperación a 60 min y 24 h (`SendAbandonedCartReminders`, tabla `abandoned_carts` con email y teléfono). Para turistas de LATAM el WhatsApp suele cerrar mejor.
- Cambio: sin envío automático por WhatsApp (requiere API Business y consentimiento): (1) en el correo de recuperación, botón "Escríbenos por WhatsApp" con `?text=` "Hola, dejé mi reserva de {tour} para el {fecha} y tengo una duda"; (2) en el panel Filament de carritos abandonados, botón "Abrir WhatsApp" con mensaje prellenado para que la clienta escriba a mano a quienes dejaron teléfono; (3) en el paso Datos, tras escribir el teléfono: "Si prefieres, te guardamos la reserva y te escribimos".
- Métrica primaria: % de carritos recuperados (`abandoned_carts.converted_at` no nulo / total con contacto). Secundaria: reservas con `utm_source=cart_recovery`.
- Medición: UTM en enlaces del email; `whatsapp_click` con `placement`=recovery_email; BD ya guarda `status` y `converted_at`.
- Muestra: no A/B. Medir 8 semanas.
- Esfuerzo M. programacion; la persona de la clienta que atiende debe contestar.
- Necesita: política de uso del teléfono, quién responde y en qué horario.

###### H14 - Atajo "Reservar ahora" (6 pantallas a 4) (después de H5)
- Evidencia (B): ficha → carrito → Datos → Pago, 6 pantallas/3 formularios, 4 taps hasta Datos tras elegir fecha.
- Cambio: cuando el carrito tiene un solo tour, el botón RESERVAR lleva al paso Datos con el resumen visible arriba; el carrito queda accesible por el icono. El paso Carrito (cupón, +tours) pasa a ser opcional. Importante: no toca la integración Culqi, solo la navegación previa.
- Métrica primaria: begin_checkout / add_to_cart y compra/add_to_cart.
- Muestra: es el único cambio con efecto potencialmente grande (+30-50% en el paso). Aún así con tráfico bajo se mide antes/después; podría hacerse A/B con 50/50 si hay >100 add_to_cart por semana.
- Esfuerzo L. programacion + maquetacion; riesgo de regresión en el flujo → exige QA completo. No empezar hasta cerrar Culqi.
- Necesita: confirmación de que no se vende upselling en el carrito que se perdería.

###### H9 - Ayuda para elegir en el listado (no tabla comparativa)
- Página: `/es/tours` (6 tours).
- Evidencia: con 6 tours una tabla comparativa sobra; el problema real es que cada tarjeta ocupa ~230 px y repite la duración (informe A #14), y no hay guía de "para quién es".
- Cambio: tarjeta de 2 líneas de título completo, 1 línea de duración, rating (si existe), precio y botón 48 px; arriba del listado chips "Mejor para: 1 día desde Lima · Familias · Primera vez en Perú · Aventura" que filtran. Más tarde, si crece el catálogo (>10), añadir comparación de 2 tours.
- Métrica primaria: clic a ficha desde listado (`select_item`) y profundidad de scroll del listado. Medición: H13.
- Esfuerzo M. maquetacion + contenido (etiquetas por tour).
- Necesita: etiqueta "ideal para" por tour.

###### H15 - Caras y fotografía propia
- Evidencia: no hay rostros (guías, equipo) en las fichas ni en el home salvo fotos de paisaje; con 13 reseñas, las personas reales pesan más que los íconos.
- Cambio: "Conoce a tus guías" (3 fotos con nombre, idiomas, años de experiencia), foto de grupo real en la ficha y en `/es/resenas`; una frase de la dueña ("Soy X, fundadora...").
- Métrica: tiempo en página y scroll a "Reserva con confianza"; clic en reservar. Medición cualitativa con Clarity.
- Esfuerzo M. contenido cliente + maquetacion. Necesita: fotos y permiso de uso, nombres, bio corta.

##### Lista "necesita contenido/dato de la clienta"
1. Confirmar que NO existen 30 reservas/semana por tour y autorizar el contador real (H1); umbral mínimo (sugiero 5 en 30 días).
2. Cupos reales por fecha, si los manejan (H1/H10); regla real del corte de las 19:00.
3. Horario real de atención y tiempo medio de respuesta de WhatsApp; ¿hay línea de emergencia durante el tour? (H12/H6).
4. URL oficial de Google Maps/Business y Tripadvisor, con enlace directo de "dejar reseña" (H2/H16).
5. Conteo real de reseñas por plataforma y autorización para mostrar nombre/país de cada reseñista (H2/H8).
6. Base verificable de "viajeros" (histórico de reservas desde 2013 o desde el sistema nuevo) y de "años de experiencia"; elegir cifra única de rating (el código dice 4.8, 4.9 y 5.0 en tres lugares) (H2).
7. Respuestas por tour a: niños y edad mínima, altitud/salud, clima o lluvia, hora y puntos de recojo, idioma de guía, cuándo se paga, equipaje (H4).
8. Promesa de marca de una línea y 3 garantías que sí cumple (H3).
9. Fotos y presentación del equipo/guías con permiso (H15).
10. Aprobación para escribir a viajeros tras el tour (H16) y política del teléfono (H5/H7); quién responde WhatsApp.
11. Dato de tráfico real (sesiones móviles/mes por home, listado, ficha; compras/mes) desde GA4 para decidir si algún cambio se puede A/B testear.
12. Etiqueta "ideal para" por tour (H9).

##### Orden recomendado de ejecución
1. Ahora (sin diseño, sin riesgo): H1 (retirar el 30 falso ya), H12, H13.
2. Diseñador (TOP 8): H2, H8, H6, H4, H3, H5, H10 con los datos de la clienta de arriba. H6 primero porque se alinea con Culqi.
3. Después: H16 (efecto acumulativo), H7, H9, H15.
4. Última: H14, cuando el flujo Culqi esté cerrado y estable.

##### No medido / límites
- No se midió Lighthouse ni tráfico real; las estimaciones de muestra son orientativas.
- Local con BD de pruebas (cifras 4.8/+2,000, 6 tours) difiere de prod (4.9/+8,000); las capturas son del local.
- No se probó pago ni formularios (regla).

## 6. Qué necesita contenido o decisión de la clienta

1. Confirmar que NO hay 30 reservas por semana por tour, aprobar el contador real (umbral sugerido: 5 en 30 días) y qué estados de reserva cuentan (pagada, pagar después).
2. URL oficial de Google Maps/Business y de Tripadvisor, con enlace directo de "dejar reseña"; conteo real por plataforma; permiso para mostrar nombre y país de cada reseñista; reseñas TEXTUALES (no parafraseadas).
3. Cifras sustentables: viajeros atendidos (histórico), años (desde 2013) y cuántos tours son propios. Elegir UNA cifra de rating.
4. Horario real de WhatsApp, tiempo de respuesta y si hay teléfono de emergencia durante el tour.
5. Respuestas reales por tour: niños y edad mínima, altitud/salud, clima o lluvia, hora y puntos de recojo, idioma del guía, cuándo se paga, equipaje.
6. Cupo real por fecha (si existe) y confirmación de la regla de corte de las 19:00 hora de Lima.
7. Promesa de marca de una línea y las 3 garantías que sí cumple; idiomas de guía por tour.
8. Logos de medios de pago activos en Culqi, texto de "pago seguro" aprobado, razón social y RUC.
9. Confirmar que el recojo puede completarse después y la política de uso del teléfono; quién responde WhatsApp.
10. Autorización para escribir a viajeros tras el tour (H16) y remitente de correo oficial.
11. Fotos de equipo y guías con permiso, nombres y bio corta (H15); foto de grupo real.
12. Para la home del PDF: si de verdad existe un beneficio de bienvenida (US$5 Lima / US$10 Ica) con cupón real y vigencia; si hay precios anteriores demostrables para mostrar tachados (si no, no se muestran); logo real en vector; tours de Cusco e Ica para la grilla; etiqueta "ideal para" por tour; tráfico real de GA4 (sesiones móviles/mes y compras/mes).

## 7. Límites

Sin tráfico real de GA4: los tamaños de muestra son orientativos. Sin Lighthouse. El local tiene datos de prueba distintos de prod. Mockups no probados en iOS Safari real ni a 320 px. No se probó pago ni formularios. Nada de esto está implementado.
