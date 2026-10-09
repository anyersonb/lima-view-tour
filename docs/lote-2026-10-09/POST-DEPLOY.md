# Post-deploy lote 2026-10-09

Deploy: 2026-10-09 03:01 UTC por FTP (14 archivos, md5 OK), vistas compiladas borradas, opcache reseteado.

## Humo público
- 200: /es, /en, /pt, /es/tours, /en/contact-us, /pt/contato, /admin/login, ficha de tour, /robots.txt
- 302: /admin/bookings, /admin/payment-links (sin sesión)
- 404: /pagar con código inexistente
- reCAPTCHA `hl=en` en /en y `hl=pt-BR` en /pt; aria-label del footer traducido
- laravel.log sin errores nuevos

## Verificación con sesión admin (2026-10-09 04:09 UTC)
- Tab "Mañana" primera y activa por defecto
- Botón "Reenviar recordatorio de pago" en la fila, junto a "Reenviar correo"; modal con correo editable (cancelado sin enviar)
- Columna toggleable "Recordatorio enviado" visible
- reCAPTCHA del login en español

## Reserva LVT-XS1UIBHC
El deploy terminó a las 22:01 Lima, fuera de la ventana del envío urgente (07:00–22:00), y el tour sale antes de las 07:00. El recordatorio se envió a mano desde el admin con el botón nuevo.

## Cron cPanel
- Cron principal cambiado a `cd ~/public_html/limaprogramacion && /usr/local/bin/php artisan schedule:run >> storage/logs/cron.log 2>&1`
- Verificado: `storage/logs/cron.log` creado a las 04:15 UTC ("No scheduled commands are ready to run", correcto fuera de ventana)
- Pendiente: borrar el cron duplicado (`php artisan` a secas, a /dev/null); cPanel dio error al borrarlo. Alternativa: editar su comando a `true`.

## Pendiente de seguimiento
- Leer `cron.log` después de las 07:15 y de las 13:00 Lima para confirmar envíos reales
- Bajas: 2 H1 en ficha de tour, "Reenviar correo" muestra error técnico, nombres de tour en pt, revisión nativa de validation.php
