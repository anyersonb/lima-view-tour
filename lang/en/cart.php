<?php

return [
    'added' => 'Tour added to cart.',
    'updated' => 'Cart updated successfully.',
    'removed' => 'Tour removed from cart.',
    'cleared' => 'Cart cleared.',
    'empty' => 'Your cart is empty.',
    'coupon_applied' => 'Coupon applied successfully!',
    'coupon_invalid' => 'The coupon code you entered is not valid.',
    'coupon_expired' => 'This coupon has expired.',
    'max_quantity' => 'Maximum allowed quantity is 20.',
    'error' => 'An error occurred. Please try again.',
    'row_not_found' => 'That tour is no longer in your cart. Refresh the page and try again.',
    'cart_full' => 'Your cart already has the maximum of :max different tours. Remove one to add another.',
    'merge_overflow' => 'That date already has a booking for the same tour, and merging them would exceed the maximum of :max people. Reduce the quantity or choose another date.',
    'row_pax_exceeded' => 'The maximum number of people per booking is :max. Reduce the number of adults or children.',

    'validation' => [
        'tour_required' => 'Please select a tour.',
        'tour_not_found' => 'The selected tour does not exist.',
        'adults_required' => 'Please enter the number of adults.',
        'adults_integer' => 'Number of adults must be an integer.',
        'adults_min' => 'At least 1 adult is required.',
        'children_required' => 'Please enter the number of children.',
        'children_integer' => 'Number of children must be an integer.',
        'children_min' => 'Number of children cannot be negative.',
        'date_required' => 'Please select the tour date.',
        'date_invalid' => 'The date format is not valid.',
        'date_future' => 'The earliest available date is :date.',
        'date_too_far' => 'The latest available booking date is :date.',
        'max_quantity' => 'Maximum allowed quantity is 20.',
    ],

    'recover_success' => 'We restored your cart. You can continue with your booking.',
    'recover_expired' => 'The recovery link is no longer valid or the cart is empty.',
];
