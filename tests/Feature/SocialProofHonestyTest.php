<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * H1 (2026-11-18): la ficha NO inventa prueba social.
 *  - Contador = adultos+niños de reservas confirmadas/pagadas, 30 días.
 *  - < umbral (5) no se muestra nada; el "30" falso ya no existe.
 *  - "Últimos cupos" no aparece (no hay dato real de cupo por fecha).
 */
class SocialProofHonestyTest extends TestCase
{
    use RefreshDatabase;

    private function tour(): Tour
    {
        return Tour::factory()->create(['price' => 150.00, 'is_published' => true]);
    }

    private function book(Tour $tour, int $adults, int $children, array $over = []): void
    {
        Booking::factory()->create(array_merge([
            'tour_id' => $tour->id,
            'adults' => $adults,
            'children' => $children,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ], $over));
    }

    private function page(Tour $tour): string
    {
        Cache::flush();

        return $this->get(route('tours.show', ['locale' => 'es', 'slug' => $tour->slug]))
            ->assertOk()
            ->getContent();
    }

    public function test_sin_reservas_no_muestra_contador_ni_el_30_falso(): void
    {
        $html = $this->page($this->tour());

        $this->assertStringNotContainsString('viajeros reservaron este tour', $html);
        $this->assertStringNotContainsString('Últimos cupos', $html);
    }

    public function test_de_0_a_4_viajeros_no_muestra_nada(): void
    {
        $tour = $this->tour();
        $this->book($tour, 2, 2); // 4

        $this->assertStringNotContainsString('viajeros reservaron este tour', $this->page($tour));
    }

    public function test_5_o_mas_muestra_el_numero_real(): void
    {
        $tour = $this->tour();
        $this->book($tour, 3, 1);
        $this->book($tour, 2, 1); // 7

        $html = $this->page($tour);

        $this->assertStringContainsString('<strong>7</strong> viajeros reservaron este tour en los últimos 30 días', $html);
        $this->assertStringContainsString('data-ga-view="social_proof_view"', $html);
    }

    public function test_ignora_pendientes_canceladas_viejas_y_de_otro_tour(): void
    {
        $tour = $this->tour();
        $this->book($tour, 3, 0);
        $this->book($tour, 9, 0, ['status' => 'pending', 'payment_status' => 'pending']);
        $this->book($tour, 9, 0, ['status' => 'cancelled', 'payment_status' => 'paid']);
        $this->book($tour, 9, 0, ['created_at' => now()->subDays(45)]);
        $this->book($this->tour(), 9, 0);

        $this->assertStringNotContainsString('viajeros reservaron este tour', $this->page($tour)); // 3 < 5
    }

    public function test_ultimos_cupos_no_aparece_ni_con_muchas_reservas(): void
    {
        $tour = $this->tour();
        $this->book($tour, 10, 0);

        $html = $this->page($tour);

        $this->assertStringNotContainsString('Últimos cupos', $html);
        $this->assertStringNotContainsString('Last spots', $html);
    }

    public function test_el_conteo_se_cachea(): void
    {
        $tour = $this->tour();
        $this->book($tour, 6, 0);
        $this->assertSame(6, \App\Support\SocialProof::travelers($tour));

        $this->book($tour, 6, 0); // no se refleja hasta que expire el cache
        $this->assertSame(6, \App\Support\SocialProof::travelers($tour));
    }
}
