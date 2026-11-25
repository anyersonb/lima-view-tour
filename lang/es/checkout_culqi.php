<?php

/*
| Textos del cobro con tarjeta vía Culqi (checkout, paso "Pago").
*/

return [
    'title' => 'Pagar con tarjeta',
    'subtitle' => 'Visa, Mastercard, American Express y Diners. Pago seguro procesado por Culqi.',
    'pay_button' => 'Pagar US$:amount con tarjeta',
    'processing' => 'Procesando tu pago…',
    'or_pay_with_paypal' => 'o paga con PayPal',
    'checkout_title' => 'Lima View Tours',
    'unavailable' => 'El pago con tarjeta no está disponible por ahora. Reserva y paga después o escríbenos por WhatsApp.',
    'token_missing' => 'No pudimos leer los datos de tu tarjeta. Inténtalo de nuevo.',
    'in_progress' => 'Ya estamos procesando tu pago. Espera unos segundos.',
    'charge_failed' => 'No pudimos completar el pago con tarjeta. No se te ha cobrado. Inténtalo de nuevo o escríbenos por WhatsApp.',
    'script_failed' => 'No se pudo cargar el formulario de tarjeta. Revisa tu conexión y recarga la página.',
    'token_error' => 'No pudimos validar tu tarjeta. Revisa los datos e inténtalo de nuevo.',
    'captured_booking_pending' => 'Tu pago se procesó correctamente, pero tuvimos un problema al confirmar tu reserva. Nuestro equipo ya fue notificado y la completará de forma manual; te contactaremos en breve. Por favor NO vuelvas a intentar el pago. Guarda esta referencia para cualquier consulta: :reference',
    'declined' => [
        'generic' => 'Tu tarjeta fue rechazada y no se te ha cobrado. Prueba con otra tarjeta o escríbenos por WhatsApp.',
        'insufficient_funds' => 'Tu tarjeta no tiene fondos suficientes. No se te ha cobrado. Prueba con otra tarjeta o escríbenos por WhatsApp.',
        'stolen_card' => 'Tu banco rechazó esta tarjeta. No se te ha cobrado. Prueba con otra tarjeta o contacta a tu banco.',
        'lost_card' => 'Tu banco rechazó esta tarjeta. No se te ha cobrado. Prueba con otra tarjeta o contacta a tu banco.',
        'contact_issuer' => 'Tu banco no autorizó el pago. Contáctalo o prueba con otra tarjeta. No se te ha cobrado.',
        'incorrect_cvv' => 'El código de seguridad (CVV) es incorrecto. Revísalo e inténtalo de nuevo. No se te ha cobrado.',
        'expired_card' => 'Tu tarjeta está vencida. Prueba con otra tarjeta. No se te ha cobrado.',
        'invalid_card' => 'Los datos de la tarjeta no son válidos. Revísalos o prueba con otra tarjeta. No se te ha cobrado.',
        'fraudulent' => 'Tu banco rechazó el pago por seguridad. Prueba con otra tarjeta o escríbenos por WhatsApp. No se te ha cobrado.',
        'issuer_not_available' => 'Tu banco no respondió. Inténtalo en unos minutos o usa otra tarjeta. No se te ha cobrado.',
        'issuer_decline_operation' => 'Tu banco no permite esta operación con la tarjeta. Prueba con otra o escríbenos por WhatsApp. No se te ha cobrado.',
        'processing_error' => 'Hubo un error al procesar la tarjeta. Inténtalo de nuevo o escríbenos por WhatsApp. No se te ha cobrado.',
    ],
];
