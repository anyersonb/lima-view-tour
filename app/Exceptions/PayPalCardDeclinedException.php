<?php

namespace App\Exceptions;

/**
 * PayPal rejected the capture because of the buyer's payment instrument
 * (issue INSTRUMENT_DECLINED in the 422 body), not because of a failure on
 * our side. This gets its own exception type so CheckoutController can
 * respond with a "try another card" message and a buyer-side HTTP status,
 * instead of folding it into the generic server-error branch.
 */
class PayPalCardDeclinedException extends \RuntimeException
{
    public function __construct(
        public readonly string $issue,
    ) {
        parent::__construct("PayPal capture declined by issuer: {$issue}");
    }
}
