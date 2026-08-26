<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El slug español volvió a ser editable el 2026-08-25 (pedido: "hazlo tipo
 * WordPress editable"). Estaba ->disabled() en edición desde el 2026-07-02.
 *
 * Los dos requisitos del pedido se prueban acá:
 *  · se puede editar;
 *  · no pueden quedar dos rutas iguales, y cuando chocan el mensaje dice CUÁL
 *    es el otro contenido y dónde editarlo.
 */
class TourSlugEditableTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'is_published' => true,
            'slug_en' => null,
            'slug_pt' => null,
        ], $overrides));
    }

    /** @test */
    public function the_spanish_slug_input_is_not_disabled_on_the_edit_form(): void
    {
        $this->actingAs($this->admin());

        $tour = $this->tour(['slug' => 'city-tour-lima']);

        $html = Livewire::test(EditTour::class, ['record' => $tour->getKey()])->html();

        // Sin el fix, el input salía con `disabled` y el editor no podía tocarlo.
        $this->assertMatchesRegularExpression(
            '/wire:model[^>]*="data\.slug"(?![^>]*\bdisabled\b)/',
            $html,
            'El input del slug español sigue renderizándose deshabilitado.'
        );
    }

    /** @test */
    public function the_spanish_slug_can_be_saved_from_the_panel(): void
    {
        $this->actingAs($this->admin());

        $tour = $this->tour(['slug' => 'city-tour-lima']);

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['slug' => 'City Tour Lima Centro Histórico'])
            ->call('save')
            ->assertHasNoFormErrors();

        // Se guarda saneado, como en WordPress: minúsculas, con guiones, sin tildes.
        $this->assertSame('city-tour-lima-centro-historico', $tour->refresh()->slug);
    }

    /** @test */
    public function it_refuses_a_spanish_slug_already_used_by_another_tour(): void
    {
        $this->actingAs($this->admin());

        $this->tour(['slug' => 'laguna-humantay', 'title_es' => 'Laguna Humantay']);
        $tour = $this->tour(['slug' => 'city-tour-lima']);

        $component = Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['slug' => 'laguna-humantay'])
            ->call('save')
            ->assertHasFormErrors(['slug']);

        $errors = $component->errors()->get('data.slug');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('/es/tours/detalle/laguna-humantay', $errors[0]);
        $this->assertStringContainsString('Laguna Humantay', $errors[0], 'El error no dice cuál es el otro contenido.');
        $this->assertSame('city-tour-lima', $tour->refresh()->slug);
    }

    /** @test */
    public function it_refuses_an_english_slug_that_collides_with_another_tours_spanish_fallback(): void
    {
        $this->actingAs($this->admin());

        // Este tour no tiene slug_en: responde en /en/... por su slug español.
        // Es el choque que ningún ->unique() de una sola columna puede ver,
        // porque las columnas comparadas son distintas.
        $this->tour(['slug' => 'humantay-lake', 'slug_en' => null, 'title_es' => 'Laguna Humantay']);
        $tour = $this->tour(['slug' => 'city-tour-lima']);

        $component = Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['slug_en' => 'humantay-lake'])
            ->call('save')
            ->assertHasFormErrors(['slug_en']);

        $errors = $component->errors()->get('data.slug_en');

        $this->assertStringContainsString('/en/tours/detalle/humantay-lake', $errors[0]);
        $this->assertNull($tour->refresh()->slug_en);
    }

    /** @test */
    public function it_refuses_a_slug_that_lands_on_a_path_already_registered_in_the_code(): void
    {
        $this->actingAs($this->admin());

        $tour = $this->tour(['slug' => 'city-tour-lima']);

        // routes/web.php registra este path literal como 301 legacy ANTES de
        // /tours/detalle/{slug}: un tour con ese slug nunca llegaría a su
        // controlador.
        $reserved = 'tour-de-dia-completo-al-oasis-de-huacachina-con-buggie-privado-canam-islas-ballestas-en-paracas';

        $component = Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['slug' => $reserved])
            ->call('save')
            ->assertHasFormErrors(['slug']);

        $this->assertStringContainsString(
            'ruta fija del sitio',
            $component->errors()->get('data.slug')[0]
        );
    }

    /** @test */
    public function keeping_its_own_slug_untouched_is_not_reported_as_a_conflict(): void
    {
        $this->actingAs($this->admin());

        $tour = $this->tour(['slug' => 'city-tour-lima']);

        // El registro que se edita tiene que quedar fuera del cotejo, o cada
        // guardado chocaría consigo mismo.
        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['title_es' => 'Otro título'])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
