<?php

/*
| Checkout nuevo (2026-11-21): modos de pago 100% / 50% / pagar después,
| saldo con link de pago y reglas de cancelación. Textos listos para el
| checkout y para el modal / página de Condiciones.
|
| Regla de negocio (cliente): el adelanto del 50% es reembolsable si se cancela
| con MÁS de 24 horas de anticipación a la salida; con menos de 24 horas no.
*/

return [
    // Errores de servidor
    'mode_invalid' => 'Elige cuánto quieres pagar ahora: 100 % o 50 % de adelanto.',
    'pay_now_via_gateway' => 'El pago inmediato debe hacerse con tarjeta.',
    'nothing_to_charge' => 'No hay ningún importe para cobrar en este momento.',

    // Sección "¿Cómo prefieres pagar?"
    'title' => '¿Cómo prefieres pagar?',
    'amount_label' => 'Elige cuánto pagar ahora',
    'mode_full_title' => '100% ahora',
    'mode_full_sub' => 'Todo de una vez',
    'mode_deposit_title' => '50% ahora',
    'mode_deposit_sub' => 'Reserva con adelanto',
    'mode_later_title' => 'Reservar y pagar después',
    'mode_later_sub' => 'Sin cobro ahora',
    'method_label' => 'Método de pago',
    'method_card' => 'Tarjeta',
    'method_card_sub' => 'Visa · Mastercard · Amex',
    'method_paypal' => 'Pagar con mi cuenta PayPal',
    'total_label' => 'Total del tour',
    'balance_label' => 'Saldo posterior (50%)',
    'pay_now_label' => 'A pagar ahora',
    'pay_now_deposit_note' => '50% de adelanto',
    'pay_now_full_note' => 'Pago completo',
    'pay_now_later_note' => 'No se cobra nada ahora',

    // Horario (decisión 5): solo la hora de salida del tour, sin selector.
    'departure_note' => 'Recibirás la confirmación de tu horario por correo y WhatsApp.',

    // Cancelación (decisión 4)
    'cancellation_title' => 'Cancelación: reembolso hasta 24 h antes',
    'cancellation_deposit' => 'El adelanto del 50% es reembolsable si cancelas con más de 24 horas de anticipación a la salida. Con menos de 24 horas, el adelanto no es reembolsable.',
    'cancellation_full' => 'Si cancelas con más de 24 horas de anticipación a la salida, te devolvemos lo pagado. Con menos de 24 horas, el pago no es reembolsable.',
    'cancellation_later' => 'No se cobra nada ahora. Tu reserva queda pendiente hasta que completes el pago.',
    'balance_info' => 'El saldo lo pagas con un enlace seguro que te enviaremos por correo. Te lo recordamos 3 días antes del tour y el enlace vence 48 horas antes de la salida.',

    // Modal / página "Condiciones de reserva"
    'conditions_title' => 'Condiciones de reserva',
    'conditions_cancel_title' => 'Cancelación y cambios',
    'conditions_cancel_body' => 'Puedes cancelar tu reserva y recibir el reembolso de lo pagado (adelanto del 50% o pago completo) si avisas con más de 24 horas de anticipación a la salida del tour. Con menos de 24 horas de anticipación, o si no te presentas en el punto de salida, el importe pagado no es reembolsable.',
    'conditions_payment_title' => 'Pago y saldo',
    'conditions_payment_body' => 'Puedes pagar el 100% ahora o reservar con un adelanto del 50%. El saldo se paga con un enlace de pago seguro que te enviamos por correo; te lo recordamos 3 días antes del tour y vence 48 horas antes de la salida. Si no se completa el pago del saldo a tiempo, nos pondremos en contacto contigo.',
    'conditions_schedule_title' => 'Horario',
    'conditions_schedule_body' => 'Cada tour sale a su hora habitual. Recibirás la confirmación de tu horario por correo y WhatsApp.',
    'terms_s4_deposit' => 'Para reservas con adelanto del 50%: el adelanto se reembolsa íntegramente si la cancelación se solicita con más de 24 horas de anticipación a la salida; con 24 horas o menos, el adelanto no es reembolsable. El saldo se abona mediante un enlace de pago que vence 48 horas antes de la salida.',
    'refund_s2_deposit' => 'Si reservaste con adelanto del 50%, te devolvemos el adelanto cuando cancelas con más de 24 horas de anticipación a la salida. Con 24 horas o menos, el adelanto no se reembolsa.',

    // Gracias
    'thanks_full' => 'Pago completo recibido. Tu reserva está confirmada.',
    'thanks_deposit' => 'Recibimos tu adelanto del 50%. Tu reserva está confirmada; el saldo se paga después con un enlace que te enviaremos por correo.',
    'thanks_later' => 'Tu reserva quedó registrada. Se confirma cuando completes el pago.',
    'thanks_balance_paid' => 'Saldo pagado. Tu reserva está pagada al 100%.',

    // Salidas en menos de 48 h: solo pago completo (2026-11-24)
    'full_only_notice' => 'Para salidas en menos de 48 h el pago es completo.',
    'full_only_required' => 'Para salidas en menos de 48 h el pago es completo (100 % ahora). Elige pagar el total.',

    // Sin método de pago disponible (PayPal oculto y Culqi apagado)
    'no_method_available' => 'En este momento no podemos cobrar en línea. Escríbenos por WhatsApp y te ayudamos a completar tu reserva.',
    'paypal_disabled' => 'PayPal no está disponible. Paga con tarjeta.',
];
