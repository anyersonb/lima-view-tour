<?php

namespace Database\Factories;

use App\Models\PaymentLink;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentLink>
 */
class PaymentLinkFactory extends Factory
{
    protected $model = PaymentLink::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'amount' => 150.00,
            'adults' => 1,
            'children' => 0,
            'travel_date' => null,
            'customer_name' => null,
            'customer_email' => null,
            'customer_phone' => null,
            'note' => null,
            'expires_at' => null,
            'single_use' => true,
            'status' => 'pending',
        ];
    }

    public function paid(): static
    {
        // Un link 'paid' de verdad SIEMPRE tiene un paypal_capture_id — es
        // lo único que lo pone en ese estado (ver
        // PaymentLinkController::captureOrder()). Sin este campo, el
        // guardián de borrado (B-5, docs/payment-links/SECURITY.md) no
        // tendría nada que proteger.
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
            'paypal_capture_id' => $attributes['paypal_capture_id'] ?? 'CAPTURE-'.Str::random(8),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
