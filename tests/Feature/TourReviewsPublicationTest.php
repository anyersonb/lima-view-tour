<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Extremo a extremo: la ficha pública del tour (TourController::show()) solo
 * debe mostrar/agregar reseñas published() (is_active=true AND
 * status=approved). Y el formulario público "Añade una valoración"
 * (tours.review.store) debe insertar con status=pending explícito, sin
 * publicarse solo.
 */
class TourReviewsPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge(['is_published' => true], $overrides));
    }

    private function review(Tour $tour, array $overrides = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'tour_id'   => $tour->id,
            'name'      => 'Viajero visible',
            'quote_es'  => 'Comentario de prueba con más de diez caracteres.',
            'rating'    => 5,
            'source'    => 'Web',
            'is_active' => true,
            'status'    => 'approved',
        ], $overrides));
    }

    public function test_tour_show_reviewstats_never_counts_a_pending_review(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima-reviews']);
        $this->review($tour, ['name' => 'Aprobada', 'status' => 'approved', 'is_active' => true]);
        $this->review($tour, ['name' => 'Pendiente', 'status' => 'pending', 'is_active' => false]);

        $this->get("/es/tours/detalle/{$tour->slug}")->assertOk();

        $stats = $tour->fresh()->reviewStats();
        $this->assertSame(1, $stats['total']);
    }

    public function test_tour_show_html_does_not_leak_a_pending_reviewers_name(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima-pending-leak']);
        $this->review($tour, ['name' => 'Reseña Aprobada Visible', 'status' => 'approved', 'is_active' => true]);
        $this->review($tour, ['name' => 'Reseña Pendiente Secreta', 'status' => 'pending', 'is_active' => false]);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();

        $this->assertStringContainsString('Reseña Aprobada Visible', $html);
        $this->assertStringNotContainsString('Reseña Pendiente Secreta', $html);
    }

    public function test_public_submission_endpoint_inserts_as_pending_and_does_not_self_publish(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima-submit']);

        $this->post(route('tours.review.store', ['locale' => 'es', 'slug' => $tour->slug]), [
            'rating'  => 5,
            'name'    => 'Nuevo Viajero',
            'email'   => 'nuevo@example.com',
            'comment' => 'Un comentario recién enviado desde el formulario público.',
            'website' => '', // honeypot vacío
        ])->assertRedirect();

        $review = Testimonial::where('name', 'Nuevo Viajero')->firstOrFail();

        $this->assertSame('pending', $review->status);
        $this->assertFalse((bool) $review->is_active);
        $this->assertFalse(Testimonial::published()->whereKey($review->id)->exists());
    }

    /**
     * Diagnóstico del 500 reportado por CRO (POST por curl, sin navegador):
     * con datos válidos y honeypot vacío, el endpoint jamás debe devolver 500
     * — solo redirect. Si este test pasa, el 500 fue artefacto de curl
     * (headers/CSRF/cookie mal armados a mano), no un bug del endpoint.
     */
    public function test_public_submission_endpoint_never_returns_a_server_error(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima-no-500']);

        $response = $this->post(route('tours.review.store', ['locale' => 'es', 'slug' => $tour->slug]), [
            'rating'  => 4,
            'name'    => 'Otro Viajero',
            'email'   => 'otro@example.com',
            'comment' => 'Comentario válido de más de diez caracteres para pasar la regla min.',
            'website' => '',
        ]);

        $this->assertLessThan(500, $response->getStatusCode());
        $response->assertRedirect();
    }

    /** El honeypot relleno (bot) no debe crear ninguna reseña. */
    public function test_honeypot_filled_creates_no_review(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima-honeypot']);

        $this->post(route('tours.review.store', ['locale' => 'es', 'slug' => $tour->slug]), [
            'rating'  => 5,
            'name'    => 'Bot Spammer',
            'email'   => 'bot@example.com',
            'comment' => 'Comentario de relleno enviado por un bot automatizado cualquiera.',
            'website' => 'https://spam.example.com', // honeypot relleno → debe rechazar
        ]);

        $this->assertFalse(Testimonial::where('name', 'Bot Spammer')->exists());
    }
}
