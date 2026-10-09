# FIX frontend i18n (lote 2026-10-09)

## 1 reCAPTCHA hl segun locale
Mapa: es->es, en->en, pt->pt-BR, otro->es (`app()->getLocale()`). Claves, version y validacion intactas.
- resources/views/partials/recaptcha.blade.php:20 define `$rcHl`; :31 v2 `api.js?hl=`; :45 v3 `api.js?render=KEY&hl=`
  (este partial cubre contacto, newsletter del footer, login y registro de cliente).
- resources/views/filament/auth/recaptcha-field.blade.php:10 define `$rcHl`; :42 (v2) y :79 (v3) anaden `&hl=`.
  Login admin: usa el locale de la app (es por defecto).
- Checkout no carga reCAPTCHA (grep sin resultados).
- Nota: el codigo previo no tenia `hl=es` fijo; no enviaba hl y Google elegia por navegador. Ahora es explicito.

## 2 aria-label fijos en espanol (footer)
- resources/views/components/footer.blade.php:53 `aria-label="Lima View Tours — {{ __('nav.home') }}"` (existe en lang es/en/pt nav.php:10; Inicio/Home/Inicio con tilde pt "Início").
- footer.blade.php:112 `aria-label="{{ __('footer.methods_of_payment') }}"` (clave ya existente en lang/*/footer.php:17).
- header.blade.php:43 ya usaba `__('nav.home')`; header:151 y alt del logo "Lima View Tours" son marca, no se traducen.
- No se anadieron claves nuevas a lang.

## Verificacion curl (php artisan serve :8765, ya detenido)
Aria-labels (sin claves crudas):
- /es: `Lima View Tours — Inicio` x2, `Métodos de pago`
- /en y /en/contact-us: `Lima View Tours — Home` x2, `Methods of payment`
- /pt y /pt/contato: `Lima View Tours — Início` x2, `Métodos de pagamento`

reCAPTCHA (local lo tenia apagado: se activo con Setting::set clave falsa TESTKEY y se RESTAURO: enabled=0, site_key vacio, version v3, Cache::flush):
- v3: /es/contacto `?render=TESTKEY&hl=es`; /en/contact-us `&hl=en`; /pt/contato `&hl=pt-BR`
- v2: /es/contacto `api.js?hl=es`; /en/contact-us `?hl=en`; /pt/contato `?hl=pt-BR`

## No verificado
- Login admin (`/admin/login`): el curl no devolvio coincidencias del script (va dentro de un atributo x-data / carga por JS). Edicion revisada por lectura, no renderizada. Probar en navegador.
- Que Google sirva realmente el widget en pt-BR: no probado (sin clave real ni navegador).

## Archivos tocados
- resources/views/partials/recaptcha.blade.php
- resources/views/filament/auth/recaptcha-field.blade.php
- resources/views/components/footer.blade.php
- docs/lote-2026-10-09/FIX-frontend.md
