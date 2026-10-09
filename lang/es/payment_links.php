<?php

return [
    'page_title' => 'Pagar :tour',
    'page_heading' => 'Completa tu pago',
    'meta_description' => 'Completa tu pago de forma segura con PayPal.',
    'generic_tour_name' => 'Tour personalizado',
    'date_to_be_arranged' => 'Fecha por coordinar',

    'not_found_title' => 'Enlace no encontrado',
    'not_found' => 'Este enlace de pago no existe o ya no está disponible. Si crees que es un error, contáctanos.',

    'already_paid_title' => 'Este pago ya fue realizado',
    'already_paid' => 'Este enlace ya fue utilizado para completar un pago. Si tienes dudas sobre tu reserva, contáctanos.',

    'cancelled_title' => 'Enlace anulado',
    'cancelled' => 'Este enlace de pago fue anulado. Si necesitas uno nuevo, contáctanos.',

    'expired_title' => 'Enlace vencido',
    'expired' => 'Este enlace de pago venció. Si necesitas uno nuevo, contáctanos.',

    'not_available_title' => 'Enlace no disponible',
    'not_available' => 'Este enlace de pago ya no está disponible.',

    'processing_wait' => 'Tu pago se está procesando. Por favor espera unos segundos y no cierres esta página.',

    'captured_booking_pending' => 'Tu pago se procesó correctamente, pero tuvimos un problema al confirmar tu reserva. '
        .'Nuestro equipo ya fue notificado y la completará de forma manual; te contactaremos en breve. '
        .'Por favor NO vuelvas a intentar el pago. Guarda esta referencia para cualquier consulta: :reference',

    // i18n fix 2026-10-08
    'adults_count' => '{1} :count adulto|[2,*] :count adultos',
    'children_count' => '{1} :count niño|[2,*] :count niños',
    'secure_payment' => 'Pago 100% seguro con PayPal',
    'phone_label' => 'Teléfono',
    'js_create_failed' => 'No se pudo iniciar el pago.',
    'js_capture_failed' => 'No pudimos completar el pago.',
    'js_paypal_error' => 'Ocurrió un error con PayPal. Por favor recarga la página e inténtalo de nuevo.',
];
