#!/usr/bin/env bash
# ============================================================
# Deploy Lima View Tours -> PRODUCCIÓN
# Lote: Carrito abandonado + correos de reserva desde admin (2026-07-08)
# NO requiere npm build (el JS del checkout es inline en el Blade).
#
# USO (desde la raíz del repo):
#   FTP_USER='limaweb@limaviewtours.com' FTP_PASS='TU_PASS' bash deploy-carrito-abandonado.sh
#
# DESPUÉS (pasos manuales):
#   1) phpMyAdmin (BD limaview_limaprogramacion):
#      ejecutar database/scripts/create-abandoned-carts-prod.sql
#   2) Crear el cron en cPanel (ver README de la tarea).
# ============================================================
set -uo pipefail
: "${FTP_USER:?Define FTP_USER}"
: "${FTP_PASS:?Define FTP_PASS}"

HOST="ftp.limaviewtours.com"
REMOTE="/public_html/limaprogramacion"
LOCAL="$(cd "$(dirname "$0")" && pwd)"

up() { echo "  ↑ $1"; curl -s --ssl-no-revoke --ftp-create-dirs -T "$LOCAL/$1" \
        "ftp://$HOST$REMOTE/$1" --user "$FTP_USER:$FTP_PASS" || echo "    !! falló $1"; }

echo "== Vistas / Blade =="
for f in \
  resources/views/checkout.blade.php \
  resources/views/emails/carts/abandoned.blade.php
do up "$f"; done

echo "== PHP (services, mail, controllers, command, filament, config, rutas, migración) =="
for f in \
  app/Services/BookingNotifier.php \
  app/Services/AbandonedCartService.php \
  app/Services/CartService.php \
  app/Mail/AbandonedCartReminder.php \
  app/Http/Controllers/CartController.php \
  app/Http/Controllers/CheckoutController.php \
  app/Console/Kernel.php \
  app/Console/Commands/SendAbandonedCartReminders.php \
  app/Filament/Resources/AbandonedCartResource.php \
  app/Filament/Resources/AbandonedCartResource/Pages/ListAbandonedCarts.php \
  app/Filament/Resources/BookingResource.php \
  app/Filament/Resources/BookingResource/Pages/CreateBooking.php \
  config/cart.php \
  routes/web.php \
  database/migrations/2026_07_08_120000_create_abandoned_carts_table.php
do up "$f"; done

echo "== Traducciones (recover_success / recover_expired) =="
for f in lang/es/cart.php lang/en/cart.php lang/pt/cart.php
do up "$f"; done

echo "== Limpiando vistas compiladas en el server (obligatorio) =="
for v in $(curl -s --ssl-no-revoke --list-only "ftp://$HOST$REMOTE/storage/framework/views/" --user "$FTP_USER:$FTP_PASS" \
           | tr -d '\r' | grep -E '\.php$'); do
  curl -s --ssl-no-revoke "ftp://$HOST$REMOTE/storage/framework/views/" --user "$FTP_USER:$FTP_PASS" \
       -Q "DELE $REMOTE/storage/framework/views/$v" >/dev/null || true
done

echo "== Limpiando bootstrap/cache (config/services/packages/routes/events) =="
for c in config.php services.php packages.php routes-v7.php events.php; do
  curl -s --ssl-no-revoke "ftp://$HOST$REMOTE/bootstrap/cache/" --user "$FTP_USER:$FTP_PASS" \
       -Q "DELE $REMOTE/bootstrap/cache/$c" >/dev/null 2>&1 || true
done

echo ""
echo "✔ Deploy de archivos completo."
echo "➡  Falta:"
echo "   1) phpMyAdmin (BD limaview_limaprogramacion): database/scripts/create-abandoned-carts-prod.sql"
echo "   2) Crear el cron en cPanel."
