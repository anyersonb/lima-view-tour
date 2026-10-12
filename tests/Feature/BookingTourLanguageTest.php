<?php

namespace Tests\Feature;

use App\Filament\Resources\BookingResource\Pages\EditBooking;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Mail\BookingConfirmed;
use App\Mail\BookingNotificationAdmin;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\User;
use App\Services\BookingNotifier;
use App\Services\CartService;
use App\Support\BookingCalendar;
use App\Support\TourLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tour language contract: 'es' | 'en' | 'pt'. Cart-card labels are normalised
 * to the code, the default is the site language, and the booking, the e-mails
 * and the panel show a readable label.
 */
class BookingTourLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TourLanguage::flush();
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'customer_name' => 'Ana Torres',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '+51987654321',
            'payment_timing' => 'later',
            'travel_date' => BookingCalendar::earliestDate(),
            'accept_terms' => true,
        ], $o);
    }

    private function checkout(array $o = [], string $locale = 'es'): Booking
    {
        Mail::fake();
        $tour = Tour::factory()->create(['is_published' => true]);
        app(CartService::class)->add($tour, 1, 0, BookingCalendar::earliestDate());

        $this->post("/{$locale}/checkout/procesar", $this->payload($o))->assertSessionHasNoErrors();

        return Booking::firstOrFail();
    }

    /** @dataProvider normalisation */
    public function test_normalize_maps_codes_and_labels_to_the_code(?string $in, ?string $expected): void
    {
        $this->assertSame($expected, TourLanguage::normalize($in));
    }

    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function normalisation(): array
    {
        return [
            'code es' => ['es', 'es'],
            'code en' => ['en', 'en'],
            'code pt' => ['pt', 'pt'],
            'upper' => ['EN', 'en'],
            'label Español' => ['Español', 'es'],
            'label English' => ['English', 'en'],
            'label Inglés' => ['Inglés', 'en'],
            'label Português' => ['Português', 'pt'],
            'label Portugues' => ['Portugues', 'pt'],
            'pt-BR' => ['pt-BR', 'pt'],
            'en_US' => ['en_US', 'en'],
            'unknown' => ['klingon', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    public function test_label_is_readable(): void
    {
        $this->assertSame('Español', TourLanguage::label('es'));
        $this->assertSame('English', TourLanguage::label('en'));
        $this->assertSame('Português', TourLanguage::label('pt'));
        $this->assertNull(TourLanguage::label(null));
    }

    public function test_pay_later_stores_the_code_chosen_on_the_cart_card(): void
    {
        $this->assertSame('pt', $this->checkout(['tour_language' => 'pt'])->tour_language);
    }

    public function test_pay_later_normalises_a_label_posted_by_an_old_card(): void
    {
        $this->assertSame('en', $this->checkout(['tour_language' => 'English'])->tour_language);
    }

    public function test_without_a_choice_it_defaults_to_the_site_language(): void
    {
        $this->assertSame('en', $this->checkout([], 'en')->tour_language);
    }

    public function test_garbage_does_not_break_the_checkout_and_falls_back_to_the_site_language(): void
    {
        $this->assertSame('es', $this->checkout(['tour_language' => 'klingon'])->tour_language);
    }

    public function test_paypal_capture_stores_the_language(): void
    {
        Mail::fake();
        $tour = Tour::factory()->create(['price' => 150.00, 'is_published' => true]);
        app(CartService::class)->add($tour, 1, 0, now()->addDays(10)->toDateString());

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders' => Http::response(['id' => 'ORDER-L'], 201),
        ]);
        $this->postJson(route('checkout.paypal.create', ['locale' => 'es']), [
            'customer_name' => 'Juan Pérez',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'accept_terms' => true,
        ])->assertOk();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/v2/checkout/orders/ORDER-L/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE-L']]]]],
            ], 200),
            '*/v2/checkout/orders/ORDER-L' => Http::response([
                'purchase_units' => [['amount' => ['currency_code' => 'USD', 'value' => '150.00']]],
            ], 200),
        ]);

        $this->postJson(route('checkout.paypal.capture', ['locale' => 'es']), [
            'orderID' => 'ORDER-L',
            'customer_name' => 'Juan Pérez',
            'customer_email' => 'juan@example.com',
            'customer_phone' => '+51987654321',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'tour_language' => 'Português',
        ])->assertOk();

        $this->assertSame('pt', Booking::firstOrFail()->tour_language);
    }

    public function test_customer_email_shows_the_readable_label(): void
    {
        $booking = Booking::factory()->create(['tour_language' => 'pt', 'locale' => 'es']);

        $html = (new BookingConfirmed(collect([$booking]), $booking->customer_email))->render();

        $this->assertStringContainsString('Português', $html);
        $this->assertStringContainsString('Idioma del tour', $html);
    }

    public function test_admin_email_shows_the_readable_label(): void
    {
        $booking = Booking::factory()->create(['tour_language' => 'en']);

        $html = (new BookingNotificationAdmin(collect([$booking]), true))->render();

        $this->assertStringContainsString('English', $html);
    }

    public function test_panel_list_and_form_show_the_language(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));
        $booking = Booking::factory()->create(['tour_language' => 'pt']);

        Livewire::test(ListBookings::class)
            ->assertTableColumnFormattedStateSet('tour_language', 'Português', $booking);

        Livewire::test(EditBooking::class, ['record' => $booking->getKey()])
            ->assertFormSet(['tour_language' => 'pt']);
    }

    public function test_everything_tolerates_the_column_not_being_migrated_yet(): void
    {
        Schema::table('bookings', fn ($t) => $t->dropColumn('tour_language'));
        TourLanguage::flush();

        $booking = $this->checkout(['tour_language' => 'en']);

        $this->assertArrayNotHasKey('tour_language', $booking->getAttributes());
        $this->assertNull($booking->tour_language_label);

        // The confirmation e-mail renders without the row and without errors.
        $html = (new BookingConfirmed(collect([$booking]), $booking->customer_email))->render();
        $this->assertStringNotContainsString('Idioma del tour', $html);

        // The notifier path used by the panel/checkout also survives.
        app(BookingNotifier::class)->send(collect([$booking]), false);
        $this->addToAssertionCount(1);
    }

    public function test_migration_is_reversible_and_idempotent(): void
    {
        $migration = require database_path('migrations/2026_10_12_000001_add_tour_language_to_bookings_table.php');

        $migration->up(); // already there (RefreshDatabase): no-op, no error
        $this->assertTrue(Schema::hasColumn('bookings', 'tour_language'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('bookings', 'tour_language'));
        $migration->down(); // second down is harmless

        $migration->up();
        $this->assertTrue(Schema::hasColumn('bookings', 'tour_language'));
    }
}
