# QA post-fix — Hotfix blog 500 (title_en NOT NULL)

Fecha: 2026-09-25
Entorno: local (`http://lima-tour.test`), MySQL `lima_tours`, rama `feat/pagos-fechas-doble-cobro-2026-08-28`.

## Veredicto
EN CURSO — se completa al cerrar todos los puntos. No cerrar como APTO/NO APTO hasta la sección "Veredicto final".

## Estado por punto
1. Migración MySQL — HECHO
2. Clic real crear nota solo ES + /en + /pt — HECHO
3. Regresión (3 idiomas + edición) — PENDIENTE
4. Gate SEO del blog — PENDIENTE
5. Tests `--filter "BlogPost|Blog"` — PENDIENTE
6. Limpieza de notas de prueba — PENDIENTE

---

## 1. Migración en MySQL real — HECHO

Precondición: `php artisan migrate:status` mostraba `2026_09_24_000000_make_blog_posts_i18n_columns_nullable` como `Pending`, y también `2026_09_24_000001_create_payment_links_table` (de otro agente) como `Pending`.

Estado de columnas ANTES (via `SHOW COLUMNS FROM blog_posts`, mysql-8.0.30-winx64):
```
title_en    varchar(255)  NO
excerpt_en  text          NO
body_en     longtext      NO
title_pt    varchar(255)  NO
excerpt_pt  text          NO
body_pt     longtext      NO
```
Las 6 en NOT NULL, confirma el bug reportado.

Comando ejecutado (con `--path` para NO tocar la migración ajena de payment_links):
```
php artisan migrate --path=database/migrations/2026_09_24_000000_make_blog_posts_i18n_columns_nullable.php --force
```
Salida: `2026_09_24_000000_make_blog_posts_i18n_columns_nullable ... DONE` (batch 20).

Verificación post-migración:
- `php artisan migrate:status`: la migración del blog quedó `Ran` (batch 20); `2026_09_24_000001_create_payment_links_table` sigue `Pending` — NO se corrió, como se pidió.
- `SHOW TABLES LIKE 'payment_links'` → vacío, la tabla no existe. Confirma que no se tocó nada de PaymentLink.
- `SHOW COLUMNS FROM blog_posts` DESPUÉS:
```
title_en    varchar(255)  YES
excerpt_en  text          YES
body_en     longtext      YES
title_pt    varchar(255)  YES
excerpt_pt  text          YES
body_pt     longtext      YES
```
Las 6 columnas quedaron `YES` (nullable) y conservan exactamente su tipo original (varchar(255), text, longtext) — sin cambio de tipo, solo la nulabilidad, tal como especifica la migración con `->change()`.

---

## 2. Clic real en el panel — PARCIAL

### Reproducción del bug ANTES del fix (mismo flujo del reporte)
- Login real en `/admin/login` con `admin@limaviewtours.com` (seeder `DatabaseSeeder`). OK, entra a `/admin`.
- Nota: bajo ~1024px el clic en "Crear" no conectaba (overlay del sidebar de Filament interceptando puntero, patrón ya conocido). Se fijó el viewport a 1440x900 y el clic funcionó con normalidad.
- Navegación a `/admin/blog-posts/create`, llenada SOLO la pestaña Español (título, extracto, cuerpo) vía Playwright, clic real en botón "Crear".
- Resultado: consola registró `[ERROR] Failed to load resource: the server responded with a status of 500 (Internal Server Error) @ http://lima-tour.test/livewire/update`.
- Confirmado en `storage/logs/laravel.log` (línea 44658, timestamp 2026-09-25 01:19:26):
```
SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'title_en' cannot be null (Connection: mysql, SQL: insert into `blog_posts` (...) values (QA Reproduccion Bug Solo Espanol, ...))
```
Coincide EXACTO con el bug del reporte original (mismo mensaje, misma columna, mismo flujo de UI).

### Verificación DESPUÉS del fix (clic real, mismo flujo)
- Nota nueva: título "QA Post Fix Guia Rapida Solo Espanol", solo pestaña Español llenada (título, extracto, cuerpo), toggle "Publicado" activado, resto de pestañas (English/Português) vacías.
- Clic real en "Crear" → la app redirige a `/admin/blog-posts/3/edit` (guardado exitoso, sin 500). Consola del navegador: 0 errores.
- Confirmado en BD (`SELECT id, slug, title_es, title_en, title_pt, is_published FROM blog_posts WHERE id=3`): `title_en = NULL`, `title_pt = NULL`, `is_published = 1`. Es decir, la nota se persiste con NULL real en las columnas EN/PT, tal como predice el fix.
- Front público, las 3 rutas devuelven 200 (no 404, no 500, no vacío):
  - `http://lima-tour.test/es/blog/qa-post-fix-guia-rapida-solo-espanol` → 200
  - `http://lima-tour.test/en/blog/qa-post-fix-guia-rapida-solo-espanol` → 200
  - `http://lima-tour.test/pt/blog/qa-post-fix-guia-rapida-solo-espanol` → 200
- En las 3 rutas: `<title>`, meta description y `<h1>` (único, confirmado con `grep -c "<h1"` = 1 en cada una) muestran el fallback en español ("QA Post Fix Guia Rapida Solo Espanol" / "Extracto QA post-fix en espanol..."), tal como implementan los accessors `getTitleAttribute`/`getMetaTitleAttribute`/`getMetaDescriptionAttribute` con `?:`.
- Sin `noindex` en meta robots ni en cabecera `X-Robots-Tag` (`curl -sI` a ES y EN, ambos `HTTP/1.1 200 OK` sin esa cabecera).
- `hreflang`: las 4 etiquetas (`es`, `en`, `pt`, `x-default`) presentes y correctas en la página ES, apuntando a las 3 URLs reales (no a staging).
- `canonical` de cada página apunta a sí misma (self-referencing), no cruzado ni a staging.
- JSON-LD: un bloque `application/ld+json` por página, parsea sin error en las 3 (validado con `JSON.parse` en Node) — no se rompió por los campos NULL.

No verificado en este paso: contenido visual/CSS de la ficha del blog (fuera de alcance del bug; el fix es de datos, no de maquetado).

---

*(Este archivo se actualiza en los siguientes pasos según se completen los puntos 2 (resto), 5, 3, 4 y 6, en ese orden de prioridad indicado por el coordinador.)*
