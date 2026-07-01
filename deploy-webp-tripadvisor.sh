#!/usr/bin/env bash
# ============================================================
# Deploy: WebP auto-optimización + widget TripAdvisor + CSP
# Target: PRODUCCIÓN (limaviewtours.com == /public_html/limaprogramacion)
#
# USO (desde la raíz del repo, con tus credenciales FTP):
#   FTP_USER='limaweb@limaviewtours.com' FTP_PASS='limaweb@limaviewtours.com' bash deploy-webp-tripadvisor.sh
# ============================================================
set -uo pipefail
: "${FTP_USER:?Define FTP_USER}"
: "${FTP_PASS:?Define FTP_PASS}"

HOST="ftp.limaviewtours.com"
REMOTE="/public_html/limaprogramacion"
LOCAL="$(cd "$(dirname "$0")" && pwd)"

up() { echo "  ↑ $1"; curl -s --max-time 90 --ftp-create-dirs -T "$LOCAL/$1" \
        "ftp://$HOST$REMOTE/$1" --user "$FTP_USER:$FTP_PASS" \
        -w "     -> %{http_code}\n" || echo "     !! falló $1"; }

echo "== Subiendo PHP =="
for f in \
  config/filesystems.php \
  app/Support/ImageOptimizer.php \
  app/Filament/Resources/TourResource.php \
  app/Filament/Resources/BlogPostResource.php \
  app/Filament/Pages/Settings.php \
  app/Http/Middleware/SecurityHeaders.php
do up "$f"; done

echo "== Subiendo Blade =="
for f in \
  resources/views/components/tripadvisor-write-review.blade.php \
  resources/views/reviews.blade.php \
  resources/views/tours/show.blade.php
do up "$f"; done

echo "== Limpiando vistas compiladas en el servidor (obligatorio) =="
# Lista los .php compilados y los borra uno por uno (conserva .gitignore).
LISTING="$(curl -s --max-time 30 "ftp://$HOST$REMOTE/storage/framework/views/" --user "$FTP_USER:$FTP_PASS")"
echo "$LISTING" | awk '{print $NF}' | grep -E '\.php$' | while read -r vf; do
  curl -s --max-time 20 "ftp://$HOST/" --user "$FTP_USER:$FTP_PASS" \
    -Q "DELE $REMOTE/storage/framework/views/$vf" >/dev/null 2>&1 \
    && echo "  🗑  $vf" || echo "  !! no se pudo borrar $vf"
done

echo "== Listo. Verifica: https://limaviewtours.com/es/reseñas y una página de tour =="
