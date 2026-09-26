# QA-3 — Verificación POST-fix FINAL (Links de pago)

Fecha: 2026-09-25
Verificador: anyerson-qa
Repo: `G:\laragon\www\lima-tour` — SIN commitear, LOCAL (http://lima-tour.test)
Fuente: `docs/payment-links/FIX-1.md` + `docs/payment-links/FIX-2.md` (fixes aplicados)
Alcance: SOLO los fixes de FIX-1/FIX-2 y su regresión directa. No se repite el QA completo.
Reglas estrictas: sin cambiar contraseñas/.env/Settings/tokens; sin navegadores fuera del MCP Playwright;
sin renombrar tablas ni alterar esquema; portapapeles NO se lee (lo verifica el coordinador).

## Puntos
1. PENDIENTE — Copiar enlace (3 vías + consola + 375px)
2. PASS — Toggle "un solo uso" ausente de form (comentario en PaymentLinkResource.php:136-143, N-1) y tabla (sin columna). `saving()` fuerza single_use=1 en create (probado con single_use:false explícito → guardó true) y en duplicado (replicate real → true).
3. PASS — /pagar/qa3test001: `X-Robots-Tag: noindex, nofollow` + 1 solo `<meta name="robots" content="noindex,nofollow">`. Home (/es) y ficha real de tour (/es/tours/detalle/{slug}): sin X-Robots-Tag, meta `index,follow,...` normal, 200 OK.
4. PASS — /pagar/nonexistentcode123 → HTTP 404 (confirmado con curl -I).
5. PASS — nota interna "NOTA INTERNA SECRETA QA3" (campo `note`) con 0 apariciones en el HTML de /pagar/qa3test001.
6. PARCIAL PASS — buyer_* seteado por tinker (buyer_name/email/phone) → 0 apariciones en /pagar (curl). Duplicar (código real replicate([...]) ejecutado con datos reales): customer_* y buyer_* quedan NULL en la copia, single_use sigue true. Falta: clic real en el botón "Duplicar" desde el panel (ver punto 1, no llegué por tiempo — ver "No verificado").
7. PASS — Individual: link con `paypal_capture_id` seteado → `delete()` devuelve false, sigue existiendo. Lote mixto (2 sin capture + 1 con capture, usando la lógica real de `DeleteBulkAction::using()`): deleted=2, protected=1, título exacto "2 eliminados, 1 protegido (tiene un cobro registrado)"; el protegido sigue en BD, los otros 2 ya no.
8. PASS — /admin/payment-links carga. Badge lateral "Links de pago 3" (snapshot Playwright) coincide exacto con `PaymentLink::where('status','pending')->count()` = 3 (tinker). Guarda `Schema::hasTable()` en `getNavigationBadge()` (PaymentLinkResource.php:41-43) cubierta por `test_navigation_badge_does_not_crash_when_table_is_missing` — no repetí el renombrado en vivo de la tabla (ya falseado por backend-laravel en FIX-1, ver "Falsación" en FIX-1.md).
9. PASS — `phpunit --filter "PaymentLink|Checkout|Booking|Webhook"`: **120 tests, 437 assertions, 4 failures** — exactos los 4 del baseline Culqi ya documentado (`CheckoutTest::test_payment_form_renders_with_items`, `test_process_payment_with_valid_token_creates_booking_and_charge`, `test_process_payment_with_failed_token_marks_booking_failed`, `test_booking_email_is_queued_after_success`). `tests/Feature/Seo`: **104 tests, 405 assertions, OK**. Cero regresiones nuevas.

## Punto 1 — Copiar enlace (detalle)

- **Vía 2 (botón "Copiar enlace" de la fila, desktop 1440px)**: clic real → toast `role="status"` con texto "Enlace copiado" confirmado (snapshot de `#lvt-copy-toast` inmediatamente tras el clic). Consola sin errores/warnings. `data-copy-text="http://lima-tour.test/pagar/qa3test001"` (URL completa correcta).
- **Vía 3 (campo "Enlace para el cliente" en Editar, desktop 1440px)**: clic real en botón "Copiar" → mismo toast "Enlace copiado" confirmado. Consola limpia. Value del textbox = URL completa correcta.
- **Vía 1 (celda "Enlace" de la tabla) — DEFECTO ALTA confirmado con clic real**: la celda es un `<a href="…/payment-links/5/edit">` que envuelve el `data-copy-text` (Filament pone la URL de edición como link por defecto de toda la fila). El listener global de `copy-fallback-script.blade.php` NO hace `preventDefault()`, así que el clic dispara el copy Y sigue el link: el navegador termina en la página de Editar (confirmado: la URL cambió a `/admin/payment-links/5/edit`, nunca se vio el toast). El admin nunca recibe confirmación de que copió y es expulsado del listado sin previo aviso. Archivo: `app/Filament/Resources/PaymentLinkResource.php:256-265` (columna `url`) + `resources/views/filament/partials/copy-fallback-script.blade.php:41-51` (falta `event.preventDefault()` cuando el trigger es o está dentro de un `<a>`/tiene href).
- **375px — NO VERIFICADO (clic real)**: el overlay `fi-sidebar-close-overlay`/`<aside>` de Filament queda con `$store.sidebar.isOpen=true` (heredado del estado en desktop, persistido) y cubre TODO el viewport bajo el breakpoint `lg`, incluida la topbar — bloquea cualquier clic (confirmado con Playwright: "aside subtree intercepts pointer events" en 3 intentos distintos, incluido el propio botón "Contraer barra lateral"). No tengo `browser_evaluate` en este rol para limpiar el store de Alpine/localStorage y forzarlo cerrado. Esto es un defecto de layout del panel Filament preexistente y AJENO al alcance de este fix (no lo tocó ni FIX-1 ni FIX-2) — lo señalo pero no lo persigo más porque no es del módulo de pagos. Sí confirmé por snapshot que el DOM a 375px trae el botón "Copiar" con el mismo `data-copy-text` correcto — solo el clic en vivo quedó sin probar.

## Limpieza de datos de prueba

IDs creados en esta sesión (todos con prefijo `qa3test*` o duplicados de ellos): **id 5** (`qa3test001`), **id 6** (`qa3test002-multiuso`, borrado durante la prueba del punto 7), **id 7** (`qa3test003-dup`), **id 8** (duplicado real vía `replicate()`, borrado durante la prueba del punto 7). Al cierre: `paypal_capture_id` de id 5 limpiado (era mi propio dato de prueba del punto 7) y borrado; id 7 borrado. Confirmado con tinker: `PaymentLink::where('code','like','qa3%')->count()` = **0**. Conteo de pendientes volvió a su línea base (1, el link `95uWp12Z...` preexistente ajeno a esta sesión, creado 12:49:08 antes de que empezara este QA).

## Gate de regresión SEO

1. Ninguna URL indexable quedó con noindex: PASS — solo `/pagar/{code}` lleva noindex (por diseño, correcto); home y ficha de tour normales (`index,follow`).
2. robots.txt de producción / no bloquea CSS-JS: NO VERIFICADO — el fix no tocó `robots.txt`, fuera del alcance de este lote (backend/Filament puro), no lo revisé.
3. Canonicals intactos: PARCIAL — no se agregó/quitó canonical en `layouts/app.blade.php` (solo se agregó `$forceNoindex`), home y tour siguen con robots normal; no inspeccioné el `<link rel="canonical">` puntualmente.
4. Slugs cambiados con 301: N/A — este lote no cambió slugs.
5. Un solo H1 / jerarquía: NO VERIFICADO — fuera del alcance verificado en esta pasada (cambio es backend, no tocó headings).
6. Datos estructurados: NO VERIFICADO — no se tocó JSON-LD en este lote.
7. Alcance completo si el fix es global: N/A — el fix es puntual a `/pagar/{code}` + `/admin/payment-links`, no global.

X-Robots-Tag `/pagar/{code}`: PASS (`noindex, nofollow`, header real vía curl). Meta robots: PASS (1 solo `<meta name="robots">`, sin duplicado — el defecto de meta duplicado del QA original quedó cerrado).

## Veredicto

**NO APTO.**

1 defecto ALTA confirmado con clic real: **vía 1 (celda de la tabla) de "Copiar enlace" navega a Editar en vez de copiar y confirmar** — regresión directa del ítem 6 del brief original (QA.md), que pedía las 3 vías funcionando. 2 de las 3 vías (botón de fila, campo del formulario) SÍ quedaron bien arregladas y verificadas con clic real + toast + consola limpia. Vuelve a **backend-laravel** (mismo archivo que tocó en FIX-1/FIX-2: `app/Filament/Resources/PaymentLinkResource.php` + `resources/views/filament/partials/copy-fallback-script.blade.php`) para agregar `event.preventDefault()`/`stopPropagation()` en el listener cuando el trigger tiene o está dentro de un elemento con `href`, o para quitarle el link de fila por defecto a esa columna específica.

Puntos 2 a 9: **PASS**, con evidencia real (BD, curl, tests, clics reales donde fue posible).

No verificado (declarado, no asumido): clic real de las 3 vías a 375px (bloqueado por un overlay de sidebar de Filament preexistente y ajeno a este fix); robots.txt de producción, canonical puntual, H1/jerarquía y datos estructurados (fuera del alcance tocado por este lote, no se revisaron en esta pasada). Portapapeles real: fuera de mi alcance, lo verifica el coordinador.
