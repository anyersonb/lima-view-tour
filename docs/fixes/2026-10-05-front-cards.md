# Fix front: tarjetas de tour (05/10/2026)

## Cambios
- `resources/views/components/tour-card-row.blade.php:116` chip "−N%" vuelve a `@if ($pct && $showOffer)` (como HEAD).
- `resources/views/home.blade.php` carrusel "Experiencias" (~:1040-1046): eliminada la insignia MÁS VENDIDO/DESTACADO sobre la foto y las variables muertas `$eBadge`, `$eBadgeBg`, `$eBadgeIsOrange`. Resto del carrusel intacto (los campos `badge_*` del setting `home_exp_tours` ya no se leen).
- `resources/scss/components/_cards.scss`: eliminado `&__badge` (Grep: cero usos de `tour-card__badge` en resources/ y app/).
- ADICIONAL (necesario): `tour-card.blade.php:52` y `tour-card-row.blade.php:56` -> `{{ $attributes }}` en el `<article>` raíz. Sin esto, `tours/index.blade.php` (sin commitear) pasa `data-tour-card`/`data-price`... que se perdían; `tour-filters.js` no hallaba tarjetas y ocultaba TODO el catálogo (`container.classList.toggle('hidden', visible===0)`). HEAD ni tenía `data-tour-card` en index, así que el defecto viene del lote de filtros sin commitear.

## tour-card.blade.php: ¿respeta el toggle?
En HEAD, la pastilla superior "OFERTA ESPECIAL + -N%" estaba bajo `@if ($pct && $showOffer)` (HEAD:113) y ya se eliminó por pedido. La pastilla "N% Descuento / Antes $X" dentro del bloque de precio (hoy :156) usa `@if ($pct && $before)`, SIN `$showOffer`, tanto en HEAD (:226) como ahora: no respeta el toggle. No se cambió; decidir si debe respetarlo.

## Mediciones (php artisan serve :8123, Chrome headless por playwright-core; scroll instantáneo; innerText en mayúsculas)
| Página | bp | BEST SELLER / OFERTA ESPECIAL / MÁS VENDIDO / DESTACADO / INCLUYE | cards visibles | features | scrollWidth = clientWidth |
|---|---|---|---|---|---|
| /es/tours | 375 | 0/0/0/0/0 | 12 (row) | 4 por card, sin subtítulo | 375=375 |
| /es/tours | 768 | 0/0/0/0/0 | 6 (grid) | 4 por card, 1 `<p>` por li | 768=768 |
| /es/tours | 1440 | 0/0/0/0/0 | 6 (grid) | 4 por card, 1 `<p>` por li | 1440=1440 |
| /es home | 375/768/1440 | 0/0/0/0 + "INCLUYE":1 | carrusel 4 slides, `.absolute` por slide = 0, h3 visibles 4/4 | n/a | sin overflow |
El "INCLUYE" de home es el texto del FAQ ("¿Qué incluye el precio del tour?"), no tarjeta ni carrusel; 0 tarjetas contienen esas palabras.
Toggle: tour con `show_offer_badge=false` -> chips "−N%" en /es/tours pasan de 6 a 5 (el check puede fallar: antes de restaurar daba 6).
Capturas: `docs/fixes/img/tours-{375,768,1440}.png`, `home-exp-{375,768,1440}.png`.

## Entorno / pendientes
- MySQL local de Laragon está caído: se verificó con SQLite desechable en %TEMP% (borrada), variables de entorno solo en el proceso, `.env` intacto. Home sin BD usa defaults; /es/tours necesita BD sembrada.
- `npm run build` (tras `view:cache`) OK; `public/build` regenerado. Servidor cerrado por PID 1416; puerto 8123 libre.
- Pre-existente: en 375 el título de la card-row se trunca ("Las Enigmáticas…") y "Cancelación gra…" se corta. No tocado.
- No verificado: otros callers de `x-tour-card` (show, checkout, home) tras añadir `$attributes`: no pasan atributos extra salvo index, riesgo nulo, pero no se navegó.
