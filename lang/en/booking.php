<?php

return [
    'date_blocked' => 'That date is not available for booking. Please choose another.',
    // Per-item message at cart/checkout: names the tour and its first bookable date.
    'date_item_unavailable' => 'The date for “:tour” is no longer available. The earliest available date is :date. Bookings for the next day close at :cutoff (Lima time).',
    'date_outdated' => 'The date for one of your tours is no longer available (it changed while your cart was open). Please review the dates and try again.',
    'card_declined' => 'Your bank declined the card. Please try a different card or contact us on WhatsApp.',
    'payment_failed' => 'We couldn\'t complete the payment. Please try again or contact us.',

    // Time slots and same-day booking (2026-11-24)
    'slot_required' => 'Please choose a departure time.',
    'slot_invalid' => 'That time is not available for this tour. Please choose another.',
    'slot_too_soon' => 'That time can no longer be booked for today: bookings need at least :hours hours notice. Choose another time or a later date.',
    'slot_item_unavailable' => 'The time for ":tour" is no longer available on that date. Change it or choose another date.',
    'same_day_notice' => 'Same-day booking available: at least :hours hours before the chosen time.',
    'same_day_mail_prefix' => '[SAME DAY]',
    'same_day_mail_banner' => 'SAME-DAY BOOKING — confirm today. Departs :date at :time',
    'slot_label' => 'Departure time',
];
