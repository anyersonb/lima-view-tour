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
];
