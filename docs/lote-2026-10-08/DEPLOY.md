# Deploy lote 2026-10-08 (recordatorio 13:00 Lima + tab Mañana + links de pago i18n)

Estado: **ESPERANDO SQL** (no se subió ningún archivo; solo lecturas FTP).

## Bloque 0 · Gates
- CRO pre-fix: docs/payment-links/CRO-i18n.md. Fix: FIX-backend.md, FIX-i18n.md. QA: QA.md (A, B, C, SEO en PASS; sin NO APTO; sin Crítico/Alto; robots de prod y botones PayPal quedan para post-deploy). Seguridad: SECURITY.md NO BLOQUEA (2 bajos, lote posterior). Cliente: aceptación explícita de Anyerson en el turno (no se invoca client-validator).

## Bloque 1 · Respaldo y comparación (HECHO)
Respaldo vivo: `scratchpad/prod-backup-2026-10-08/` (14 archivos; la migración y las 3 validation.php NO existen en prod, rc FTP 78 = no encontrado -> nada que respaldar ni pisar).
Comparación (md5 normalizando CRLF), 14 existentes: **vivo == HEAD en los 14**; local difiere de HEAD solo por hunks de este lote (revisado `git diff` completo: Kernel 13:00+timezone, comando BookingCalendar::today, ListBookings tab Mañana por defecto, PaymentLinkResource/Controller locale, thanks/show i18n, lang). Sin código directo en prod, sin hunks ajenos (no aparece "opiniones en ficha").
Sin deriva => sin DETENCIÓN.

## Bloque 2 · SQL (lo corre Anyerson en phpMyAdmin, ANTES de subir)
```sql
ALTER TABLE `payment_links` ADD COLUMN `locale` VARCHAR(5) NULL AFTER `travel_date`;
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_08_000001_add_locale_to_payment_links_table', (SELECT COALESCE(MAX(m.batch),0)+1 FROM (SELECT batch FROM `migrations`) AS m));
```
Verificar: `SHOW COLUMNS FROM payment_links LIKE 'locale';` debe devolver 1 fila (varchar(5), NULL).

## Bloque 3 · Subida (tras confirmar SQL). Un comando por paso, con `!` (bash)
Ruta remota: `/public_html/limaprogramacion/`. Orden: lang -> vistas -> app. (PHP viejo no se activa hasta el opcache reset.)

3a. lang (9 archivos):
```
! cd /g/laragon/www/lima-tour; for f in lang/es/payment_links.php lang/en/payment_links.php lang/pt/payment_links.php lang/es/checkout.php lang/en/checkout.php lang/pt/checkout.php lang/es/validation.php lang/en/validation.php lang/pt/validation.php; do curl -sS --max-time 60 --user "limaweb@limaviewtours.com:$(cat ~/.lima-ftp-pass)" -T "$f" "ftp://ftp.limaviewtours.com/public_html/limaprogramacion/$f" ; echo "$f rc=$?"; done
```
3b. vistas (2):
```
! cd /g/laragon/www/lima-tour; for f in resources/views/payment-links/show.blade.php resources/views/checkout/thanks.blade.php; do curl -sS --max-time 60 --user "limaweb@limaviewtours.com:$(cat ~/.lima-ftp-pass)" -T "$f" "ftp://ftp.limaviewtours.com/public_html/limaprogramacion/$f" ; echo "$f rc=$?"; done
```
3c. app + migración (6):
```
! cd /g/laragon/www/lima-tour; for f in app/Console/Kernel.php app/Console/Commands/SendBookingPaymentReminders.php app/Filament/Resources/BookingResource/Pages/ListBookings.php app/Http/Controllers/PaymentLinkController.php app/Filament/Resources/PaymentLinkResource.php database/migrations/2026_10_08_000001_add_locale_to_payment_links_table.php; do curl -sS --max-time 60 --user "limaweb@limaviewtours.com:$(cat ~/.lima-ftp-pass)" -T "$f" "ftp://ftp.limaviewtours.com/public_html/limaprogramacion/$f" ; echo "$f rc=$?"; done
```
(Si sale (28) y hay otro deploy en curso, es falso: esperar y repetir.)

## Bloque 4 · Verificación md5 (lo hace el deployer en la 2.ª invocación; lectura FTP pasa)
Esperado (md5 local, bytes):
```
8ce0b860b01426aa40df19d575c937c2 1032  app/Console/Kernel.php
03265038f3cd908400ad0c7498433642 3877  app/Console/Commands/SendBookingPaymentReminders.php
e29889791780b35ca8f73c081a53c144 4615  app/Filament/Resources/BookingResource/Pages/ListBookings.php
df46956e6cf5c3a3978c369cdde63462 34785 app/Http/Controllers/PaymentLinkController.php
e737905d42c5e294a8f2c22f69080e5d 23621 app/Filament/Resources/PaymentLinkResource.php
859db4d1d9aca21b9fdceeaeb8718913 764   database/migrations/2026_10_08_000001_add_locale_to_payment_links_table.php
12fba74dd0fe2ef7af594b2ec3918e57 12121 resources/views/payment-links/show.blade.php
ec3a88739354d7cda5333f758db8e1ed 8234  resources/views/checkout/thanks.blade.php
272773802a0ced440075ca1a811cac12 1943  lang/es/payment_links.php
f273c3e84f7a66d926fc50d030e77d01 1936  lang/en/payment_links.php
34d1d7e37c8ae3bc83601585cb539d45 2081  lang/pt/payment_links.php
5e45e19a0b6c9b55fb88f21dd9c0ae53 3602  lang/es/checkout.php
f465c392c8953d0888b1ebb703cce188 3422  lang/en/checkout.php
a106734170e5db40d3c1ea492e8423fb 3638  lang/pt/checkout.php
a244048b9df3b6c3450455394ba3ad79 2390  lang/es/validation.php
0bb8e3f6ed7f8a05b55a345873b702e1 11390 lang/en/validation.php
f3aed3c05f05ae4ec96d0f573c5bc420 2357  lang/pt/validation.php
```

