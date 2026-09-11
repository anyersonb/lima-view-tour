#!/usr/bin/env bash
# Deploy 2026-08-02 — Recordatorio de pago "pagar luego": mensaje explícito de que
# la reserva NO se hace efectiva sin pago, portugués, y botón de pago directo
# cuando la reserva tiene link cargado desde el panel.
#
#   resources/views/emails/bookings/payment-reminder.blade.php
#   app/Mail/BookingPaymentReminder.php   (asunto ES/EN/PT)
#
# Uso:
#   FTP_PASS='********' bash deploy-recordatorio-pago-2026-08-02.sh
set -u

HOST="${FTP_HOST:-ftp.limaviewtours.com}"
USER="${FTP_USER:-limaweb@limaviewtours.com}"
PASS="${FTP_PASS:?define FTP_PASS}"
# Ruta de producción (sirve la raíz del dominio). NO es /limaprogramacion a secas.
BASE="/public_html/limaprogramacion"
CURL="curl -sS --ssl-no-revoke --ftp-create-dirs --connect-timeout 30 -u ${USER}:${PASS}"

cd "$(dirname "$0")"

put () {
  local local_path="$1" remote_path="$2"
  echo ">> $remote_path"
  $CURL -T "$local_path" "ftp://${HOST}${remote_path}" && echo "   OK" || echo "   FALLO"
}

put "resources/views/emails/bookings/payment-reminder.blade.php" \
    "${BASE}/resources/views/emails/bookings/payment-reminder.blade.php"
put "app/Mail/BookingPaymentReminder.php" \
    "${BASE}/app/Mail/BookingPaymentReminder.php"

# ── Limpiar vistas Blade compiladas ──────────────────────────────────────────
# Sin esto el correo puede seguir renderizando la plantilla vieja.
echo ""
echo ">> limpiando storage/framework/views/"
for f in $($CURL -l "ftp://${HOST}${BASE}/storage/framework/views/" 2>/dev/null | grep '\.php$'); do
  $CURL -Q "DELE ${BASE}/storage/framework/views/${f}" "ftp://${HOST}${BASE}/" >/dev/null 2>&1 \
    && echo "   borrado $f"
done

# ── Refrescar opcache (el server lo tiene congelado entre deploys) ───────────
echo ""
echo ">> opcache-reset"
curl -sS --ssl-no-revoke "https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026" | head -3

echo ""
echo "Listo. Verificar con:  php artisan bookings:send-payment-reminders --dry  (cPanel Terminal)"
