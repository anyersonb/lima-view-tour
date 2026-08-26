#!/usr/bin/env bash
# ============================================================================
# Deploy 2026-08-25 — Slug editable tipo WordPress (con 301 automático y aviso
# de rutas duplicadas) + los 242 códigos de país del checkout.
#
# ⚠️ ESTO VA DIRECTO A PRODUCCIÓN. Lima View Tours NO tiene staging: la app en
#    /public_html/limaprogramacion/ sirve https://limaviewtours.com/ (la raíz).
#    Las URLs /limaprogramacion/* llevan un 301 a la raíz desde el 2026-07-02.
#
# ⚠️ ANTES DE CORRER ESTO: aplicar deploy-slug-editable-2026-08-25.sql en
#    phpMyAdmin (BD limaview_limaprogramacion). El código consulta la tabla
#    `slug_redirects`; sin ella el panel y los 404 devuelven 500.
#
# USO:
#   FTP_PASS='...' bash deploy-slug-editable-2026-08-25.sh codigo
#   FTP_PASS='...' bash deploy-slug-editable-2026-08-25.sh caches
#
# En auto-mode el clasificador bloquea las escrituras FTP: correrlo con `!`
# desde el prompt (PowerShell, curl.exe, sin &&).
#
# NO hace falta `npm run build`: no cambió SCSS ni JS, y el componente nuevo no
# introduce clases de Tailwind.
# ============================================================================
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

GRUPO="${1:-codigo}"

if [ "$GRUPO" = "codigo" ] || [ "$GRUPO" = "todo" ]; then
echo "--- SLUG EDITABLE ---"
put "app/Support/RouteConflict.php"
put "app/Support/RouteRegistry.php"
put "app/Models/SlugRedirect.php"
put "app/Models/Concerns/HasLocalizedSlug.php"
put "app/Models/Tour.php"
put "app/Filament/Concerns/HasEditableSlugField.php"
put "app/Filament/Resources/TourResource.php"
put "app/Filament/Resources/BlogPostResource.php"
put "app/Filament/Resources/PageResource.php"
put "database/migrations/2026_08_25_100000_create_slug_redirects_table.php"

echo "--- CODIGOS DE PAIS DEL CHECKOUT ---"
put "config/phone_codes.php"
put "resources/views/components/phone-country-select.blade.php"
put "resources/views/checkout.blade.php"
put "lang/es/ui.php"
put "lang/en/ui.php"
put "lang/pt/ui.php"
fi

if [ "$GRUPO" = "caches" ]; then
  echo "--- LIMPIANDO CACHES EN EL SERVIDOR ---"
  VIEWS=$($CURL "ftp://${HOST}${BASE}/storage/framework/views/" 2>/dev/null | awk '{print $NF}' | grep '\.php$' || true)
  for v in $VIEWS; do
    if $CURL -Q "DELE ${BASE}/storage/framework/views/${v}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      echo "  borrada vista ${v}"
    fi
  done
  # config.php sí hay que borrarlo: config/phone_codes.php es un archivo de
  # config NUEVO y un config cacheado no lo incluiría nunca.
  for c in packages.php services.php config.php events.php; do
    if $CURL -Q "DELE ${BASE}/bootstrap/cache/${c}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      echo "  borrado bootstrap/cache/${c}"
    else
      echo "  (no existia bootstrap/cache/${c})"
    fi
  done
  echo
  echo "Ahora, en este orden:"
  echo "  1. https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
  echo "  2. Comprobar: https://limaviewtours.com/es/carrito (selector de país)"
  echo "  3. Comprobar: https://limaviewtours.com/admin → Tours → un tour → SEO Español"
  exit 0
fi

echo
echo "RESUMEN: ${OK} subidos, ${FAIL} fallidos"
echo "Siguiente: bash $(basename "$0") caches"
[ "$FAIL" -eq 0 ] || exit 1
