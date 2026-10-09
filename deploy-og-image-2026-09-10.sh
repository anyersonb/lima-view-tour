#!/usr/bin/env bash
set -u
HOST="${FTP_HOST:-ftp.limaviewtours.com}"
USER="${FTP_USER:-limaweb@limaviewtours.com}"
PASS="${FTP_PASS:?define FTP_PASS}"
BASE="/public_html/limaprogramacion"
CURL="curl -sS --ssl-no-revoke --ftp-create-dirs --connect-timeout 30 -u ${USER}:${PASS}"
cd "$(dirname "$0")"; OK=0; FAIL=0
put(){ if $CURL -T "$1" "ftp://${HOST}${BASE}/$1" >/dev/null 2>&1; then printf "  OK    %s\n" "$1"; OK=$((OK+1)); else printf "  FALLO %s\n" "$1"; FAIL=$((FAIL+1)); fi; }
del(){ $CURL -Q "DELE ${BASE}/$1" "ftp://${HOST}${BASE}/" >/dev/null 2>&1 && echo "  BORRADO $1" || echo "  (no existia) $1"; }
G="${1:-todo}"
if [ "$G" = "codigo" ] || [ "$G" = "todo" ]; then
echo "--- FIX OG:IMAGE ---"
for f in app/Models/Tour.php resources/views/tours/show.blade.php resources/views/layouts/app.blade.php; do put "$f"; done
echo "--- SCHEMAS / METAS SEO ---"
for f in app/Support/PageSeo.php app/Filament/Concerns/HasLocalizedSeoFields.php app/Filament/Pages/Settings.php app/Models/Setting.php resources/views/components/schema-raw.blade.php; do put "$f"; done
echo "--- SLUG EDITABLE ---"
for f in app/Filament/Concerns/HasEditableSlugField.php app/Filament/Concerns/RoutesRecordsByKey.php app/Filament/Resources/TourResource.php app/Filament/Resources/BlogPostResource.php app/Filament/Resources/PageResource.php; do put "$f"; done
echo "--- CHECKOUT / PAYPAL ---"
for f in app/Http/Controllers/CheckoutController.php app/Services/PayPalService.php app/Exceptions/PayPalCardDeclinedException.php resources/views/checkout.blade.php lang/es/booking.php lang/en/booking.php lang/pt/booking.php; do put "$f"; done
echo "--- VISTAS FRONT ---"
for f in resources/views/home.blade.php resources/views/tours/index.blade.php resources/views/blog/index.blade.php resources/views/blog/show.blade.php resources/views/about.blade.php resources/views/contact.blade.php resources/views/reviews.blade.php resources/views/pages/privacy.blade.php resources/views/pages/terms.blade.php; do put "$f"; done
echo "--- UTILIDADES DOCROOT ---"
for f in public/opcache-reset.php public/migrate-once.php; do put "$f"; done
fi
if [ "$G" = "build" ] || [ "$G" = "todo" ]; then
echo "--- BUILD ---"; put "public/build/manifest.json"; for a in public/build/assets/*; do put "$a"; done
fi
if [ "$G" = "borrados" ] || [ "$G" = "todo" ]; then
echo "--- BORRADOS ---"; del "resources/views/components/jsonld.blade.php"; del "resources/views/partials/faq-schema.blade.php"
fi
if [ "$G" = "caches" ]; then
echo "--- CACHES ---"
V=$($CURL "ftp://${HOST}${BASE}/storage/framework/views/" 2>/dev/null | awk '{print $NF}' | grep '\.php$' || true)
for v in $V; do $CURL -Q "DELE ${BASE}/storage/framework/views/${v}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1 && echo "  vista ${v}"; done
for c in packages.php services.php config.php events.php routes-v7.php; do $CURL -Q "DELE ${BASE}/bootstrap/cache/${c}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1 && echo "  borrado bootstrap/cache/${c}" || echo "  (no existia ${c})"; done
echo "Ahora: https://www.limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"; exit 0
fi
echo; echo "RESUMEN: ${OK} subidos, ${FAIL} fallidos"; [ "$FAIL" -eq 0 ] || exit 1
