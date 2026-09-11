#!/usr/bin/env bash
# Deploy 2026-08-24 — Metas editables por URL (home, catalogo, catalogo por
# region y listado del blog) desde Configuracion -> SEO -> Metas por pagina.
#
# NO hay migraciones: los valores viven en la tabla `settings`, que ya existe.
# Orden: codigo -> caches -> opcache-reset.
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

if [ "$GRUPO" = "codigo" ] || [ "$GRUPO" = "todo" ]; then
echo "--- CODIGO ---"
put "app/Support/PageSeo.php"
put "app/Filament/Pages/Settings.php"
put "app/Filament/Resources/RegionResource.php"
put "app/Models/Setting.php"
put "resources/views/home.blade.php"
put "resources/views/tours/index.blade.php"
put "resources/views/blog/index.blade.php"
fi

if [ "$GRUPO" = "caches" ]; then
  echo "--- LIMPIANDO CACHES EN EL SERVIDOR ---"
  VIEWS=$($CURL "ftp://${HOST}${BASE}/storage/framework/views/" 2>/dev/null | awk '{print $NF}' | grep '\.php$' || true)
  for v in $VIEWS; do
    if $CURL -Q "DELE ${BASE}/storage/framework/views/${v}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      echo "  borrada vista ${v}"
    fi
  done
  for c in packages.php services.php config.php events.php; do
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