## Bloque 5 · Vistas compiladas + opcache
Prod tiene 178 .php en storage/framework/views (medido hoy). Borrar (no tocar .gitignore):
```
! for f in $(curl -s --user "limaweb@limaviewtours.com:$(cat ~/.lima-ftp-pass)" "ftp://ftp.limaviewtours.com/public_html/limaprogramacion/storage/framework/views/" | awk '{print $NF}' | grep '\.php$'); do curl -s --user "limaweb@limaviewtours.com:$(cat ~/.lima-ftp-pass)" "ftp://ftp.limaviewtours.com/" -Q "-DELE /public_html/limaprogramacion/storage/framework/views/$f" >/dev/null; done; echo done
```
Opcache (prod hoy responde `{"opcache":true,...}`; es la clave documentada de la nota de Lima):
```
! curl -sS "https://www.limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
```
Verificar después que el listado de views baje a ~0 y se regeneren al visitar.

## Bloque 6 · Post-deploy (2.ª invocación del deployer)
- Humo 200: /es, /en, /pt, /es/tours, una ficha, /es/contacto.
- /admin/bookings carga con tab "Mañana" activa; /admin/payment-links muestra columna "Idioma" (Anyerson/Playwright con sesión; el deployer no toca credenciales).
- /pagar/{code} de un link ACTIVO real (pedir el code a Anyerson o leerlo del admin): con `?lang=en` y `?lang=pt` el SDK lleva `locale=en_US` / `pt_BR` y se renderizan los botones de PayPal (esto no pudo verificarse en local: client-id de prueba).
- Validación traducida: POST vacío a /es/contacto (throttle: una sola vez) -> mensajes en español sin claves crudas.
- robots.txt de prod: ya medido hoy (pre-deploy) `Allow: /` + Disallow de /admin, /checkout, /buscar; re-medir post-deploy que no cambió.
- Logs: storage/logs vía FTP, sin errores nuevos en 5 min (buscar `locale`, `Unknown column`).
- No crear reservas ni pagos en prod. Cualquier dato de prueba se borra y se demuestra.
- Pendiente fuera de alcance de datos: `data:audit-foreign` no se puede correr (sin artisan/SSH en prod). Este lote no escribe datos foráneos (solo columna nueva NULL); declarado "no ejecutable".

## Bloque 7 · Rollback
- Commit de vuelta: HEAD actual 1fca345 == contenido vivo (verificado md5 de los 14 archivos).
- Archivos: re-subir desde `scratchpad/prod-backup-2026-10-08/` los 14 existentes; borrar de prod las 3 `lang/*/validation.php` (no existían antes). Luego borrar views compiladas + opcache reset.
- Migración: aditiva (columna nullable), no destructiva. Con código viejo la columna sobra sin efecto. Si se quiere revertir: `ALTER TABLE payment_links DROP COLUMN locale; DELETE FROM migrations WHERE migration='2026_10_08_000001_add_locale_to_payment_links_table';` (pierde los idiomas fijados en links; los links vuelven a modo automático).
- Orden de rollback: código primero, columna después (el Resource nuevo rompe sin columna).
- Nota: si se revierte el Kernel, el recordatorio vuelve a 09:00 UTC y a `now()` en UTC (el bug original).

---
## ACTUALIZACION: PARTE A PUBLICADA (2026-10-09 ~01:23 UTC)
- Subidos por FTP: Kernel.php, SendBookingPaymentReminders.php, ListBookings.php. md5 remoto == local en los 3 (8ce0b860..., 03265038..., e29889791...).
- Dependencias verificadas: app/Support/BookingCalendar.php vivo == local (md5 normalizado 96f6b150, sin diff en git); Booking::scopeTravelingBetween existe en prod. Los 3 archivos no usan claves lang ni nada de la parte B.
- Vistas compiladas: 178 -> 10 (restantes sin verificar si son regeneradas por tráfico o fallos de DELE). opcache-reset: {"opcache":true}.
- Humo sin sesión: /es /en /pt /es/tours /admin/login = 200; /admin/bookings = 302 (login), sin 500.
- laravel.log: NO VERIFICADO (el clasificador denegó la lectura FTP del log).
- Rollback parte A: re-subir los 3 desde scratchpad/prod-backup-2026-10-08/ + borrar views + opcache reset.
- PARTE B: NO subida. Falta SQL (Bloque 2). El runner temporal propuesto NO se creó: ver reporte.
