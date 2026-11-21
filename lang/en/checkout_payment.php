<?php

/*
| New checkout (2026-11-21): pay 100% / 50% / pay later, balance payment link
| and cancellation rules. Ready for the checkout and the Terms page/modal.
|
| Business rule (client): the 50% deposit is refundable if the booking is
| cancelled MORE than 24 hours before departure; not refundable within 24 hours.
*/

return [
    // Server errors
    'mode_invalid' => 'Choose how much to pay now: 100% or a 50% deposit.',
    'pay_now_via_gateway' => 'Immediate payment must be made by card (Culqi) or PayPal.',
    'nothing_to_charge' => 'There is no amount to charge right now.',

    // "How would you like to pay?" section
    'title' => 'How would you like to pay?',
    'amount_label' => 'Choose how much to pay now',
    'mode_full_title' => '100% now',
    'mode_full_sub' => 'All at once',
    'mode_deposit_title' => '50% now',
    'mode_deposit_sub' => 'Book with a deposit',
    'mode_later_title' => 'Book and pay later',
    'mode_later_sub' => 'No charge now',
    'method_label' => 'Payment method',
    'method_card' => 'Card',
    'method_card_sub' => 'Visa · Mastercard · Amex',
    'method_paypal' => 'Pay with my PayPal account',
    'total_label' => 'Tour total',
    'balance_label' => 'Remaining balance (50%)',
    'pay_now_label' => 'Pay now',
    'pay_now_deposit_note' => '50% deposit',
    'pay_now_full_note' => 'Full payment',
    'pay_now_later_note' => 'Nothing is charged now',

    // Schedule (decision 5): the tour departure time only, no selector.
    'departure_note' => 'You will receive confirmation of your departure time by email and WhatsApp.',

    // Cancellation (decision 4)
    'cancellation_title' => 'Cancellation: refund up to 24 h before',
    'cancellation_deposit' => 'The 50% deposit is refundable if you cancel more than 24 hours before departure. Within 24 hours, the deposit is not refundable.',
    'cancellation_full' => 'If you cancel more than 24 hours before departure, we refund what you paid. Within 24 hours, the payment is not refundable.',
    'cancellation_later' => 'Nothing is charged now. Your booking stays pending until you complete the payment.',
    'balance_info' => 'You pay the balance with a secure link we will email you. We remind you 3 days before the tour and the link expires 48 hours before departure.',

    // "Booking conditions" modal / page
    'conditions_title' => 'Booking conditions',
    'conditions_cancel_title' => 'Cancellation and changes',
    'conditions_cancel_body' => 'You can cancel your booking and receive a refund of what you paid (50% deposit or full payment) if you notify us more than 24 hours before the tour departs. Within 24 hours of departure, or if you do not show up at the departure point, the amount paid is not refundable.',
    'conditions_payment_title' => 'Payment and balance',
    'conditions_payment_body' => 'You can pay 100% now or book with a 50% deposit. The balance is paid with a secure payment link we email you; we remind you 3 days before the tour and it expires 48 hours before departure. If the balance is not paid in time, we will contact you.',
    'conditions_schedule_title' => 'Schedule',
    'conditions_schedule_body' => 'Each tour departs at its usual time. You will receive confirmation of your departure time by email and WhatsApp.',
    'terms_s4_deposit' => 'For bookings with a 50% deposit: the deposit is fully refunded if cancellation is requested more than 24 hours before departure; with 24 hours or less, the deposit is not refundable. The balance is paid through a payment link that expires 48 hours before departure.',
    'refund_s2_deposit' => 'If you booked with a 50% deposit, we refund the deposit when you cancel more than 24 hours before departure. With 24 hours or less, the deposit is not refunded.',

    // Thank you
    'thanks_full' => 'Full payment received. Your booking is confirmed.',
    'thanks_deposit' => 'We received your 50% deposit. Your booking is confirmed; the balance is paid later with a link we will email you.',
    'thanks_later' => 'Your booking is registered. It is confirmed once you complete the payment.',
    'thanks_balance_paid' => 'Balance paid. Your booking is paid in full.',
];
