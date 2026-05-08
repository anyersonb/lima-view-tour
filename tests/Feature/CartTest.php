<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'price'        => 100.00,
            'is_published' => true,
        ], $overrides));
    }

    private function cartService(): CartService
    {
        return app(CartService::class);
    }

    private function validPayload(Tour $tour, array $overrides = []): array
    {
        return array_merge([
            'tour_id'     => $tour->id,
            'adults'      => 2,
            'children'    => 1,
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    //  Tests
    // ─────────────────────────────────────────────────────────────

    public function test_user_can_add_tour_to_cart(): void
    {
        $tour = $this->tour();

        $response = $this->post(
            route('cart.store', ['locale' => self::LOCALE]),
            $this->validPayload($tour)
        );

        $response->assertRedirectToRoute('cart.index', ['locale' => self::LOCALE]);
        $response->assertSessionHas('success');

        $this->assertCount(1, $this->cartService()->items());
        $this->assertEquals(1, $this->cartService()->count());
    }

    public function test_user_can_update_quantity(): void
    {
        $tour = $this->tour();
        $service = $this->cartService();

        // Add first via service so we have a rowId
        $service->add($tour, 2, 0, now()->addDays(5)->format('Y-m-d'));
        $rowId = $service->items()->first()['row_id'];

        $response = $this->patch(
            route('cart.update', ['locale' => self::LOCALE, 'rowId' => $rowId]),
            ['adults' => 3, 'children' => 1]
        );

        $response->assertRedirectToRoute('cart.index', ['locale' => self::LOCALE]);

        $item = $service->items()->first();
        $this->assertEquals(3, $item['adults']);
        $this->assertEquals(1, $item['children']);
        $this->assertEquals(4, $item['quantity']);
        $this->assertEquals(400.00, $item['subtotal']);
    }

    public function test_user_can_remove_item(): void
    {
        $tour = $this->tour();
        $service = $this->cartService();

        $service->add($tour, 1, 0, now()->addDays(3)->format('Y-m-d'));
        $rowId = $service->items()->first()['row_id'];

        $this->assertCount(1, $service->items());

        $response = $this->delete(
            route('cart.destroy', ['locale' => self::LOCALE, 'rowId' => $rowId])
        );

        $response->assertRedirectToRoute('cart.index', ['locale' => self::LOCALE]);
        $this->assertCount(0, $service->items());
    }

    public function test_user_can_apply_valid_coupon_percent(): void
    {
        $tour = $this->tour(['price' => 200]);
        $service = $this->cartService();

        $service->add($tour, 2, 0, now()->addDays(5)->format('Y-m-d'));
        // subtotal = 400

        $response = $this->post(
            route('cart.coupon', ['locale' => self::LOCALE]),
            ['code' => 'LIMA10']
        );

        $response->assertRedirectToRoute('cart.index', ['locale' => self::LOCALE]);
        $response->assertSessionHas('success');

        $this->assertEquals('LIMA10', $service->couponCode());
        $this->assertEquals(40.00, $service->couponDiscount()); // 10% of 400
        $this->assertEquals(360.00, $service->total());
    }

    public function test_user_can_apply_valid_coupon_fixed(): void
    {
        $tour = $this->tour(['price' => 100]);
        $service = $this->cartService();

        $service->add($tour, 1, 0, now()->addDays(5)->format('Y-m-d'));
        // subtotal = 100

        $response = $this->post(
            route('cart.coupon', ['locale' => self::LOCALE]),
            ['code' => 'FIXED5']
        );

        $response->assertSessionHas('success');

        $this->assertEquals('FIXED5', $service->couponCode());
        $this->assertEquals(5.00, $service->couponDiscount());
        $this->assertEquals(95.00, $service->total());
    }

    public function test_invalid_coupon_returns_error(): void
    {
        $response = $this->post(
            route('cart.coupon', ['locale' => self::LOCALE]),
            ['code' => 'FAKE_CODE']
        );

        $response->assertRedirectToRoute('cart.index', ['locale' => self::LOCALE]);
        $response->assertSessionHas('error');

        $this->assertNull($this->cartService()->couponCode());
        $this->assertEquals(0.0, $this->cartService()->couponDiscount());
    }

    public function test_clear_cart(): void
    {
        $tour = $this->tour();
        $service = $this->cartService();

        $service->add($tour, 2, 1, now()->addDays(7)->format('Y-m-d'));
        $this->assertCount(1, $service->items());

        // Apply coupon
        $service->applyCoupon('LIMA10');
        $this->assertNotNull($service->couponCode());

        $response = $this->delete(
            route('cart.clear', ['locale' => self::LOCALE])
        );

        $response->assertRedirectToRoute('cart.index', ['locale' => self::LOCALE]);
        $this->assertCount(0, $service->items());
        $this->assertNull($service->couponCode());
        $this->assertEquals(0.0, $service->subtotal());
    }

    public function test_cart_persists_across_requests_in_session(): void
    {
        $tour = $this->tour(['price' => 150]);

        // First request — add to cart
        $this->post(
            route('cart.store', ['locale' => self::LOCALE]),
            $this->validPayload($tour, ['adults' => 1, 'children' => 0])
        );

        // Second request — check cart index
        $response = $this->get(
            route('cart.index', ['locale' => self::LOCALE])
        );

        $response->assertOk();
        $response->assertViewHas('items', function ($items) {
            return $items->count() === 1;
        });
        $response->assertViewHas('subtotal', 150.00);
    }

    public function test_cart_index_renders_checkout_view_with_items(): void
    {
        $tour    = $this->tour(['price' => 100]);
        $service = $this->cartService();

        $service->add($tour, 2, 1, now()->addDays(5)->format('Y-m-d'));

        $response = $this->get(
            route('cart.index', ['locale' => self::LOCALE])
        );

        $response->assertOk();
        $response->assertViewIs('checkout');
        $response->assertViewHas('items');
        $response->assertViewHas('subtotal');
        $response->assertViewHas('discount');
        $response->assertViewHas('total');
        $response->assertViewHas('couponCode');

        $items = $response->viewData('items');
        $this->assertCount(1, $items);
        $this->assertEquals($tour->title_es, $items->first()['title_snapshot']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Service unit-style tests (no HTTP)
    // ─────────────────────────────────────────────────────────────

    public function test_store_validation_fails_without_required_fields(): void
    {
        $response = $this->post(
            route('cart.store', ['locale' => self::LOCALE]),
            []
        );

        $response->assertSessionHasErrors(['tour_id', 'adults', 'children', 'travel_date']);
    }

    public function test_store_validation_fails_with_past_date(): void
    {
        $tour = $this->tour();

        $response = $this->post(
            route('cart.store', ['locale' => self::LOCALE]),
            $this->validPayload($tour, ['travel_date' => now()->subDay()->format('Y-m-d')])
        );

        $response->assertSessionHasErrors(['travel_date']);
    }
}
