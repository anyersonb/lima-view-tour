# Re-auditoría de seguridad: lote PayPal + reseñas + filtros (2026-10-05)

Rama `feat/pagos-fechas-doble-cobro-2026-08-28`, HEAD `c469f3a`, working tree SIN COMMITEAR. Solo lectura. Base: `docs/security/2026-09-30-lote-paypal-resenas.md` (NO APTO).

## Veredicto: APTO (condicionado al deploy de vendor)

El lote no deja abierto ningún hallazgo Crítico ni Alto. La única condición bloqueante es de despliegue: producción tiene que salir con el `vendor` de este `composer.lock` (livewire 3.8.10). Si producción se queda en livewire <3.6.4, el CVE-2025-54068 (RCE sin autenticar) sería Crítico. Esa versión en producción no está verificada.

## Estado por punto
1. Hallazgos del 30/09: HECHO
2. Diff nuevo: HECHO
3. Secretos: HECHO
4. composer audit: HECHO. Pendiente: separar lo que trajo este lote de lo que ya existía. No pude diffear las versiones del lock contra HEAD.

## 1 · Hallazgos del 30/09

| # | Estado | Evidencia |
|---|---|---|
| 1 Payer rechazado por PayPal → 500 | CERRADO | `CheckoutController.php:293-335`: surname truncado a 140, correo validado con FILTER_VALIDATE_EMAIL y ≤254, teléfono 8-14 dígitos. Si PayPal responde 4xx sobre `payer`, `PayPalService.php:152-162` reintenta una sola vez sin `payer`. Queda un residual Bajo (ver B1). |
| 2 PII en logs | CERRADO | `PayPalService.php` ya no tiene ningún `->body()` (verificado con grep). `safeErrorSummary()` (~:230-250) solo devuelve name, debug_id y details[].field/issue. |
| 3 `application_context` deprecado | ABIERTO (funcional, no de seguridad) | No se migró, de forma deliberada (ver `docs/fixes/2026-10-05-backend-paypal-todas.md`). No verificado en sandbox. |
| 4 `tour_language` descartado | NO REVISADO (no es de seguridad) | Le toca a backend-laravel. |
| 5 Subida de `.php` en photos | CERRADO | `TestimonialResource.php:113-137` + `ImageOptimizer.php:82-85` (`Str::ulid()` + extensión sacada de un mapa cerrado por MIME detectado). La regla (~:270-283) valida ahora `$value` directamente. PoC propia con bytes reales y `runningUnitTests=false`: `shell.php` (PNG con PHP anexado) se guarda como `.png`, el SVG, el `.php` puro y el HTML como `.bin`, y `ok.png` como `.png`. La regla rechaza `shell.php` y `b.PHTML` y acepta `.png` y `.jpg`. Ojo: la suite del repo usa `UploadedFile::fake()`, que con `getMimeType()` en modo test lee el nombre del archivo. Por eso la evidencia que vale es la PoC, no la suite. |
| 6 Backfill sin `where` | CERRADO | En la migración `2026_09_20_000000_...php:44`: `->where('is_active', true)->update(['status'=>'approved'])`. Lo demás queda en el default `pending`. |
| 7 Reseñas sin captcha | ABIERTO (Bajo, ya existía) | El diff no lo toca. |
| 8 `rememberForever` de reviewStats | ABIERTO (Bajo, integridad) | El diff no lo toca. |
| 9 Token de opcache-reset en docs | ABIERTO (Bajo) | `docs/seo/RESPUESTA-ESPASEO-2026-08-21.md:208` y `public/opcache-reset.php:5` (trackeado, token estático `lvt-mail-diag-2026`, el mismo que tenía el diag). |
| 10 `/_diag/mail` | CERRADO en el código | La ruta se eliminó de `routes/web.php` (en el diff aparece entera en `-`). Sigue viva en producción hasta el deploy. |

## 2 · Diff nuevo

