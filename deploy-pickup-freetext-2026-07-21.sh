#!/usr/bin/env bash
# Deploy 2026-07-21 — Desactivar buscador Google Maps en el recojo del checkout
#   (campo de texto libre). Un solo archivo: resources/views/checkout.blade.php
#
# Uso:
#   FTP_HOST=ftp.limaviewtours.com \
#   FTP_USER='limaweb@limaviewtours.com' \
#   FTP_PASS='********' \
#   bash deploy-pickup-freetext-2026-07-21.sh
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

put "resources/views/checkout.blade.php" "${BASE}/resources/views/checkout.blade.php"

echo ""
echo "=== PASOS MANUALES EN SERVIDOR ==="
echo "1) Refrescar opcache:  https://limaviewtours.com/opcache-reset.php"
echo "2) Si el blade no refresca, borrar por FTP los .php de ${BASE}/storage/framework/views/"
