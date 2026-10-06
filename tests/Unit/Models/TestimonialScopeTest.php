<?php

namespace Tests\Unit\Models;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testimonial::scopePublished() es el ÚNICO scope que debe usar cualquier
 * superficie pública. Antes de esto había dos interruptores independientes
 * (is_active / — sin status) y era fácil dejar pasar una reseña sin
 * moderar filtrando solo uno. Falsado en los dos sentidos: atrapa lo que
 * debe quedar afuera Y deja pasar lo que debe quedar adentro.
 */
class TestimonialScopeTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $overrides = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'name'      => 'Viajero',
            'quote_es'  => 'Comentario de prueba con más de diez caracteres.',
            'rating'    => 5,
            'source'    => 'Web',
            'is_active' => true,
            'status'    => 'approved',
        ], $overrides));
    }

    public function test_published_includes_active_and_approved(): void
    {
        $review = $this->make(['is_active' => true, 'status' => 'approved']);

        $this->assertTrue(Testimonial::published()->whereKey($review->id)->exists());
    }

    public function test_published_excludes_pending_even_when_active(): void
    {
        $review = $this->make(['is_active' => true, 'status' => 'pending']);

        $this->assertFalse(Testimonial::published()->whereKey($review->id)->exists());
    }

    public function test_published_excludes_rejected_even_when_active(): void
    {
        $review = $this->make(['is_active' => true, 'status' => 'rejected']);

        $this->assertFalse(Testimonial::published()->whereKey($review->id)->exists());
    }

    public function test_published_excludes_approved_but_hidden_manually(): void
    {
        // status=approved pero is_active=false (ocultada a mano por el admin):
        // el otro interruptor solo, sin este, dejaría pasar esta fila.
        $review = $this->make(['is_active' => false, 'status' => 'approved']);

        $this->assertFalse(Testimonial::published()->whereKey($review->id)->exists());
    }

    public function test_new_row_without_explicit_status_defaults_to_pending_fail_closed(): void
    {
        $review = Testimonial::create([
            'name'      => 'Sin status explícito',
            'quote_es'  => 'Comentario de prueba con más de diez caracteres.',
            'rating'    => 5,
            'source'    => 'Web',
            'is_active' => true,
            // status omitido a propósito: debe caer al default de columna.
        ]);

        $this->assertSame('pending', $review->fresh()->status);
        $this->assertFalse(Testimonial::published()->whereKey($review->id)->exists());
    }

    public function test_submitter_email_never_serializes_to_array_or_json(): void
    {
        $review = $this->make(['submitter_email' => 'viajero@example.com']);

        $this->assertArrayNotHasKey('submitter_email', $review->toArray());
        $this->assertStringNotContainsString('viajero@example.com', $review->toJson());
    }
}
