<?php

/*
|--------------------------------------------------------------------------
| New strings for the B1-B4 batch (PayPal on the normal checkout)
|--------------------------------------------------------------------------
|
| New file on purpose: the coordinator asked that ALL new copy for this
| batch live here, without touching lang/*\/ui.php or lang/*\/checkout.php
| (the frontend agent is editing those in parallel). Some keys are not
| strictly "PayPal" (e.g. pickup_detail_label, accept_terms_required also
| applies to "pay later"), but they still go here per that instruction.
|
*/

return [
    // B1: separator between the card button and the PayPal button. Only
    // shown when the card button actually renders (isEligible()).
    'or_pay_with_paypal' => 'or pay with your PayPal account',

    // B3: label for the #pickup_detail <input> (the column already existed
    // and was already being persisted; the form field was missing).
    // Placeholder reuses checkout.pickup_detail_placeholder, already present
    // in all 3 locales.
    'pickup_detail_label' => 'Additional pickup detail',

    // B4: validation message when the terms-and-conditions checkbox isn't
    // submitted (ProcessPaymentRequest and CheckoutController::paypalCreateOrder()).
    'accept_terms_required' => 'You must accept the terms and conditions to continue.',
    'phone_invalid' => 'Enter a valid phone number with country code, 7 to 15 digits.',

    // Alertas y errores del JS del checkout (antes en español fijo).
    'validate_datos_fields' => 'Please enter your name, email, phone and travel date before continuing.',
    'validate_email' => 'Please enter a valid email address.',
    'validate_empty_cart' => 'Add at least one tour to continue.',
    'validate_customer_fields' => 'Please complete all required fields (name, email, phone and travel date) before continuing.',
    'create_order_failed' => 'The order could not be created.',
];
