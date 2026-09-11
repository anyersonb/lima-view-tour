#!/usr/bin/env bash
# Deploy 2026-07-19 — 4 entregables:
#   1) Reseñas: cards de comentarios uniformes (clamp + ver más)
#   2) Reseñas: sistema propio de comentarios (quita widget TripAdvisor)
#   3) Home: sección única "Imágenes del Home" + optimización WebP
#   4) Tour: bloque comparativo (convencional VS premium) editable por tour
#
# Credenciales por variables de entorno (no se guardan en el archivo).
# Uso:
#   FTP_HOST=ftp.limaviewtours.com \
#   FTP_USER='limaweb@limaviewtours.com' \
#   FTP_PASS='limaweb@limaviewtours.com' \
#   bash deploy-resenas-comparativa-2026-07-19.sh
set -u

HOST="${FTP_HOST:?define FTP_HOST}"
USER="${FTP_USER:?define FTP_USER}"
PASS="${FTP_PASS:?define FTP_PASS}"
# ⚠️ Ruta correcta del deploy (según último deploy 2026-07-16): /public_html/limaprogramacion
BASE="/public_html/limaprogramacion"
CURL="curl -sS --ssl-no-revoke --ftp-create-dirs --connect-timeout 30 -u ${USER}:${PASS}"

cd "$(dirname "$0")"

put () {
  local local_path="$1" remote_path="$2"
  echo ">> $remote_path"
  $CURL -T "$local_path" "ftp://${HOST}${remote_path}" && echo "   OK" || echo "   FALLO"
}

# --- PHP backend ---
put "app/Models/Tour.php"                              "${BASE}/app/Models/Tour.php"
put "app/Http/Controllers/ReviewController.php"        "${BASE}/app/Http/Controllers/ReviewController.php"
put "app/Filament/Resources/TourResource.php"          "${BASE}/app/Filament/Resources/TourResource.php"
put "app/Filament/Pages/Settings.php"                  "${BASE}/app/Filament/Pages/Settings.php"
put "routes/web.php"                                   "${BASE}/routes/web.php"
put "database/migrations/2026_07_18_100000_add_comparison_to_tours_table.php" "${BASE}/database/migrations/2026_07_18_100000_add_comparison_to_tours_table.php"

# --- Vistas Blade ---
put "resources/views/reviews.blade.php"                "${BASE}/resources/views/reviews.blade.php"
put "resources/views/tours/show.blade.php"             "${BASE}/resources/views/tours/show.blade.php"
put "resources/views/components/tour-comparison.blade.php" "${BASE}/resources/views/components/tour-comparison.blade.php"

# --- Assets compilados (nuevos hashes) + manifest ---
put "public/build/assets/app-J77VOzPK.css"             "${BASE}/public/build/assets/app-J77VOzPK.css"
put "public/build/assets/app-rlDHHCmG.js"              "${BASE}/public/build/assets/app-rlDHHCmG.js"
put "public/build/manifest.json"                       "${BASE}/public/build/manifest.json"

# --- Limpiar caches en servidor ---
echo ">> Limpiando bootstrap/cache/{routes,config}.php"
$CURL -Q "-DELE ${BASE}/bootstrap/cache/routes.php" "ftp://${HOST}/" 2>/dev/null && echo "   routes.php borrado" || echo "   (routes.php no existía)"
$CURL -Q "-DELE ${BASE}/bootstrap/cache/config.php" "ftp://${HOST}/" 2>/dev/null && echo "   config.php borrado" || echo "   (config.php no existía)"

echo ""
echo "=== PASOS MANUALES EN SERVIDOR ==="
echo "1) phpMyAdmin (DB limaview_limaprogramacion): ejecutar deploy-2026-07-19.sql"
echo "2) Refrescar opcache:  https://limaviewtours.com/opcache-reset.php"
echo "3) Si el blade no refresca, borrar por FTP los .php de ${BASE}/storage/framework/views/"
