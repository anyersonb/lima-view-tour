<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cart Coupons
    |--------------------------------------------------------------------------
    |
    | Hardcoded coupon list for Phase 2. In Phase 3+ replace with DB-backed model.
    | type: 'percent' applies value as %, 'fixed' subtracts flat amount.
    |
    */
    'coupons' => [
        'LIMA10'    => ['type' => 'percent', 'value' => 10],
        'WELCOME20' => ['type' => 'percent', 'value' => 20],
        'FIXED5'    => ['type' => 'fixed',   'value' => 5],
    ],

    /*
    |--------------------------------------------------------------------------
    | Carrito abandonado
    |--------------------------------------------------------------------------
    |
    | first_reminder_after_minutes  Minutos de inactividad tras los que se envía
    |                               el 1er recordatorio.
    | second_reminder_after_minutes Minutos de inactividad tras los que se envía
    |                               el 2do (y último) recordatorio.
    | max_reminders                 Tope de correos de recuperación por carrito.
    | expire_after_days             Días tras los que un carrito activo sin
    |                               convertir se marca 'expired' y ya no se
    |                               notifica.
    | batch_size                    Máx. de carritos procesados por corrida del
    |                               comando (evita picos de envío).
    |
    */
    'abandoned' => [
        'first_reminder_after_minutes'  => 60,        // 1 hora
        'second_reminder_after_minutes' => 60 * 24,   // 24 horas
        'max_reminders'                 => 2,
        'expire_after_days'             => 7,
        'batch_size'                    => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recordatorio de pago — reservas "pagar luego"
    |--------------------------------------------------------------------------
    |
    | Cuando un cliente reserva con la opción "pagar luego" (payment_method =
    | 'pay_later', payment_status = 'pending'), se le envía un correo
    | recordándole que complete el pago ANTES de la fecha del tour.
    |
    | enabled       Activa/desactiva el envío de recordatorios.
    | days_before   Días de antelación respecto a la fecha del tour en que se
    |               dispara el recordatorio (p. ej. 2 = dos días antes).
    | batch_size    Máx. de reservas procesadas por corrida del comando.
    |
    */
    'payment_reminder' => [
        'enabled'     => true,
        'days_before' => 2,
        'batch_size'  => 100,
        // Reservas de último momento (tour hoy/mañana): el recordatorio sale
        // esta cantidad de horas tras crear la reserva (comando --urgent,
        // cada hora entre urgent_window_start y urgent_window_end, hora Lima).
        'urgent_delay_hours' => 2,
        'urgent_window_start' => '07:00',
        'urgent_window_end'   => '22:00',
        // Intentos fallidos de envío por reserva antes de dejar de reintentar
        // (contador en Cache, 3 días; sin columna ni migración).
        'max_attempts' => 3,
    ],
];
