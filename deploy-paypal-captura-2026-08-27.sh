#!/usr/bin/env bash
# ============================================================================
# Deploy lote "captura de PayPal" — Lima View Tours
#
# CRÍTICO (dinero): la captura no verificaba el importe ni ataba el pedido al
# carrito. El cliente aprobaba un pedido barato, engordaba el carrito desde
# otra pestaña y disparaba la captura: se cobraba el importe viejo y se creaban
# las reservas del carrito VIVO, marcadas confirmed/paid, con su correo de
# confirmación como coartada. Para el operador era indistinguible de una
# reserva legítima.
#
# Fix: snapshot en sesión al crear la orden (total de servidor, moneda, ítems);
# al capturar se rechaza un orderID sin snapshot SIN llamar a PayPal, se
# compara el importe contra el snapshot ANTES de capturar, las reservas se
# construyen desde el snapshot y el snapshot se borra al capturar
# (idempotencia ante replay).
#
# Además: finalizeBookings() ya no revalida fechas después de cobrar (podía
# dejar cargo hecho sin reserva al cruzar la medianoche de Lima durante la
# llamada a PayPal), y ese catch ahora sí registra order_id y capture_id.
# Y completitud del carrito: MAX_ROWS en replace(), MAX_PAX_PER_ROW en add(),
# throttle en cart.destroy.
#
# ⚠️ VA DIRECTO A PRODUCCIÓN. No hay staging: /public_html/limaprogramacion
#    SIRVE LA RAÍZ de https://limaviewtours.com/.
#
# La lista de 7 archivos NO es la del reporte del agente: se obtuvo
# comparando cada candidato contra el archivo vivo por FTP. Los otros 14
# salieron idénticos a producción y no se vuelven a subir.
#
# SIN migraciones (producción no tiene artisan).
# SIN barrido de vistas: se verificó por diff contra producción que los tres
# Blade del lote anterior son idénticos, así que este lote no tocó ninguna
# vista y las compiladas siguen siendo válidas.
# SIN borrado de config/rutas cacheadas: se verificó que en producción NO
# existen bootstrap/cache/config.php ni routes-v7.php — este sitio no cachea
# config ni rutas, así que routes/web.php entra en vigor solo.
#
# USO:
#   FTP_PASS='...' bash deploy-paypal-captura-2026-08-27.sh codigo
# Y DESPUÉS, obligatorio (opcache con validate_timestamps apagado):
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

GRUPO="${1:?uso: $0 codigo}"

if [ "$GRUPO" != "codigo" ]; then
  echo "Este lote solo tiene el grupo 'codigo'. No hay cachés que barrer:"
  echo "producción no cachea config ni rutas, y ninguna vista cambió."
  echo "Tras subir, abre el opcache-reset (ver cabecera del script)."
  exit 1
fi

echo "--- CAPTURA DE PAYPAL (verificación de importe + snapshot) ---"
put "app/Http/Controllers/CheckoutController.php"
put "app/Services/PayPalService.php"

echo "--- CARRITO (MAX_ROWS en replace, MAX_PAX_PER_ROW en add) ---"
put "app/Services/CartService.php"

echo "--- RUTAS (throttle en cart.destroy) ---"
put "routes/web.php"

echo "--- TEXTOS (clave nueva cart.row_pax_exceeded) ---"
put "lang/es/cart.php"
put "lang/en/cart.php"
put "lang/pt/cart.php"

echo
echo "RESUMEN: ${OK} subidos, ${FAIL} fallidos  (esperado: 7 y 0)"
echo
echo "AHORA, OBLIGATORIO — sin esto el PHP nuevo no entra en vigor:"
echo "  https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026"
echo
echo "Comprobaciones:"
echo "  1. /es/carrito y la ficha de un tour responden 200 (no 500 por typo)"
echo "  2. El calendario sigue abriendo en el mes actual y mañana es elegible"
echo "  3. UNA COMPRA REAL de prueba con PayPal: es lo único que confirma que"
echo "     el GET /v2/checkout/orders/{id} real devuelve el payload que se"
echo "     asumió (purchase_units[0].amount.value / currency_code). Los tests"
echo "     usan Http::fake() y NO cubren esto."
[ "$FAIL" -eq 0 ] || exit 1
