# Pase 2026-08-30 — fix 404 al editar + 500 "Generar desde el título"

Incidente: el commit `7791956` (25/08, desplegado 26/08) dejó dos defectos vivos
de cara al cliente. Este pase los cierra.

| # | Defecto | Archivo culpable | Síntoma |
|---|---|---|---|
| 1 | 404 al editar | `TourResource:36`, `PageResource:25` | **Tours y Páginas** no se pueden editar |
| 2 | 500 en "Generar desde el título" | `HasEditableSlugField:305` | **Tours y Blog** (Páginas NO: tiene su propio campo de slug manual, sin ese botón) |

## Archivos a subir (4)

Ruta remota base: `/public_html/limaprogramacion/`
(⚠️ NO `/limaprogramacion/`, que es una copia stale a nivel home)

- `app/Filament/Concerns/RoutesRecordsByKey.php`   ← NUEVO
- `app/Filament/Concerns/HasEditableSlugField.php`
- `app/Filament/Resources/TourResource.php`
- `app/Filament/Resources/PageResource.php`

No lleva `npm run build`: no se tocó Tailwind, SCSS ni JS.
No lleva `filament:clear-cached-components`: se verificó por FTP que
`bootstrap/cache/` en producción solo tiene `packages.php` y `services.php`
(no existe `filament/panels/`).
No lleva migración: no hay cambios de esquema.

## Orden

1. Subir los 4 archivos (`curl -T`, un comando por archivo, empezando por `curl`).
2. Cotejar md5 remoto contra local. Si uno no coincide, resubir ese; no seguir.
3. Reset de opcache — OBLIGATORIO, prod tiene `validate_timestamps` off:
   `https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026`
   Debe responder `{"opcache":true}`.
4. Borrar vistas compiladas (`storage/framework/views/*.php`). Opcional aquí
   —no se tocó Blade— pero inofensivo.

## Verificación post-deploy (la parte que faltó el 26/08)

El checklist viejo solo miraba la web pública. Por eso este bug llegó al cliente.
Hay que entrar AL PANEL:

1. Login en `https://limaviewtours.com/admin`. El reCAPTCHA v2 lo resuelve
   Anyerson a mano (no se desactiva en producción).
2. **Tours** → clic real en **Editar** de un tour. La URL debe llevar el **id**
   y la ficha debe cargar. Es el camino exacto que usa el cliente.
3. **Páginas** → clic real en **Editar**. Mismo criterio. (No tiene el botón
   de la varita; no aplica el punto 5.)
4. Guardar un cambio intrascendente en uno de los dos y confirmar que persiste.
5. **Tours** y **Blog** → crear nuevo, escribir un título y pulsar
   **"Generar desde el título"**. No debe dar 500 y el slug debe rellenarse.
   Descartar el borrador después.
6. Sitio público: una ficha de tour responde 200.

⚠️ El punto 2 es el que Leo reportó, pero el 5 es el que NADIE probó el 26/08.
Los dos entran al checklist a partir de ahora.

## Rollback

Ver `COMO-REVERTIR.txt`. Los tres `.prod-antes` de esta carpeta son los archivos
tal como estaban vivos antes del pase (mtime original `Aug 26 07:01`).
`RoutesRecordsByKey.php` puede quedarse en el servidor: sin el `use` en los
Resources es inerte.

⚠️ Revertir devuelve el 404 y el 500. Solo si el pase rompe algo peor.
