#!/usr/bin/env bash
# Deploy 2026-07-26 — 301 de URLs legacy sin prefijo de idioma + sitemap sin duplicados.
#
#   routes/web.php                            -> Route::fallback() 301 legacy -> /es/<path>
#   app/Http/Controllers/SitemapController.php -> unique('loc') (quita /nosotros y /contacto duplicados)
#
# Uso:
#   FTP_HOST=ftp.limaviewtours.com \
#   FTP_USER='limaweb@limaviewtours.com' \
#   FTP_PASS='********' \
#   bash deploy-legacy-301-2026-07-26.sh
set -u

HOST="${FTP_HOST:?define FTP_HOST}"
USER="${FTP_USER:?define FTP_USER}"
PASS="${FTP_PASS:?define FTP_PASS}"
# Ruta de producción (sirve la raíz del dominio)
BASE="/public_html/limaprogramacion"
CURL="curl -sS --ssl-no-revoke --ftp-create-dirs --connect-timeout 30 -u ${USER}:${PASS}"

cd "$(dirname "$0")"

put () {
  local local_path="$1" remote_path="$2"
  echo ">> $remote_path"
  $CURL -T "$local_path" "ftp://${HOST}${remote_path}" && echo "   OK" || echo "   FALLO"
}

put "routes/web.php"                             "${BASE}/routes/web.php"
put "app/Http/Controllers/SitemapController.php" "${BASE}/app/Http/Controllers/SitemapController.php"

# El cambio es en routes/: si hay caché de rutas compilada, el fallback NO carga.
echo ""
echo ">> bootstrap/cache/ (buscando caché de rutas)"
$CURL -l "ftp://${HOST}${BASE}/bootstrap/cache/" 2>/dev/null | sed 's|^|   |'

# ── Segunda tanda (mapa era WordPress) ───────────────────────────────────
put "config/legacy_redirects.php" "${BASE}/config/legacy_redirects.php"
