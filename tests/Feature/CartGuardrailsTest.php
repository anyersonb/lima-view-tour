<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgos de la auditoría de seguridad sobre el lote de fechas de reserva
 * (2026-08-26) que no son sobre fechas en sí, sino sobre el carrito:
 *
 *  · PATCH /{locale}/carrito/{rowId} con un rowId inexistente respondía
 *    200 `{"success":true}` sin tocar nada: un fallo real pasaba por éxito.
 *  · Fundir dos filas del mismo tour en la misma fecha recortaba la suma de
 *    pasajeros a 20 con `min(20, ...)`: la diferencia desaparecía en
 *    silencio si las dos filas sumaban más de 20.
 *  · El carrito no tenía tope de filas: 400 filas en una sesión dejaban un
 *    `cart.items` de ~178 KB, copiado entero a `abandoned_carts.items`.
 */
class CartGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge(['is_published' => true], $overrides));
    }

    // ── PATCH sobre una fila que no existe ──────────────────────────────

    /** @test */
    public function patching_a_row_id_that_never_existed_returns_404_not_success(): void
    {
        $response = $this->patchJson('/es/carrito/noexiste', [
            'adults' => 1,
            'children' => 0,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function patching_a_row_already_removed_from_the_cart_returns_404(): void
    {
        $tour = $this->tour();
        $this->cart()->add($tour, 1, 0, now()->addDays(3)->toDateString());
        $rowId = $this->cart()->items()->first()['row_id'];

        $this->cart()->remove($rowId);

        $this->patchJson("/es/carrito/{$rowId}", [
            'adults' => 1,
            'children' => 0,
        ])->assertStatus(404);
    }

    // ── Fusión de filas que superaría el máximo de pasajeros ───────────

    /** @test */
    public function merging_two_rows_that_would_exceed_the_pax_limit_is_rejected_without_dropping_anyone(): void
    {
        $tour = $this->tour();
        $a = now()->addDays(4)->toDateString();
        $b = now()->addDays(5)->toDateString();

        $this->cart()->add($tour, 15, 0, $a);
        $this->cart()->add($tour, 10, 0, $b);
        $this->assertCount(2, $this->cart()->items());

        $rowB = $this->cart()->items()->firstWhere('travel_date', $b)['row_id'];

        // 15 + 10 = 25, por encima del máximo de 20 por fila.
        $response = $this->patchJson("/es/carrito/{$rowB}", [
            'adults' => 10,
            'children' => 0,
            'travel_date' => $a,
        ]);

        $response->assertStatus(422);

        $items = $this->cart()->items();

        // Antes: min(20, 15 + 10) recortaba a 20 y 5 pasajeros desaparecían
        // sin ningún aviso. Ahora el movimiento se rechaza entero: las dos
        // filas siguen intactas, con toda su gente.
        $this->assertCount(2, $items, 'Las filas se fundieron en vez de rechazar el movimiento.');
        $this->assertSame(15, $items->firstWhere('travel_date', $a)['adults']);
        $this->assertSame(10, $items->firstWhere('travel_date', $b)['adults']);
    }

    /** @test */
    public function merging_two_rows_within_the_pax_limit_still_works(): void
    {
        $tour = $this->tour();
        $a = now()->addDays(4)->toDateString();
        $b = now()->addDays(5)->toDateString();

        $this->cart()->add($tour, 12, 0, $a);
        $this->cart()->add($tour, 8, 0, $b);

        $rowB = $this->cart()->items()->firstWhere('travel_date', $b)['row_id'];

        $this->patchJson("/es/carrito/{$rowB}", [
            'adults' => 8,
            'children' => 0,
            'travel_date' => $a,
        ])->assertOk();

        $items = $this->cart()->items();
        $this->assertCount(1, $items);
        $this->assertSame(20, $items->first()['adults']);
    }

    // ── Tope de filas del carrito ───────────────────────────────────────

    /** @test */
    public function adding_a_row_beyond_the_cart_cap_is_rejected_with_a_clear_message(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->cart()->add($this->tour(), 1, 0, now()->addDays(3)->toDateString());
        }
        $this->assertCount(20, $this->cart()->items());

        $extraTour = $this->tour();

        $response = $this->postJson('/es/carrito/agregar', [
            'tour_id' => $extraTour->id,
            'adults' => 1,
            'children' => 0,
            'travel_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertFalse((bool) $response->json('success'));
        $this->assertCount(20, $this->cart()->items(), 'Se agregó una fila 21 pese al tope.');
    }

    /** @test */
    public function incrementing_an_existing_row_is_allowed_even_at_the_cart_cap(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->cart()->add($this->tour(), 1, 0, now()->addDays(3)->toDateString());
        }

        $existing = $this->cart()->items()->first();

        // Misma fila (mismo tour + misma fecha): no debe contar como fila
        // nueva, solo actualiza cantidades.
        $response = $this->postJson('/es/carrito/agregar', [
            'tour_id' => $existing['tour_id'],
            'adults' => 3,
            'children' => 0,
            'travel_date' => $existing['travel_date'],
        ]);

        $response->assertOk();
        $this->assertCount(20, $this->cart()->items());
    }

    // ── Hallazgo de seguridad 2026-08-27 · completitud del carrito ─────

    /** @test */
    public function recovering_an_abandoned_cart_snapshot_is_truncated_to_the_row_cap(): void
    {
        // MAX_ROWS solo se aplicaba en add(): cart.recover (GET por token,
        // sin throttle) restauraba el snapshot ENTERO vía CartService::replace(),
        // esquivando el tope.
        $items = [];
        for ($i = 0; $i < 25; $i++) {
            $tour = $this->tour();
            $items[] = [
                'tour_id' => $tour->id,
                'title_snapshot' => $tour->title_es,
                'unit_price' => (float) $tour->price,
                'adults' => 1,
                'children' => 0,
                'quantity' => 1,
                'subtotal' => (float) $tour->price,
                'travel_date' => now()->addDays(5)->toDateString(),
            ];
        }

        $token = \Illuminate\Support\Str::random(32);
        \App\Models\AbandonedCart::create([
            'token' => $token,
            'session_id' => 'test-session',
            'items' => $items,
            'email' => 'lead@example.com',
        ]);

        $this->get("/es/carrito/recuperar/{$token}")
            ->assertRedirectToRoute('cart.index', ['locale' => 'es']);

        $this->assertCount(
            20,
            $this->cart()->items(),
            'El snapshot de 25 filas se coló entero por cart.recover, esquivando MAX_ROWS.'
        );
    }

    /** @test */
    public function adding_a_row_with_more_than_the_pax_cap_in_a_single_call_is_rejected(): void
    {
        // MAX_PAX_PER_ROW solo se aplicaba al FUNDIR filas en update():
        // adults=20 + children=20 en un solo add() metía 40 pasajeros en
        // una fila sin pasar por ningún tope.
        $tour = $this->tour();

        $response = $this->postJson('/es/carrito/agregar', [
            'tour_id' => $tour->id,
            'adults' => 20,
            'children' => 20,
            'travel_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertFalse((bool) $response->json('success'));
        $this->assertCount(0, $this->cart()->items(), 'Se agregó una fila de 40 pasajeros pese al tope.');
    }

    /** @test */
    public function adding_a_row_within_the_pax_cap_still_works(): void
    {
        $tour = $this->tour();

        $this->postJson('/es/carrito/agregar', [
            'tour_id' => $tour->id,
            'adults' => 12,
            'children' => 8,
            'travel_date' => now()->addDays(5)->toDateString(),
        ])->assertOk();

        $this->assertCount(1, $this->cart()->items());
        $this->assertSame(20, $this->cart()->items()->first()['adults'] + $this->cart()->items()->first()['children']);
    }

    /** @test */
    public function destroying_cart_rows_is_rate_limited(): void
    {
        // cart.destroy no tenía throttle, a diferencia de store/update: se le
        // agrega el mismo throttle:60,1 por simetría. remove() no valida que
        // el rowId exista (unset silencioso), así que basta con pegarle al
        // endpoint 60 veces con un rowId inventado para agotar la cuota.
        for ($i = 0; $i < 60; $i++) {
            $this->deleteJson('/es/carrito/rowid-inexistente')->assertStatus(200);
        }

        // La request número 61 dentro del mismo minuto debe ser bloqueada.
        $this->deleteJson('/es/carrito/rowid-inexistente')->assertStatus(429);
    }
}