- **El importe sale 100% del servidor:** `CheckoutController.php:242` (`$this->cart->total()`), con moneda fija `'USD'`. El reintento (`PayPalService.php:152-162`) solo hace `unset($payload['payer'])` sobre el MISMO `$payload` ya armado: no puede cambiar importe, moneda ni ítems. El snapshot (`:256-260`) se guarda después con `$total`, así que la captura sigue verificando contra él. OK.
- **XSS en checkout:** las alertas nuevas usan `@json(__('...'))` dentro de `<script>`. `@json` aplica HEX_TAG/APOS/QUOT/AMP, así que no hay salida del script. Los `innerHTML` del diff son `= ''`. OK.
- **XSS `{{ $attributes }}`** (`tour-card.blade.php:52`, `tour-card-row.blade.php:56`): los valores llegan desde `tours/index.blade.php:402-407,435-440` como `data-*="{{ }}"`. Blade los escapa al compilar el atributo del componente (sanitizeComponentAttribute), las claves son literales de la plantilla y los datos son del servidor/admin. OK.
- **tour-filters.js:** no tiene sinks HTML. Solo hace `JSON.parse` del atributo `data-i18n` (`@json`). OK.
- **ListBookings:** solo cambia fechas del servidor y añade el badge `count()`. No hay input del usuario. OK.
- **Inyección / mass assignment:** no hay SQL crudo ni `create($request->all())` nuevos. `buildPayer()` arma el array de forma explícita.
- **CSRF:** el diff no añade rutas nuevas (solo quita `/_diag/mail`). Los endpoints de PayPal siguen bajo `web`.

Hallazgos del diff:
- **B1 [BAJO · funcional] La validación propia sigue pudiendo dejar al cliente sin pagar.** `CheckoutController.php:221-226`: `customer_phone` con regex `^\+?\d{7,15}$`. `#phone_local` no tiene `maxlength` (`checkout.blade.php:~1215`), así que con 16 dígitos create-order devuelve 422. El JS (`checkout.blade.php:2325`) solo muestra `errors.accept_terms` y cae en el genérico "No se pudo crear la orden." Remedio: aplicar la misma política que buildPayer (campo inválido = se omite, no 422) o mostrar `errors.customer_phone`, y añadir `maxlength` en el input. → backend-laravel (regla) + maquetador-frontend (maxlength y mensaje).
- **B2 [BAJO · A09, ya existía] El correo del cliente en logs operativos:** `CheckoutController.php:176`, `:557`, `:725`. Está justificado para la conciliación manual de cobros. Recomendado: reemplazarlo por `customer_id` o un hash. → backend-laravel (otro lote).

## 3 · Secretos
- `deploy-og-image-2026-09-10.sh:3-5`: la clave sale de `${FTP_PASS:?}` (no hay secreto en el archivo). En los defaults quedan el host y el usuario FTP. No debería ir al repo: o añadirlo a `.gitignore` como `deploy-*.sh` o no stagearlo.
- `scratchpad/lima-qa-mask-pii.md`: no contiene credenciales. Es un artefacto de trabajo y debería añadirse `scratchpad/` a `.gitignore`.
- `docs/fixes`, `docs/qa`, `docs/security` nuevos: grep de pass/secret/token/api key/client_id/BEGIN/correos sin resultados sensibles. Solo aparecen `PAYPAL_CLIENT_ID=QA_STUB_CLIENT` (un stub) y el teléfono de prueba `+51987654321`. Las capturas de `docs/fixes/img/*.png` son de páginas públicas. NO las inspeccioné visualmente.
- `.gitignore` no cubre `scratchpad/` ni `deploy-*.sh` (solo uno, por nombre).

## 4 · composer audit (lock actual, CA de Avast)
49 avisos en 14 paquetes, ninguno de severidad crítica. livewire 3.8.10 no aparece (CVE-2025-54068 cerrado en el lock).

Altos relevantes, que vienen de antes (el lote solo subió livewire/Symfony):
- `laravel/framework`: CRLF en la regla `email` por defecto. Afecta a `customer_email` en checkout, contacto y reseñas.
- `filament/forms`: XSS con el estado de un RichEditor deshabilitado. Hay RichEditor en `BlogPostResource.php:49,90,130`. Requiere panel.
- `guzzlehttp/guzzle` (host no canónico), `league/commonmark` (DoS y XSS de AttributesExtension).
- Medio a vigilar: `filament/filament` "Unauthenticated temporary file upload on auth pages" (cruza con el riesgo de que `/storage/` ejecute PHP).

Remedio: lote aparte de `composer update` con laravel/framework, filament/*, guzzlehttp/*, league/commonmark y symfony/*, más la suite. → backend-laravel. No lo bloqueo en este lote porque es preexistente, pero es prioritario.

## No verificado
Versión de livewire en producción; que `/storage/` ejecute PHP en producción; PayPal sandbox (reintento real); qué avisos de composer trajo este lote frente a los que ya existían; `npm audit`.
