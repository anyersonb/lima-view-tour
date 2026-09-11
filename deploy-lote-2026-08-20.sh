#!/usr/bin/env bash
# Deploy 2026-08-20 (+ lote D del 2026-08-21) — 4 lotes:
#   A) Slug + metas + JSON-LD por idioma (Tours, Paginas, Blog)
#   B) Filtros de la lista de Reservas + indices
#   C) Datos de contacto en Carrito Abandonado
#   D) URL por idioma de las paginas institucionales (/en/about-us, /pt/sobre-nos…),
#      301 canonico, hreflang reales, sitemap localizado, fix del @section null del
#      blog y fix de la cadena de 301 con slash final en public/.htaccess
#
# ORDEN OBLIGATORIO: migraciones -> runner (migrate-once) -> codigo -> limpiar caches.
# El codigo del lote A lee columnas nuevas; si sube antes que la migracion, revienta.
set -u
HOST="${FTP_HOST:-ftp.limaviewtours.com}"
USER="${FTP_USER:-limaweb@limaviewtours.com}"
PASS="${FTP_PASS:?define FTP_PASS}"
BASE="/public_html/limaprogramacion"
CURL="curl -sS --ssl-no-revoke --ftp-create-dirs --connect-timeout 30 -u ${USER}:${PASS}"
cd "$(dirname "$0")"
OK=0; FAIL=0
put () {
  local p="$1"
  if $CURL -T "$p" "ftp://${HOST}${BASE}/${p}" >/dev/null 2>&1; then
    printf "  OK    %s\n" "$p"; OK=$((OK+1))
  else
    printf "  FALLO %s\n" "$p"; FAIL=$((FAIL+1))
  fi
}
GRUPO="${1:-todo}"

if [ "$GRUPO" = "migraciones" ] || [ "$GRUPO" = "todo" ]; then
echo "--- MIGRACIONES ---"
put "database/migrations/2026_08_17_150000_add_i18n_seo_fields_to_tours_table.php"
put "database/migrations/2026_08_17_150100_add_i18n_seo_fields_to_pages_table.php"
put "database/migrations/2026_08_17_150200_add_i18n_slug_and_jsonld_to_blog_posts_table.php"
put "database/migrations/2026_08_19_000000_add_filter_indexes_to_bookings_table.php"
put "database/migrations/2026_08_20_023927_add_contact_indexes_to_abandoned_carts_table.php"
fi

if [ "$GRUPO" = "runner" ]; then
  echo "--- SCRIPT DE MIGRACION (borrar despues) ---"
  put "public/migrate-once.php"
fi

if [ "$GRUPO" = "codigo" ] || [ "$GRUPO" = "todo" ]; then
echo "--- LOTE A: SEO i18n ---"
put "app/Filament/Concerns/HasLocalizedSeoFields.php"
put "app/Models/Concerns/HasLocalizedSlug.php"
put "app/Models/Concerns/HasLocalizedSeoMeta.php"
put "app/Filament/Resources/TourResource.php"
put "app/Filament/Resources/PageResource.php"
put "app/Filament/Resources/BlogPostResource.php"
put "app/Models/Tour.php"
put "app/Models/Page.php"
put "app/Models/BlogPost.php"
put "app/Http/Controllers/TourController.php"
put "app/Http/Controllers/BlogController.php"
put "app/Http/Controllers/PageController.php"
put "app/Http/Controllers/ReviewController.php"
put "resources/views/layouts/app.blade.php"
put "resources/views/tours/show.blade.php"
put "resources/views/blog/show.blade.php"
put "resources/views/components/jsonld.blade.php"
put "resources/views/about.blade.php"
put "resources/views/contact.blade.php"
put "resources/views/reviews.blade.php"
put "resources/views/pages/terms.blade.php"
put "resources/views/pages/privacy.blade.php"
echo "--- LOTE B: Filtros de Reservas ---"
put "app/Filament/Resources/BookingResource.php"
put "app/Filament/Resources/BookingResource/Pages/ListBookings.php"
put "app/Models/Booking.php"
put "database/factories/BookingFactory.php"
put "database/factories/CategoryFactory.php"
echo "--- LOTE C: Carrito abandonado ---"
put "app/Filament/Resources/AbandonedCartResource.php"
put "app/Filament/Resources/AbandonedCartResource/Pages/ViewAbandonedCart.php"
put "app/Models/AbandonedCart.php"
echo "--- LOTE D: URL por idioma de las institucionales ---"
put "config/localized_pages.php"
put "app/Support/LocalizedPages.php"
put "app/Http/Middleware/CanonicalLocalizedPage.php"
put "app/Http/Kernel.php"
put "routes/web.php"
put "app/Http/Controllers/SitemapController.php"
put "resources/views/components/header.blade.php"
put "resources/views/components/footer.blade.php"
put "resources/views/components/cookie-banner.blade.php"
put "resources/views/checkout.blade.php"
put "resources/views/checkout/payment.blade.php"
put "public/.htaccess"
fi

if [ "$GRUPO" = "caches" ]; then
  # Paso OBLIGATORIO tras subir codigo: sin esto Laravel sigue sirviendo las
  # vistas compiladas viejas y los providers cacheados (sintoma clasico:
  # "Target class [translator] does not exist"). routes-v7.php se borra por si
  # produccion tiene la cache de rutas: el lote D agrega rutas nuevas y con la
  # cache vieja darian 404.
  echo "--- LIMPIANDO CACHES EN EL SERVIDOR ---"
  VIEWS=$($CURL "ftp://${HOST}${BASE}/storage/framework/views/" 2>/dev/null | awk '{print $NF}' | grep '\.php$' || true)
  for v in $VIEWS; do
    if $CURL -Q "DELE ${BASE}/storage/framework/views/${v}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      echo "  borrada vista ${v}"
    fi
  done
  for c in packages.php services.php config.php routes-v7.php events.php; do
    if $CURL -Q "DELE ${BASE}/bootstrap/cache/${c}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      echo "  borrado bootstrap/cache/${c}"
    else
      echo "  (no existia bootstrap/cache/${c})"
    fi
  done
  echo "Ahora: https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
  exit 0
fi

echo
echo "RESUMEN: ${OK} subidos, ${FAIL} fallidos"
[ "$FAIL" -eq 0 ] || exit 1
