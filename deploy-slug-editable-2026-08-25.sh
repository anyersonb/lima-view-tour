#!/usr/bin/env bash
# ============================================================================
# Deploy lote "slug editable" — Lima View Tours
#
#  · Slug español editable en el CMS, con 301 automático de la dirección
#    anterior y aviso de rutas duplicadas.
#  · Los 242 códigos de país en el selector de teléfono del checkout.
#
# ⚠️ ESTO VA DIRECTO A PRODUCCIÓN. Lima View Tours NO tiene staging: la app en
#    /public_html/limaprogramacion/ sirve https://limaviewtours.com/ (la raíz).
#    Las URLs /limaprogramacion/* llevan un 301 a la raíz desde el 2026-07-02.
#
# ORDEN OBLIGATORIO — la tabla ANTES del código:
#
#   1) bash deploy-slug-editable-2026-08-25.sh migraciones
#   2) bash deploy-slug-editable-2026-08-25.sh runner
#   3) abrir https://limaviewtours.com/migrate-slug-redirects.php?t=anyerson-2026-08-26-slug-redirects
#      → tiene que terminar en "LISTO". Si no, PARAR acá: el código todavía no subió.
#   4) bash deploy-slug-editable-2026-08-25.sh codigo
#   5) bash deploy-slug-editable-2026-08-25.sh caches
#   6) abrir https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026
#   7) bash deploy-slug-editable-2026-08-25.sh limpiar-runner
#
# Por qué la tabla va primero: el código consulta `slug_redirects` en cada URL
# que no encuentra ficha y en cada render del campo de slug del panel. Con el
# código arriba y sin tabla, esas rutas devuelven 500 en vez de 404.
#
# En auto-mode el clasificador bloquea las escrituras FTP: correrlo con `!`.
# NO hace falta `npm run build`: no cambió SCSS ni JS, y nada introduce clases
# de Tailwind nuevas.
#
# Fallback si el runner no puede correr: deploy-slug-editable-2026-08-25.sql
# hace lo mismo desde phpMyAdmin (BD limaview_limaprogramacion).
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

GRUPO="${1:?uso: $0 migraciones|runner|codigo|caches|limpiar-runner}"

if [ "$GRUPO" = "migraciones" ]; then
  echo "--- MIGRACION ---"
  put "database/migrations/2026_08_25_100000_create_slug_redirects_table.php"
fi

if [ "$GRUPO" = "runner" ]; then
  echo "--- RUNNER DE UN SOLO USO ---"
  put "public/migrate-slug-redirects.php"
  echo
  echo "Ahora abrir:"
  echo "  https://limaviewtours.com/migrate-slug-redirects.php?t=anyerson-2026-08-26-slug-redirects"
  echo "Tiene que terminar en 'LISTO'. Si no, NO seguir con 'codigo'."
fi

if [ "$GRUPO" = "codigo" ]; then
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
  # config.php es OBLIGATORIO acá: config/phone_codes.php es un archivo de
  # config NUEVO, y un config cacheado del deploy anterior no lo contiene.
  for c in packages.php services.php config.php events.php; do
    if $CURL -Q "DELE ${BASE}/bootstrap/cache/${c}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      echo "  borrado bootstrap/cache/${c}"
    else
      echo "  (no existia bootstrap/cache/${c})"
    fi
  done
  echo
  echo "Ahora: https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
  echo "Y después, las comprobaciones:"
  echo "  · https://limaviewtours.com/es/carrito  → selector de país con la lista completa"
  echo "  · https://limaviewtours.com/admin → Tours → un tour → SEO Español → el slug se puede escribir"
  echo "  · una URL inventada, p.ej. /es/tours/detalle/no-existe-nada → tiene que dar 404, NO 500"
  echo "Cuando esté todo verde: bash $(basename "$0") limpiar-runner"
fi

if [ "$GRUPO" = "limpiar-runner" ]; then
  echo "--- BORRANDO EL RUNNER ---"
  if $CURL -Q "DELE ${BASE}/public/migrate-slug-redirects.php" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
    echo "  borrado public/migrate-slug-redirects.php"
  else
    echo "  !! no se pudo borrar public/migrate-slug-redirects.php — BORRARLO A MANO"
  fi
  # Resto del lote del 2026-08-20, si quedó.
  if $CURL -Q "DELE ${BASE}/public/migrate-once.php" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
    echo "  borrado public/migrate-once.php (residuo del lote 2026-08-20)"
  else
    echo "  (public/migrate-once.php ya no estaba)"
  fi
  exit 0
fi

echo
echo "RESUMEN: ${OK} subidos, ${FAIL} fallidos"
[ "$FAIL" -eq 0 ] || exit 1
