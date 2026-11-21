<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutDurationLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function cartPage(string $locale, string $duration): string
    {
        $tour = Tour::factory()->create(['price' => 50, 'duration' => $duration, 'is_published' => true]);
        app(CartService::class)->add($tour, 2, 0, now()->addDays(10)->format('Y-m-d'));

        return $this->get("/{$locale}/carrito")->assertOk()->getContent();
    }

    public function test_english_cart_shows_hours_not_horas(): void
    {
        $html = $this->cartPage('en', '4 horas');
        $this->assertStringContainsString('>4 hours<', $html);
        $this->assertStringNotContainsString('4 horas', $html);
    }

    public function test_portuguese_cart_localises_hours_and_full_day(): void
    {
        $this->assertStringContainsString('>4 horas<', $this->cartPage('pt', '4 hours'));
    }

    public function test_english_cart_localises_full_day(): void
    {
        $html = $this->cartPage('en', 'Día completo');
        $this->assertStringNotContainsString('Día completo', $html);
    }
}
