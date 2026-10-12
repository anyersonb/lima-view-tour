<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Services\BookingCreationService;
use App\Services\CartService;
use App\Support\TourListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * tours.pickup_included / tours.free_cancellation must govern every place that
 * promises pickup or free cancellation: detail page, cart/checkout, bookings
 * and emails; not just the listing card.
 */
class TourPickupCancellationPromiseTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $o = []): Tour
    {
        return Tour::factory()->create(array_merge(['is_published' => true, 'price' => 100], $o));
    }

    private function show(Tour $tour, string $locale = 'es'): string
    {
        return $this->get(route('tours.show', ['locale' => $locale, 'slug' => $tour->slug]))
            ->assertOk()->getContent();
    }

    public function test_detail_page_promises_pickup_and_cancellation_when_switches_are_on(): void
    {
        $html = $this->show($this->tour(['pickup_included' => true, 'free_cancellation' => true]));

        $this->assertStringContainsString('Recojo incluido', $html);
        $this->assertStringContainsString('Desde tu hotel', $html);
        $this->assertStringContainsString('Cancelación gratuita', $html);
    }

    public function test_detail_page_does_not_promise_pickup_when_switch_is_off(): void
    {
        $tour = $this->tour(['pickup_included' => false, 'free_cancellation' => true]);

        foreach (['es' => ['Recojo incluido', 'Desde tu hotel'], 'en' => ['Pickup included', 'From your hotel'], 'pt' => ['Coleta incluída']] as $locale => $texts) {
            $html = $this->show($tour, $locale);
            foreach ($texts as $text) {
                $this->assertStringNotContainsString($text, $html, "$locale still promises: $text");
            }
        }

        $this->assertStringContainsString('Cancelación gratuita', $this->show($tour));
    }

    public function test_detail_page_does_not_promise_free_cancellation_when_switch_is_off(): void
    {
        $tour = $this->tour(['pickup_included' => true, 'free_cancellation' => false]);

        foreach (['es' => 'Cancelación gratuita', 'en' => 'Free cancellation', 'pt' => 'Cancelamento gratuito'] as $locale => $text) {
            $this->assertStringNotContainsString($text, $this->show($tour, $locale), "$locale still promises: $text");
        }

        $this->assertStringContainsString('Recojo incluido', $this->show($tour));
    }

    public function test_cart_asks_for_pickup_only_when_a_tour_has_pickup(): void
    {
        $noPickup = $this->tour(['pickup_included' => false, 'free_cancellation' => false]);
        app(CartService::class)->add($noPickup, 2, 0, now()->addDays(10)->format('Y-m-d'));

        $html = $this->get(route('cart.index', ['locale' => 'es']))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="pickup_point"', $html);
        $this->assertStringNotContainsString('id="pickup_detail"', $html);
        $this->assertStringNotContainsString('Cancelación gratuita hasta 24 horas antes de la salida', $html);

        $withPickup = $this->tour(['pickup_included' => true]);
        app(CartService::class)->add($withPickup, 2, 0, now()->addDays(10)->format('Y-m-d'));

        $html = $this->get(route('cart.index', ['locale' => 'es']))->assertOk()->getContent();
        $this->assertStringContainsString('id="pickup_point"', $html);
    }

    public function test_checkout_does_not_require_pickup_point_for_a_tour_without_pickup(): void
    {
        Mail::fake();
        $tour = $this->tour(['pickup_included' => false]);
        app(CartService::class)->add($tour, 2, 0, now()->addDays(10)->format('Y-m-d'));

        $this->post(route('checkout.process', ['locale' => 'es']), [
            'payment_timing' => 'later',
            'customer_name' => 'Juan Pérez',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '987654321',
            'travel_date' => now()->addDays(15)->format('Y-m-d'),
            'accept_terms' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('bookings', ['tour_id' => $tour->id, 'customer_email' => 'juan@example.com']);
    }

    public function test_bookings_drop_a_posted_pickup_point_for_tours_without_pickup(): void
    {
        Mail::fake();
        $noPickup = $this->tour(['pickup_included' => false]);
        $withPickup = $this->tour(['pickup_included' => true]);
        $item = fn (Tour $t) => [
            'tour_id' => $t->id, 'title_snapshot' => $t->title_es, 'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'adults' => 1, 'children' => 0, 'unit_price' => 100, 'subtotal' => 100,
        ];

        app(BookingCreationService::class)->createBookings(
            collect([$item($noPickup), $item($withPickup)]),
            [
                'customer_name' => 'Ana', 'customer_email' => 'ana@example.com', 'customer_phone' => '987654321',
                'travel_date' => now()->addDays(10)->format('Y-m-d'),
                'pickup_point' => 'Hotel Miraflores', 'pickup_detail' => 'Room 4',
            ],
            'pay_later', null, false, 'es',
        );

        $this->assertDatabaseHas('bookings', ['tour_id' => $noPickup->id, 'pickup_point' => null, 'pickup_detail' => null]);
        $this->assertDatabaseHas('bookings', ['tour_id' => $withPickup->id, 'pickup_point' => 'Hotel Miraflores', 'pickup_detail' => 'Room 4']);
    }

    public function test_confirmation_email_omits_pickup_notice_for_tours_without_pickup(): void
    {
        $render = function (bool $pickup): string {
            $tour = $this->tour(['pickup_included' => $pickup]);
            $booking = \App\Models\Booking::factory()->create(['tour_id' => $tour->id, 'locale' => 'es']);

            return view('emails.bookings.confirmed', ['bookings' => collect([$booking->load('tour')])])->render();
        };

        $this->assertStringContainsString('horario exacto de recojo', $render(true));
        $this->assertStringNotContainsString('horario exacto de recojo', $render(false));
    }

    public function test_language_label_is_never_mixed_between_locales(): void
    {
        foreach (['Español / Inglés', 'Espanhol / Inglés', 'Spanish / English', 'Español / Inglês'] as $raw) {
            $this->assertSame('Espanhol / Inglês', TourListing::languageLabel($raw, 'pt'));
            $this->assertSame('Español / Inglés', TourListing::languageLabel($raw, 'es'));
            $this->assertSame('Spanish / English', TourListing::languageLabel($raw, 'en'));
        }
    }

    public function test_detail_page_language_is_localized_in_portuguese(): void
    {
        $html = $this->show($this->tour(['language' => 'Espanhol / Inglés']), 'pt');

        $this->assertStringContainsString('Espanhol / Inglês', $html);
        $this->assertStringNotContainsString('Espanhol / Inglés', $html);
    }
}
