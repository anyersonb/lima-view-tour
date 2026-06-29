#!/usr/bin/env bash
# ============================================================
# Deploy Lima View Tours -> STAGING (limaviewtours.com/limaprogramacion)
# Sube SOLO los archivos cambiados en este lote, borra el CSS viejo
# y limpia las vistas/caches compiladas en el servidor.
#
# USO (corre desde la raíz del repo, con tus credenciales FTP):
#   FTP_USER='limaweb@limaviewtours.com' FTP_PASS='TU_PASS' bash deploy-staging.sh
#
# Después: ejecuta database/scripts/migrate-pt-staging.sql en phpMyAdmin
# (BD limaview_limaprogramacion) para crear las columnas *_pt.
# ============================================================
set -uo pipefail
: "${FTP_USER:?Define FTP_USER}"
: "${FTP_PASS:?Define FTP_PASS}"

HOST="ftp.limaviewtours.com"
REMOTE="/public_html/limaprogramacion"
LOCAL="$(cd "$(dirname "$0")" && pwd)"
CSS="app-ClG2dQkz.css"   # <- hash del CSS actual (public/build/manifest.json)
JS="app-rlDHHCmG.js"     # <- hash del JS actual

up() { echo "  ↑ $1"; curl -s --ftp-create-dirs -T "$LOCAL/$1" \
        "ftp://$HOST$REMOTE/$1" --user "$FTP_USER:$FTP_PASS" || echo "    !! falló $1"; }

echo "== Subiendo Blade / vistas =="
for f in \
  resources/views/layouts/app.blade.php \
  resources/views/home.blade.php \
  resources/views/reviews.blade.php \
  resources/views/checkout.blade.php \
  resources/views/tours/index.blade.php \
  resources/views/tours/show.blade.php \
  resources/views/components/header.blade.php \
  resources/views/components/footer.blade.php \
  resources/views/components/lang-switcher.blade.php \
  resources/views/components/tour-card.blade.php \
  resources/views/components/jsonld.blade.php \
  resources/views/contact.blade.php \
  resources/views/emails/bookings/confirmed.blade.php \
  resources/views/emails/bookings/admin-notification.blade.php
do up "$f"; done

echo "== Subiendo PHP (controllers, models, filament, rutas, config) =="
for f in \
  app/Http/Controllers/CartController.php \
  app/Http/Controllers/CheckoutController.php \
  app/Http/Controllers/HomeController.php \
  app/Http/Controllers/ReviewController.php \
  app/Services/PayPalService.php \
  app/Services/GoogleReviewsService.php \
  app/Services/TripadvisorReviewsService.php \
  app/Filament/Pages/Settings.php \
  app/Filament/Pages/Maintenance.php \
  app/Mail/BookingConfirmed.php \
  app/Mail/BookingNotificationAdmin.php \
  config/services.php \
  app/Models/Tour.php \
  app/Models/Region.php \
  app/Models/Category.php \
  app/Models/Offer.php \
  app/Filament/Resources/TourResource.php \
  app/Filament/Resources/TestimonialResource.php \
  app/Filament/Resources/RegionResource.php \
  app/Filament/Resources/CategoryResource.php \
  app/Filament/Resources/PageResource.php \
  app/Filament/Resources/OfferResource.php \
  routes/web.php \
  config/app.php \
  database/migrations/2026_06_27_000001_add_pt_columns_to_all_tables.php
do up "$f"; done

echo "== Subiendo traducciones PT =="
for f in \
  lang/pt/nav.php lang/pt/seo.php lang/pt/cart.php lang/pt/checkout.php \
  lang/pt/common.php lang/pt/footer.php lang/pt/legal.php lang/pt.json
do up "$f"; done

echo "== Subiendo assets compilados =="
up "public/build/assets/$CSS"
up "public/build/assets/$JS"
up "public/build/manifest.json"

echo "== Borrando CSS/JS viejos en el server =="
for old in $(curl -s --list-only "ftp://$HOST$REMOTE/public/build/assets/" --user "$FTP_USER:$FTP_PASS" \
             | tr -d '\r' | grep -E '\.(css|js)$' | grep -vE "^($CSS|$JS)$"); do
  echo "  ✗ $old"
  curl -s "ftp://$HOST$REMOTE/public/build/assets/" --user "$FTP_USER:$FTP_PASS" \
       -Q "DELE $REMOTE/public/build/assets/$old" >/dev/null || true
done

echo "== Limpiando vistas compiladas en el server (paso obligatorio) =="
for v in $(curl -s --list-only "ftp://$HOST$REMOTE/storage/framework/views/" --user "$FTP_USER:$FTP_PASS" \
           | tr -d '\r' | grep -E '\.php$'); do
  curl -s "ftp://$HOST$REMOTE/storage/framework/views/" --user "$FTP_USER:$FTP_PASS" \
       -Q "DELE $REMOTE/storage/framework/views/$v" >/dev/null || true
done

echo "== Limpiando bootstrap/cache (config/services/packages/routes) =="
for c in config.php services.php packages.php routes-v7.php; do
  curl -s "ftp://$HOST$REMOTE/bootstrap/cache/" --user "$FTP_USER:$FTP_PASS" \
       -Q "DELE $REMOTE/bootstrap/cache/$c" >/dev/null 2>&1 || true
done

echo ""
echo "✔ Deploy de archivos completo."
echo "➡  Falta 1 paso manual: ejecuta en phpMyAdmin (BD limaview_limaprogramacion):"
echo "    database/scripts/migrate-pt-staging.sql"
echo "   Verifica luego: https://limaviewtours.com/limaprogramacion/es?fresh=1"
