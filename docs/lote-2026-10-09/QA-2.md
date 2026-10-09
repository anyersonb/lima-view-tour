# QA-2 (post Correcciones SECURITY) - APTO
1. Admin reenvio recordatorio (reserva QA2-TEST pay_later pendiente, locale en): a otro email -> aviso exito, correo en log (asunto EN), sent_at NULL, log `by:1, email:qa2-otro, customer_email_original:qa2-orig, marked_sent:false`. Al email original -> sent_at=2026-10-09 02:37:35, log marked_sent:true.
2. `--urgent` (reserva para manana, creada hace 3h): dry lista 1; real envia 1 (1 "To:" en log); 2a urgente -> "No hay reservas por recordar"; diaria inmediata -> 0.
3. Suite filtrada (phpunit.xml = SQLite :memory: confirmado): 169 passed + 4 skipped = 173, 691 assertions, 0 fallos.
4. /admin/bookings abre en "Mañana"; /es 200, /en 200.
Limpieza: reserva QA2-TEST borrada; bookings=36, payment_links=1, 0 filas QA2%; serve (8123) detenido. Sin cambios de codigo/settings.
