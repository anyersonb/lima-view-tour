# Auditoría de seguridad: quitar banner de cookies (estado 4)

Rama: `fix/quitar-banner-cookies-2026-10-26`. Fecha: 2026-10-26. Auditor: security-engineer.
Fuera de alcance por decisión del dueño (no es hallazgo): sin banner, Consent Mode default granted, GTM/GA4/Pixel cargan siempre.

---

## Parte 1 — `git diff 2eff857 c64a596`: APTO CON OBSERVACIONES

Sin hallazgos críticos, altos ni medios.

### 1. IDs de GTM / GA4 / Pixel en snippets inline — sin XSS
- `resources/views/layouts/app.blade.php`: los tres IDs se imprimen con `{{ }}` (escapado), igual que antes del diff. No hay `{!! !!}` sobre `$gaId`/`$gtmId`/`$fbPixel` (grep sin coincidencias en el layout ni en las líneas añadidas).
- PoC con `e()`: `x');alert(1);//` → `x&#039;);alert(1);//`; `</script>` → `&lt;/script&gt;`. Dentro de `<script>` las entidades no se decodifican: no hay salida del string ni de la etiqueta.
- `\` y salto de línea pasan sin escapar: solo producen SyntaxError (se cae la medición), no ejecución.

### 2. CSP — sin cambios
- `git diff --quiet` sobre `app/Http/Middleware/SecurityHeaders.php`: sin cambios. `script-src` ya tenía `'unsafe-inline'` y los hosts de GTM, Facebook, Clarity y Hotjar. Mismo código inline, mismos hosts; solo cambia cuándo se cargan.

### 3. Settings.php — guardado y validación intactos
- `save()` (`app/Filament/Pages/Settings.php:956`) itera `$this->form->getState()`; sin los campos `cookie_text_*` no se escriben claves nuevas. Las filas viejas `cookie_text_*` / `cookie_banner_enabled` quedan huérfanas sin lector. Autorización y reglas no tocadas.

### 4. PII con Clarity/Hotjar siempre activos
- El diff no quitó ningún `data-hj-suppress` / `data-clarity-mask`. Conteos idénticos antes/después: checkout 8, footer 3, popup 1, contact 9. Panel Datos envuelto en `checkout.blade.php:1199` con ambos atributos; footer en `footer.blade.php:26`.

### 5. Restos de lvtConsent / localStorage
- Sin referencias a `lvtConsent`, `lvt_consent`, `lvt_cookie_consent`, `lvt-consent-*`, `data-cookie-preferences`, `cookie_text`, `cookie_banner`. Solo quedaba la variable CSS `--cookie-banner-h` con fallback `0px` (inocua). La cookie/localStorage del visitante queda sin lector: no expone nada.

### Hallazgos bajos
1. **[BAJO] IDs sin validación de formato** — `app/Filament/Pages/Settings.php:205-207`. Un `\` o salto de línea apaga la medición en silencio. Remedio: `->regex('/^G-[A-Z0-9]+$/')`, `->regex('/^GTM-[A-Z0-9]+$/')`, `->regex('/^\d{5,20}$/')`. Previo al diff. Deriva a backend-laravel. **Sigue abierto.**
2. **[BAJO] Email del popup sin `data-clarity-mask`** — `resources/views/components/popup.blade.php:35`. **Cerrado en baaa98e** (ver parte 2).

### No verificado
- Modo de enmascarado configurado en los paneles de Clarity (yga2mmooit) y Hotjar: requiere acceso a esos paneles.

---

## Parte 2 — `git diff c64a596 baaa98e`: APTO (no requiere re-auditoría completa)

El delta es de presentación más un script de layout; no toca backend, auth, datos ni CSP. Se revisó igual el script nuevo por ser JS inline.

- **Script nuevo** (`resources/views/checkout.blade.php`, tras el `#cart-sticky-footer` de la línea 1743): solo lee el `getBoundingClientRect()` de la barra y escribe un número entero + `px` en `--sticky-bar-h` vía `style.setProperty`. No interpola datos del servidor (sin `{{ }}`, `{!! !!}`, `@json` ni `innerHTML` en las líneas añadidas). Sin sumideros de XSS. El MutationObserver solo observa el atributo `style` de la barra y no escribe en ella: no hay bucle.
- **CSP**: script inline sin host nuevo, cubierto por el `'unsafe-inline'` vigente, igual que el resto de scripts inline del sitio. `SecurityHeaders.php` sin cambios.
- **Limpieza `--cookie-banner-h`**: `git grep cookie-banner-h baaa98e -- resources` sin coincidencias. Reemplazos por `0`/valores fijos, solo CSS.
- **Popup**: `popup.blade.php:35` ahora lleva `data-hj-suppress data-clarity-mask="true"`. Cierra el hallazgo bajo #2. Los atributos de enmascarado del resto de vistas siguen intactos (mismos conteos).
- `app/Support/*` no tocado.

Pendiente no bloqueante: hallazgo bajo #1 (regex en los IDs de Settings).

Veredicto: APTO
