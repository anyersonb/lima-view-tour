<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TestimonialResource\Pages\ListTestimonials;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El panel de Filament es la ÚNICA excepción a Testimonial::published(): el
 * admin debe seguir viendo las reseñas pendientes/rechazadas para poder
 * moderarlas. Cubre: el listado por defecto NO las esconde, el filtro por
 * estado funciona, y las acciones masivas Aprobar/Rechazar cambian `status`.
 */
class TestimonialModerationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    private function review(array $overrides = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'name'      => 'Viajero',
            'quote_es'  => 'Comentario de prueba con más de diez caracteres.',
            'rating'    => 5,
            'source'    => 'Web',
            'is_active' => false,
            'status'    => 'pending',
        ], $overrides));
    }

    public function test_the_default_listing_does_not_hide_pending_reviews(): void
    {
        $this->actingAs($this->admin());

        $pending = $this->review(['name' => 'Pendiente Visible En Panel']);

        Livewire::test(ListTestimonials::class)
            ->assertCanSeeTableRecords([$pending]);
    }

    public function test_filtering_by_status_pending_narrows_the_table(): void
    {
        $this->actingAs($this->admin());

        $pending = $this->review(['name' => 'A', 'status' => 'pending']);
        $approved = $this->review(['name' => 'B', 'status' => 'approved', 'is_active' => true]);

        Livewire::test(ListTestimonials::class)
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved]);
    }

    public function test_bulk_approve_action_sets_status_to_approved(): void
    {
        $this->actingAs($this->admin());

        $pending = $this->review();

        Livewire::test(ListTestimonials::class)
            ->callTableBulkAction('approve', [$pending->id]);

        $this->assertSame('approved', $pending->fresh()->status);
    }

    public function test_bulk_reject_action_sets_status_to_rejected(): void
    {
        $this->actingAs($this->admin());

        $pending = $this->review();

        Livewire::test(ListTestimonials::class)
            ->callTableBulkAction('reject', [$pending->id]);

        $this->assertSame('rejected', $pending->fresh()->status);
    }
}
