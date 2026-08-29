#!/usr/bin/env bash
# ============================================================================
# Deploy lote "fechas de reserva" — Lima View Tours
#
# Reportado: "el cliente intenta reservar y lo obliga a reservar el 4 de
# septiembre" / "quieren reservar para mañana". Tres fallos detrás:
#
#  A. La fecha venía pre-rellenada con hoy+7 en la ficha y los DOS campos del
#     carrito estaban `readonly`: quien no abría el calendario quedaba atrapado
#     en una fecha que no eligió, sin forma de cambiarla.
#  B. finalizeBookings() grababa la fecha del formulario en TODAS las reservas.
#     Dos tours en días distintos se guardaban ambos en el del primero, sin
#     avisar. (Probablemente el "fecha cambiada en reserva" de julio.)
#  C. La app corre en UTC y el operador está en Lima (UTC-5). Entre las 19:00 y
#     medianoche de Lima el servidor ya estaba en el día siguiente, así que
#     `after:today` rechazaba justo la fecha de MAÑANA. Cinco horas cada noche
#     sin poder reservar para el día siguiente.
#
# ⚠️ VA DIRECTO A PRODUCCIÓN. No hay staging: /public_html/limaprogramacion
#    sirve https://limaviewtours.com/.
#
# SIN migraciones. `config/booking.php` es un archivo de config NUEVO, así que
# el paso `caches` (que borra bootstrap/cache/config.php) NO es opcional.
#
# USO:
#   FTP_PASS='...' bash deploy-fechas-reserva-2026-08-27.sh codigo
#   FTP_PASS='...' bash deploy-fechas-reserva-2026-08-27.sh caches
#
# NO hace falta `npm run build`: no cambió SCSS ni JS compilado.
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

GRUPO="${1:?uso: $0 codigo|caches}"

if [ "$GRUPO" = "codigo" ]; then
  echo "--- CALENDARIO DE RESERVAS (zona horaria de Lima) ---"
  put "config/booking.php"
  put "app/Support/BookingCalendar.php"
  put "app/Exceptions/UnbookableDateException.php"
  put "app/Exceptions/CartItemNotFoundException.php"
  put "app/Exceptions/CartLimitExceededException.php"

  echo "--- CARRITO Y CHECKOUT ---"
  put "app/Services/CartService.php"
  put "app/Http/Controllers/CartController.php"
  put "app/Http/Controllers/CheckoutController.php"

  echo "--- RUTAS (throttle en cart.store / cart.update) ---"
  put "routes/web.php"
  put "app/Http/Requests/CartItemRequest.php"
  put "app/Http/Requests/ProcessPaymentRequest.php"

  echo "--- VISTAS ---"
  put "resources/views/tours/show.blade.php"
  put "resources/views/checkout.blade.php"
  put "resources/views/checkout/payment.blade.php"

  echo "--- TEXTOS ---"
  for l in es en pt; do
    put "lang/${l}/ui.php"
    put "lang/${l}/cart.php"
    put "lang/${l}/booking.php"
  done
fi

if [ "$GRUPO" = "caches" ]; then
  echo "--- LIMPIANDO CACHES EN EL SERVIDOR ---"
  VIEWS=$($CURL "ftp://${HOST}${BASE}/storage/framework/views/" 2>/dev/null | awk '{print $NF}' | grep '\.php$' || true)
  for v in $VIEWS; do
    if $CURL -Q "DELE ${BASE}/storage/framework/views/${v}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      echo "  borrada vista ${v}"
    fi
  done
  # config.php OBLIGATORIO: config/booking.php es nuevo y un config cacheado
  # del deploy anterior no lo contiene -> BookingCalendar caería al default.
  for c in packages.php services.php config.php events.php routes-v7.php; do
    if $CURL -Q "DELE ${BASE}/bootstrap/cache/${c}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      echo "  borrado bootstrap/cache/${c}"
    else
      echo "  (no existia bootstrap/cache/${c})"
    fi
  done
  echo
  echo "Ahora: https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
  echo
  echo "Comprobaciones (la 1 y la 2 son las del bug reportado):"
  echo "  1. Ficha de un tour -> el campo de fecha dice 'Seleccionar fecha del tour', VACIO"
  echo "  2. Abrir el calendario -> se abre en el MES ACTUAL y MAÑANA se puede elegir"
  echo "  3. Reservar sin fecha -> no deja pasar, avisa y abre el calendario"
  echo "  4. En el carrito -> la fecha de cada tour se puede cambiar"
  echo "  5. Dos tours con fechas distintas -> el bloque de datos las lista por separado"
  exit 0
fi

echo
echo "RESUMEN: ${OK} subidos, ${FAIL} fallidos"
echo "Siguiente: bash $(basename "$0") caches"
[ "$FAIL" -eq 0 ] || exit 1
