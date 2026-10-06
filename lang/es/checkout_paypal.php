<?php

/*
|--------------------------------------------------------------------------
| Textos nuevos del lote B1-B4 (PayPal en el checkout normal)
|--------------------------------------------------------------------------
|
| Archivo nuevo a propósito: el coordinador pidió que TODOS los textos
| nuevos de este lote vivan acá, sin tocar lang/*\/ui.php ni lang/*\/checkout.php
| (los edita el maquetador-frontend en paralelo). Algunas claves no son
| "de PayPal" en sentido estricto (p.ej. pickup_detail_label, accept_terms_required
| también aplica a "pagar después"), pero van acá igual por esa instrucción.
|
*/

return [
    // B1: separador entre el botón de tarjeta y el de PayPal. Solo se
    // muestra si el botón de tarjeta SÍ se pinta (isEligible()).
    'or_pay_with_paypal' => 'o paga con tu cuenta PayPal',

    // B3: label del <input> #pickup_detail (la columna ya existía y se
    // persistía; faltaba el campo en el formulario). Placeholder reutiliza
    // checkout.pickup_detail_placeholder, que ya existía en los 3 locales.
    'pickup_detail_label' => 'Detalle adicional del recojo',

    // B4: mensaje de validación cuando el checkbox de términos y
    // condiciones no llega marcado (ProcessPaymentRequest y
    // CheckoutController::paypalCreateOrder()).
    'accept_terms_required' => 'Debes aceptar los términos y condiciones para continuar.',
    'phone_invalid' => 'Ingresa un teléfono válido, con prefijo de país y entre 7 y 15 dígitos.',

    // Alertas y errores del JS del checkout (antes en español fijo).
    'validate_datos_fields' => 'Completa tu nombre, correo, teléfono y fecha de viaje antes de continuar.',
    'validate_email' => 'Ingresa un correo electrónico válido.',
    'validate_empty_cart' => 'Agrega al menos un tour para continuar.',
    'validate_customer_fields' => 'Por favor completa todos los campos obligatorios (nombre, correo, teléfono y fecha de viaje) antes de continuar.',
    'create_order_failed' => 'No se pudo crear la orden.',
];
