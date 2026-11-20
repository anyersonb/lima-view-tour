<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\OfferResource\Pages\CreateOffer;
use App\Models\Offer;
use App\Models\User;
use App\Support\HomeSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Ofertas → banda promo del home: enlace seguro y reflejo en los datos del front. */
class OfferPromoFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_crear_oferta_activa_aparece_en_la_banda_promo(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'title_es' => 'Oferta de prueba QA',
                'cta_label_es' => 'Ver tours',
                'cta_url' => '/es/tours',
                'is_active' => true,
                'order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        app()->setLocale('es');
        $this->assertSame('Oferta de prueba QA', HomeSections::promo()['title']);
    }

    public function test_enlace_peligroso_es_rechazado(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'title_es' => 'Oferta mala',
                'cta_label_es' => 'Ver',
                'cta_url' => 'javascript:alert(1)',
                'is_active' => true,
                'order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['cta_url']);

        $this->assertSame(0, Offer::count());
    }
}
