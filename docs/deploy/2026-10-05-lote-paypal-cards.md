# Deploy Lima View Tours: lote PayPal + cards + Reservas + reseñas + filtros (2026-10-05)

Estado: PREPARADO. No se tocó producción. La subida la corre Anyerson con `!` en PowerShell 5.1.

## Autorización y excepciones (cita de Anyerson)

Autorización dada en la conversación principal: "subelo tu jefe", "despliega", "delega".
Eximió de forma explícita los gates de `cro-validator` y `client-validator`: él valida PayPal y Reservas en producción con credenciales reales.
Gates que SÍ constan: QA APTO (`docs/qa/2026-10-05-qa-lote-paypal-cards.md`); Seguridad APTO condicionado a subir el vendor con livewire 3.8.10 (`docs/security/2026-10-05-reauditoria-lote.md`).

## 1. Commits (rama `feat/pagos-fechas-doble-cobro-2026-08-28`, base c469f3a = prod actual)

| Hash | Título |
|---|---|
| 6711d5f | feat(checkout): PayPal card flow hardening and Reservas tabs |
| c95952f | feat(reviews): moderated traveler reviews, rating stats and Tripadvisor stamp |
| 378fc99 | feat(tours): compact tour cards and filters on /tours |
| 51adc8f | fix(security): safe upload names, remove SMTP diag route, livewire 3.8.10 |
| 939b82a | docs: QA, security and validation reports for the 2026-10-05 lote |

Archivos mezclados que no se separaron (hunks de lotes distintos en el mismo archivo), metidos en el lote de reseñas (el mayor):
- `lang/{es,en,pt}/ui.php`: claves de reseñas + claves de filtros de /tours (las del lote c viven en el commit c95952f).
- `app/Filament/Pages/Settings.php`: sello Tripadvisor + `safeImageNamer()` del hallazgo #5.
- `tests/Feature/PaypalPayerSanitizationTest.php` fue al commit de seguridad aunque ejercita código del commit de checkout; no importa para el deploy (se sube todo junto).

Nada con secretos: `deploy-og-image-2026-09-10.sh` y `scratchpad/` quedan SIN trackear (verificado con `git status`). Revisé `git diff --cached --stat` antes de cada commit y busqué patrones de credenciales en el lote de checkout (sin hallazgos). `.env*` y `CLAUDE.md` están en `.gitignore`.
Trailer de los commits: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. El encargo pedía "Claude Opus 5.5", pero la instrucción de atribución del entorno exige el modelo real que commitea; si Anyerson prefiere la otra línea, se corrige con rebase de mensajes (sin push, es barato).

## 2. Suite

BD de test confirmada antes de correr: `phpunit.xml` fija `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` (no hay `.env.testing`); además las exporté como prefijo del comando para que un `.env` con MySQL no pueda pisarlas. `lima_tours` no se tocó.

Comando: `php -d memory_limit=1G vendor/phpunit/phpunit/phpunit --no-progress` sobre HEAD 939b82a.
Resultado: **OK, 408 tests, 3151 aserciones, 0 fallos, 13 skipped** (8m45s). Los skips: 4 explícitos de Culqi desconectado en `CheckoutTest.php` ("decisión pendiente") y el resto de `ResourceEditUrlRouteKeyConsistencyTest` (recursos sin página edit, por diseño). No volví a correr con `--display-skipped` para listarlos uno a uno (otros 9 min): la atribución de los 13 es por lectura de `markTestSkipped`, no por salida de PHPUnit.

## 3. Build

Orden ejecutado: `view:cache` -> `npm run build` -> `view:clear`. Éxito (58 módulos, 12.8 s).
Assets nuevos (`public/build/manifest.json`):
- `public/build/assets/app-OJRVTS29.css` (168 kB)
- `public/build/assets/app-D-1sf1sN.js` (98 kB)

