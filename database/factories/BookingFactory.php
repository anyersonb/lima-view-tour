<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $adults = $this->faker->numberBetween(1, 4);
        $children = $this->faker->numberBetween(0, 2);
        $unitPrice = $this->faker->randomElement([65, 100, 120, 200]);

        return [
            'tour_id' => Tour::factory(),
            'tour_title_snapshot' => $this->faker->sentence(4),
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->unique()->safeEmail(),
            'customer_phone' => $this->faker->numerify('9########'),
            'travel_date' => $this->faker->dateTimeBetween('-30 days', '+60 days')->format('Y-m-d'),
            'adults' => $adults,
            'children' => $children,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * ($adults + $children),
            'discount_amount' => 0,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'pay_later',
            'locale' => 'es',
        ];
    }

    public function paid(): static
    {
        return $this->state(['payment_status' => 'paid', 'status' => 'confirmed']);
    }

    public function pendingPayment(): static
    {
        return $this->state(['payment_status' => 'pending']);
    }

    public function failedPayment(): static
    {
        return $this->state(['payment_status' => 'failed']);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function customTour(): static
    {
        return $this->state([
            'tour_id' => null,
            'custom_tour_details' => $this->faker->sentence(),
        ]);
    }

    public function withPickup(): static
    {
        return $this->state([
            'pickup_point' => 'Miraflores',
            'pickup_detail' => $this->faker->streetAddress(),
        ]);
    }

    public function withDiscount(): static
    {
        return $this->state([
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'discount_amount' => 10,
        ]);
    }
}
