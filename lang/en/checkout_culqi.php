<?php

/*
| Card payment via Culqi (checkout, "Payment" step).
*/

return [
    'title' => 'Pay by card',
    'subtitle' => 'Visa, Mastercard, American Express and Diners. Secure payment processed by Culqi.',
    'pay_button' => 'Pay US$:amount by card',
    'processing' => 'Processing your payment…',
    'or_pay_with_paypal' => 'or pay with PayPal',
    'checkout_title' => 'Lima View Tours',
    'unavailable' => 'Card payment is not available right now. Reserve and pay later or message us on WhatsApp.',
    'token_missing' => 'We could not read your card details. Please try again.',
    'in_progress' => 'We are already processing your payment. Please wait a few seconds.',
    'charge_failed' => 'We could not complete the card payment. You have not been charged. Please try again or message us on WhatsApp.',
    'script_failed' => 'The card form could not be loaded. Check your connection and reload the page.',
    'token_error' => 'We could not validate your card. Check the details and try again.',
    'captured_booking_pending' => 'Your payment was processed, but we had a problem confirming your booking. Our team has been notified and will complete it manually; we will contact you shortly. Please do NOT try to pay again. Keep this reference for any inquiry: :reference',
    'declined' => [
        'generic' => 'Your card was declined and you have not been charged. Try another card or message us on WhatsApp.',
        'insufficient_funds' => 'Your card has insufficient funds. You have not been charged. Try another card or message us on WhatsApp.',
        'stolen_card' => 'Your bank declined this card. You have not been charged. Try another card or contact your bank.',
        'lost_card' => 'Your bank declined this card. You have not been charged. Try another card or contact your bank.',
        'contact_issuer' => 'Your bank did not authorize the payment. Contact them or try another card. You have not been charged.',
        'incorrect_cvv' => 'The security code (CVV) is incorrect. Check it and try again. You have not been charged.',
        'expired_card' => 'Your card has expired. Try another card. You have not been charged.',
        'invalid_card' => 'The card details are not valid. Check them or try another card. You have not been charged.',
        'fraudulent' => 'Your bank declined the payment for security reasons. Try another card or message us on WhatsApp. You have not been charged.',
        'issuer_not_available' => 'Your bank did not respond. Try again in a few minutes or use another card. You have not been charged.',
        'issuer_decline_operation' => 'Your bank does not allow this operation on the card. Try another one or message us on WhatsApp. You have not been charged.',
        'processing_error' => 'There was an error processing the card. Try again or message us on WhatsApp. You have not been charged.',
    ],
];
