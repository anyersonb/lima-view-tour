#!/usr/bin/env bash
# ============================================================================
# Deploy lote "doble cobro tras captura" — Lima View Tours
#
# PROBLEMA: PayPal captura el dinero y DESPUÉS se crean las reservas. Si algo
# reventaba en medio, el cliente veía "El pago no pudo completarse, inténtalo
# de nuevo" con los botones de PayPal vivos -> segundo clic -> SEGUNDO COBRO.
# El rechazo de PayPal por ORDER_ALREADY_CAPTURED no protegía: la segunda
# orden tiene otro orderID. Y finalizeBookings() no estaba en transacción, así
# que un fallo en la reserva 2 de 3 dejaba reservas parciales ya cobradas.
#
# FIX:
#  · Marcador en sesión escrito SOLO si la captura tuvo éxito y falló la
#    reserva. Mientras existe, paypalCreateOrder() responde 409 SIN llamar a
#    PayPal. El candado está en el servidor, no en la UI.
#  · DB::transaction() sobre la creación de reservas. Correos y jobs fuera.
#  · Código y mensaje distintos cuando el dinero YA se movió, con referencia
#    de captura para reclamar.
#  · Frontend: panel persistente, botones de PayPal vaciados, y un flag para
#    que volver a "Datos" y avanzar no los resucite.
#
# ⚠️ VA DIRECTO A PRODUCCIÓN. No hay staging: /public_html/limaprogramacion
#    SIRVE LA RAÍZ de https://limaviewtours.com/.
#
# La lista de 5 archivos se obtuvo comparando cada candidato contra el vivo
# por FTP, no de git ni del reporte de un agente (el árbol tiene varios lotes
# sin commitear y el mtime miente tras un stash pop).
#
# SIN migraciones (producción no tiene artisan).
# SIN borrado de config/rutas: verificado que producción NO cachea ninguna de
# las dos (no existen bootstrap/cache/config.php ni routes-v7.php).
#
# ESTE LOTE SÍ TOCA UNA VISTA (checkout.blade.php). No hace falta barrer las
# 162 vistas compiladas: Laravel recompila cuando el mtime del Blade es más
# nuevo que el del compilado, y la subida por FTP le pone fecha actual. Se
# COMPRUEBA en el HTML vivo (paso 2 de abajo) y solo si no aparece el markup
# nuevo se usa el grupo 'vistas'.
#
# USO:
#   FTP_PASS='...' bash deploy-doble-cobro-2026-08-28.sh codigo
#   (opcional, solo si la comprobación falla) ... vistas
# Y DESPUÉS, obligatorio:
#   https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026
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

GRUPO="${1:?uso: $0 codigo|vistas}"

if [ "$GRUPO" = "vistas" ]; then
  echo "--- BARRIDO DE VISTAS COMPILADAS (solo si el markup nuevo no salió) ---"
  VIEWS=$($CURL "ftp://${HOST}${BASE}/storage/framework/views/" 2>/dev/null | awk '{print $NF}' | grep '\.php$' || true)
  n=0
  for v in $VIEWS; do
    if $CURL -Q "DELE ${BASE}/storage/framework/views/${v}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1; then
      n=$((n+1))
    fi
  done
  echo "  borradas ${n} vistas compiladas"
  echo "  Ahora repite el opcache-reset y vuelve a comprobar."
  exit 0
fi

if [ "$GRUPO" != "codigo" ]; then
  echo "uso: $0 codigo|vistas"; exit 1
fi

echo "--- BLOQUEO DE SERVIDOR + TRANSACCIÓN ---"
put "app/Services/PaymentLockService.php"
put "app/Http/Controllers/CheckoutController.php"
put "app/Http/Controllers/CartController.php"

echo "--- PANEL DE PAGO PENDIENTE (frontend) ---"
put "resources/views/checkout.blade.php"

echo "--- TEXTOS (6 claves payment_review_*) ---"
put "lang/es/ui.php"
put "lang/en/ui.php"
put "lang/pt/ui.php"

echo
echo "RESUMEN: ${OK} subidos, ${FAIL} fallidos  (esperado: 7 y 0)"
echo
echo "AHORA, OBLIGATORIO — sin esto el PHP nuevo no entra en vigor:"
echo "  https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
echo
echo "Comprobaciones:"
echo "  1. /es/carrito, /en/carrito, /pt/carrito y la ficha responden 200"
echo "  2. El HTML del carrito contiene 'payment-review-panel'. Si NO aparece,"
echo "     la vista compilada quedó rancia -> corre el grupo 'vistas'."
echo "  3. El calendario sigue abriendo en el mes actual y mañana es elegible"
echo "  4. UNA COMPRA REAL de sandbox: sigue siendo lo único que confirma el"
echo "     payload real de PayPal. Los tests usan Http::fake()."
[ "$FAIL" -eq 0 ] || exit 1