Ojo de medición: el build local previo ya tenía estos mismos nombres (el hash coincide), así que NO puedo saber por el local cuáles son los assets viejos de prod. El script `borrar-assets-viejos.ps1` los lista EN VIVO y borra todo salvo estos dos (con dry-run primero y copia de seguridad antes de cada DELE).

## 4. Lista de archivos a subir

Todo copiado además a `G:\laragon\www\deploy-lote-2026-10-05\` (fuera del repo; el scratchpad es de sesión). Ahí están los scripts y las listas.

- `lista-app.txt` (46): `git diff --name-only c469f3a..HEAD` sin `docs/` ni `tests/` (incluye `composer.lock`, la migración, `routes/web.php`, lang, vistas, `resources/js|scss`).
- `lista-assets.txt` (2) + `lista-manifest.txt` (1): build nuevo.
- `lista-vendor.txt` (3386 archivos): carpetas completas de los paquetes que cambian en `composer.lock` (c469f3a..HEAD):
  `livewire/livewire` 3.6.3->3.8.10; `league/mime-type-detection` 1.16->1.17; `symfony/{console, error-handler, http-foundation, http-kernel}` 6.4.22->6.4.47; `event-dispatcher` 6.4.13->6.4.44; `mime` y `string` 6.4.21->6.4.46; `var-dumper` 6.4.21->6.4.45; `deprecation-contracts`, `event-dispatcher-contracts`, `translation-contracts` 3.6.0->3.7.1; `service-contracts` 3.6.0->3.7.3; `polyfill-{ctype, intl-grapheme, intl-idn, intl-normalizer, mbstring, php80, php83}` 1.32->1.37..1.43. Cero paquetes nuevos ni eliminados.
- `vendor/composer/*` (12 archivos): ver la TRAMPA siguiente.
- `public/vendor/livewire`: NO existe en el repo (Livewire sirve su JS por ruta). Nada que subir.
- `bootstrap/cache/*`: sin cambios (no hay paquetes ni providers nuevos).

### TRAMPA: `vendor/composer` local contiene paquetes DEV
Verifiqué que el `vendor/composer/autoload_files.php` local carga archivos de `phpunit`, `mockery`, `deep-copy` y `collision`. Si prod tiene el vendor instalado con `--no-dev` (lo normal), subir esos archivos haría que `require` falle con fatal y TODO el sitio daría 500.
Resolución preparada: generé en `G:\laragon\www\deploy-lote-2026-10-05\vendor-composer-nodev\` el `vendor/composer` regenerado con `composer dump-autoload --no-dev -o --no-scripts` (0 apariciones de phpunit en `autoload_files.php` y `autoload_static.php`), y lo restauré en local (la suite de `tests/Unit` pasó después: 39/39). La copia dev queda en `vendor-composer-dev\`.
Decisión en el paso 2b: se mira el `autoload_files.php` VIVO que ya bajó el respaldo; si NO contiene `phpunit`, se sube `nodev`; si contiene `phpunit`, se sube `dev`. No verificado contra prod: no tengo acceso.
Efecto de no borrar: los archivos que una versión vieja tenía y la nueva ya no (p. ej. alguna clase suelta en symfony) quedan en prod. Es inocuo porque el classmap nuevo no los referencia.

### Assets viejos a borrar en prod
Se obtienen en vivo (ver arriba): `public/build/assets/*` salvo `app-OJRVTS29.css` y `app-D-1sf1sN.js`.

## 5. Migración de testimonials

Archivo: `database/migrations/2026_09_20_000000_add_moderation_and_details_to_testimonials_table.php`.
- Agrega 10 columnas, todas nullable o con default: `title_es`, `title_en`, `title_pt`, `traveler_type`, `photos` (json), `helpful_count` (default 0), `status` (default 'pending'), `review_date`, `submitter_email`, `locale`, más el índice `testimonials_tour_status_active_index (tour_id, status, is_active)`.
- Backfill: `UPDATE ... SET status='approved' WHERE is_active=1`. Solo toca lo ya visible; lo `is_active=0` queda `pending` (hallazgo #6).
- `down()` definido: `dropIndex` + `dropColumn` de las 10 columnas. Es destructivo si se ejecuta con datos nuevos (títulos, fotos, estado de moderación se pierden). Por eso el plan de rollback NO revierte el esquema.
- Riesgo para datos existentes: ninguno. No borra ni modifica columnas previas. Sobre MySQL el ALTER de una tabla pequeña es inmediato; los `after()` solo reordenan columnas.
- Compatibilidad con el código viejo (relevante si se hace rollback de archivos): el código de c469f3a no conoce las columnas nuevas; los INSERT viejos caen en `status='pending'` (lado seguro). Por eso la migración se corre ANTES de subir el código.
- No hay migraciones previas pendientes locales: `git diff c469f3a..HEAD` solo añade esta. El runner además aborta si en prod hay OTRA migración pendiente distinta (tras comprobarlo con el migrator), salvo `&force=1`.

Runner (patrón de `public/migrate-slug-redirects.php` del 26/08, mismo mecanismo; el último se corrió el 25/09 y ya está borrado): `G:\laragon\www\deploy-lote-2026-10-05\lvt-run-2026-10-05-testimonials.php` (lint PHP OK; NO ejecutado: correrlo en local habría tocado `lima_tours`).
- Token de 48 caracteres hex (en el propio archivo y en el comando 3b). Comparación con `hash_equals`; 404 sin token.
- Orden: guard (estado previo + pendientes) -> RESPALDO de `testimonials` a JSON (SELECT completo) en el home por encima de `public_html` (`dirname(__DIR__, 3)`, con fallback a `storage/app`; `chmod 600`; se relee y se compara el conteo ANTES de migrar, si falla aborta sin migrar) -> `migrate --force` -> verificación de las 10 columnas, índice y conteo de filas -> `view:clear` -> `route:clear`. Devuelve texto y termina con `RESULTADO: OK` o `RESULTADO: FALLO`.
- Imprime la ruta del respaldo y si cayó dentro de `public_html` (en ese caso moverlo/borrarlo).
- Ubicación remota: `/public_html/limaprogramacion/public/` (sirve la raíz como `https://www.limaviewtours.com/`).

## 6. Secuencia de comandos para Anyerson (PowerShell 5.1)

Cambio de orden respecto al encargo: la migración (3) va ANTES de subir vendor y código (4). Es aditiva, el código viejo la tolera y el código nuevo da 500 si falta la columna `status`. Todas las rutas remotas son `/public_html/limaprogramacion/`. Los `.ps1` solo leen la clave de `$env:LIMA_FTP_PASS`.
Nota para los comandos con `powershell -File`: ya están en una línea cada uno; no encajan en la regla "empieza por curl.exe" porque hacen bucles. Para app/assets individuales, ver el bloque alternativo al final de esta sección.

**0. Clave (en la misma ventana de PowerShell donde correrás todo)**
```
$env:LIMA_FTP_PASS = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR((Read-Host -AsSecureString "Clave FTP")))
```
(Si `!` no admite el prompt, escribirla a mano: `$env:LIMA_FTP_PASS = "la-clave"`.) La sesión de `!` no comparte entorno entre mensajes: si cada `!` abre un shell nuevo, repetir este paso en la misma línea con `;` antes de cada comando.

**1. Comprobar el docroot** (debe listar `Auth`, `Pages`, `Resources`)
```
curl.exe -sS --list-only -u "limaweb@limaviewtours.com:$env:LIMA_FTP_PASS" ftp://ftp.limaviewtours.com/public_html/limaprogramacion/app/Filament/
```

**2. Respaldo** de las versiones vivas (3447 archivos, tarda; destino `G:\laragon\www\deploy-backup-2026-10-05\`, fuera del repo; los archivos que aún no existen en prod se anotan en `backup-nuevos.txt`)
```
powershell -NoProfile -ExecutionPolicy Bypass -File G:\laragon\www\deploy-lote-2026-10-05\ftp-lote.ps1 -Modo Respaldo -ListFile G:\laragon\www\deploy-lote-2026-10-05\lista-respaldo.txt
```
Debe terminar en `SIN FALLOS`. Los "nuevos" esperados son los 2 assets, las 4 vistas/componentes nuevos, 3 lang `checkout_paypal`, `tour-filters.js`, `_tours.scss`, `partials/filter-groups` y la migración (≈13).

**2b. Decidir qué `vendor/composer` subir** (lee el `autoload_files.php` vivo ya respaldado)
```
findstr /c:"phpunit" G:\laragon\www\deploy-backup-2026-10-05\vendor\composer\autoload_files.php
```
- No imprime nada: prod es `--no-dev` -> usar `vendor-composer-nodev` (paso 4c, tal cual).
- Imprime una línea: prod tiene dev -> en 4c cambiar `vendor-composer-nodev` por `vendor-composer-dev`.
- Comparar además la versión mínima de PHP: `findstr /c:"PHP_VERSION_ID" G:\laragon\www\deploy-backup-2026-10-05\vendor\composer\platform_check.php` (el nuevo exige `>= 80102`; si el vivo exige algo MENOR y prod corre PHP < 8.1.2, parar).

**3. Migración con runner**
3a. Subir el runner
```
curl.exe -sS --ftp-create-dirs -T G:\laragon\www\deploy-lote-2026-10-05\lvt-run-2026-10-05-testimonials.php -u "limaweb@limaviewtours.com:$env:LIMA_FTP_PASS" ftp://ftp.limaviewtours.com/public_html/limaprogramacion/public/lvt-run-2026-10-05-testimonials.php
```
3b. Ejecutarlo (con `www`, sin `www` da 301 y NO ejecuta). Debe terminar en `RESULTADO: OK`; si dice FALLO o no imprime las 10 columnas, NO seguir.
```
curl.exe -sS -m 180 "https://www.limaviewtours.com/lvt-run-2026-10-05-testimonials.php?t=<token-runner-ya-borrado>"
```
3c. Borrarlo
```
curl.exe -sS -u "limaweb@limaviewtours.com:$env:LIMA_FTP_PASS" ftp://ftp.limaviewtours.com/ -Q "DELE public_html/limaprogramacion/public/lvt-run-2026-10-05-testimonials.php"
```
3d. Comprobar que ya no responde (esperado `404`)
```
curl.exe -s -o NUL -w "%{http_code}" "https://www.limaviewtours.com/lvt-run-2026-10-05-testimonials.php?t=<token-runner-ya-borrado>"
```
3e. Mover el respaldo JSON (`backup-testimonials-<fecha>.json` en el home, ver línea "Dentro de public_html" del runner) fuera del servidor y guardarlo en `G:\laragon\www\deploy-backup-2026-10-05\` si el runner lo dejó en `storage/app`.

**4. Subida** (el orden importa: assets, vendor, composer, código)
4a. Assets nuevos
```
powershell -NoProfile -ExecutionPolicy Bypass -File G:\laragon\www\deploy-lote-2026-10-05\ftp-lote.ps1 -Modo Subir -ListFile G:\laragon\www\deploy-lote-2026-10-05\lista-assets.txt
```
4b. Paquetes de vendor (3386 archivos)
```
powershell -NoProfile -ExecutionPolicy Bypass -File G:\laragon\www\deploy-lote-2026-10-05\ftp-lote.ps1 -Modo Subir -ListFile G:\laragon\www\deploy-lote-2026-10-05\lista-vendor.txt
```
4c. `vendor/composer` (variante según el paso 2b)
```
powershell -NoProfile -ExecutionPolicy Bypass -File G:\laragon\www\deploy-lote-2026-10-05\ftp-lote.ps1 -Modo Subir -ListFile G:\laragon\www\deploy-lote-2026-10-05\lista-composer.txt -LocalRoot G:\laragon\www\deploy-lote-2026-10-05\vendor-composer-nodev -RemotePrefix vendor/composer/
```
4d. Código de la app + `manifest.json` (47 archivos)
```
powershell -NoProfile -ExecutionPolicy Bypass -File G:\laragon\www\deploy-lote-2026-10-05\ftp-lote.ps1 -Modo Subir -ListFile G:\laragon\www\deploy-lote-2026-10-05\lista-codigo.txt
```
Cada uno debe cerrar con `SIN FALLOS`. Con fallos, resubir el mismo comando (idempotente) antes de seguir.

**5. Borrar assets viejos** (primero el simulacro, que lista los de prod y muestra qué borraría; la ejecución guarda cada archivo viejo en el respaldo antes del DELE)
```
powershell -NoProfile -ExecutionPolicy Bypass -File G:\laragon\www\deploy-lote-2026-10-05\borrar-assets-viejos.ps1
```
```
powershell -NoProfile -ExecutionPolicy Bypass -File G:\laragon\www\deploy-lote-2026-10-05\borrar-assets-viejos.ps1 -Ejecutar
```
Aborta sin borrar si los dos assets nuevos no están en prod.

**6. Reset de opcache** (prod tiene `validate_timestamps` off: sin esto no se ve NADA del PHP nuevo). Debe devolver JSON con `"reset": true`.
```
curl.exe -sS "https://www.limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
```

**7. Humo** (esperado 200; `/es/checkout/pago` con carrito vacío responde 302 a `/es/carrito` por diseño, `/es/checkout` es un 301 legacy al carrito)
```
curl.exe -s -o NUL -w "%{http_code}" https://www.limaviewtours.com/es
```
```
curl.exe -s -o NUL -w "%{http_code}" https://www.limaviewtours.com/es/tours
```
```
curl.exe -s -o NUL -w "%{http_code}" https://www.limaviewtours.com/es/tours/detalle/tour-privado-huacachina-islas-ballestas-atardecer-buggy-can-am
```
```
curl.exe -s -o NUL -w "%{http_code}" https://www.limaviewtours.com/es/carrito
```
```
curl.exe -s -o NUL -w "%{http_code}" https://www.limaviewtours.com/es/checkout/pago
```
```
curl.exe -s -o NUL -w "%{http_code}" https://www.limaviewtours.com/_diag/mail?key=lvt-mail-diag-2026
```
(El último es la prueba de que se quitó la ruta de diagnóstico SMTP: esperado 404.)
Además, a mano en el navegador: `/admin` (Reservas y Reseñas cargan) y el flujo PayPal real que Anyerson validará.

**8. Integridad** (re-descarga y compara hash con lo local)
```
curl.exe -sS --create-dirs -o G:\laragon\www\deploy-verify-2026-10-05\PayPalService.php -u "limaweb@limaviewtours.com:$env:LIMA_FTP_PASS" ftp://ftp.limaviewtours.com/public_html/limaprogramacion/app/Services/PayPalService.php
```
```
certutil -hashfile G:\laragon\www\deploy-verify-2026-10-05\PayPalService.php MD5
```
```
certutil -hashfile G:\laragon\www\lima-tour\app\Services\PayPalService.php MD5
```
```
curl.exe -sS --create-dirs -o G:\laragon\www\deploy-verify-2026-10-05\show.blade.php -u "limaweb@limaviewtours.com:$env:LIMA_FTP_PASS" ftp://ftp.limaviewtours.com/public_html/limaprogramacion/resources/views/tours/show.blade.php
```
```
certutil -hashfile G:\laragon\www\deploy-verify-2026-10-05\show.blade.php MD5
```
```
certutil -hashfile G:\laragon\www\lima-tour\resources\views\tours\show.blade.php MD5
```
```
curl.exe -sS --create-dirs -o G:\laragon\www\deploy-verify-2026-10-05\manifest.json -u "limaweb@limaviewtours.com:$env:LIMA_FTP_PASS" ftp://ftp.limaviewtours.com/public_html/limaprogramacion/public/build/manifest.json
```
```
certutil -hashfile G:\laragon\www\deploy-verify-2026-10-05\manifest.json MD5
```
```
certutil -hashfile G:\laragon\www\lima-tour\public\build\manifest.json MD5
```
```
curl.exe -sS --create-dirs -o G:\laragon\www\deploy-verify-2026-10-05\LivewireManager.php -u "limaweb@limaviewtours.com:$env:LIMA_FTP_PASS" ftp://ftp.limaviewtours.com/public_html/limaprogramacion/vendor/livewire/livewire/src/LivewireManager.php
```
```
certutil -hashfile G:\laragon\www\deploy-verify-2026-10-05\LivewireManager.php MD5
```
```
certutil -hashfile G:\laragon\www\lima-tour\vendor\livewire\livewire\src\LivewireManager.php MD5
```
Cada par de hashes debe ser idéntico. Si no, resubir ese archivo y reset de opcache.

### Bloque alternativo: un `curl.exe` por archivo (si prefieres no usar los `.ps1`)
Plantilla (una línea por archivo de `lista-codigo.txt` y `lista-assets.txt`; la ruta remota es la misma que la local con `/`):
```
curl.exe -sS --ftp-create-dirs -T G:\laragon\www\lima-tour\app\Services\PayPalService.php -u "limaweb@limaviewtours.com:$env:LIMA_FTP_PASS" ftp://ftp.limaviewtours.com/public_html/limaprogramacion/app/Services/PayPalService.php
```

## 7. ROLLBACK

El esquema NO se revierte (la migración es aditiva y el código viejo la tolera; el `down()` destruiría datos de moderación). Si hiciera falta volver los datos de `testimonials`, está el JSON del runner.

R1. Restaurar todo lo respaldado (re-sube las versiones vivas de antes, incluidos el `manifest.json` y los assets viejos que el paso 5 copió, y BORRA los archivos que no existían en prod: assets nuevos, vistas nuevas, etc.)
```
powershell -NoProfile -ExecutionPolicy Bypass -File G:\laragon\www\deploy-lote-2026-10-05\ftp-lote.ps1 -Modo Restaurar
```
R2. Reset de opcache
```
curl.exe -sS "https://www.limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
```
R3. Humo (repetir los comandos del paso 7; la ruta `/_diag/mail` volverá a existir con el código viejo, es esperable).

Notas: el código de c469f3a de vuelta + `vendor` viejo + `composer` viejo es exactamente el estado que había en prod. La restauración respeta que `backup-existentes.txt` se llena también con los assets viejos del paso 5; si se hace rollback ANTES del paso 5, los assets viejos nunca se borraron y siguen en prod.

## 8. Riesgos y qué NO está verificado

- No tengo acceso a prod: la decisión dev/no-dev de `vendor/composer` y la lista de assets viejos dependen de lo que revelen los pasos 2b y 5 (diseñados para ello).
- Los scripts `.ps1` pasaron el parser de PowerShell pero no se ejecutaron contra un FTP real (no hay clave). Primer síntoma de problema: `curl: (28)`/`(530)`; ver la nota de trampas FTP (límite de conexiones del Pure-FTPd si hay otro job activo; no abrir dos sesiones a la vez).
- El runner y los `.ps1` usan `DELE` con ruta relativa al home (`public_html/limaprogramacion/...`) porque `limaweb@` ve el home. Si el `DELE` del paso 3c responde error 550, probar con ruta absoluta `/public_html/...`.
- 13 skips de la suite: atribuidos por lectura de código, no por `--display-skipped`.
- Pendientes ya conocidos fuera de este lote (de la memoria del proyecto): webhook de PayPal de Leo y prueba de pago USD 1 real.

## 9. Archivos de apoyo (fuera del repo)

`G:\laragon\www\deploy-lote-2026-10-05\`: `ftp-lote.ps1`, `borrar-assets-viejos.ps1`, `lvt-run-2026-10-05-testimonials.php`, `lista-*.txt`, `vendor-composer-nodev\`, `vendor-composer-dev\`.
