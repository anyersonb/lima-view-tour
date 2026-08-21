<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ronda 3 del lote SEO i18n (2026-08-19): slug_en/slug_pt se ofrecían en el
 * panel Filament → Páginas → SEO — English/Português, pero ninguna ruta
 * pública lee esas columnas (las 5 páginas fijas usan paths literales en
 * español en routes/web.php). El editor de contenido guardaba un slug
 * traducido creyendo que cambiaba la URL y no pasaba nada — se decidió
 * sacar los campos del formulario (la columna de BD se conserva).
 *
 * Este test ejercita el HTML REALMENTE renderizado del formulario Livewire/
 * Filament (no el array de columnas del modelo): sin el fix en
 * PageResource::form(), el label "Slug traducido — English" seguía
 * apareciendo en el HTML del form.
 */
class PageResourceSlugFieldsRemovedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_the_create_page_form_html_does_not_offer_slug_en_or_slug_pt_fields(): void
    {
        $this->actingAs($this->admin());

        $html = Livewire::test(CreatePage::class)->html();

        $this->assertStringNotContainsString(
            'data.slug_en',
            $html,
            'El input slug_en sigue renderizado en el formulario de Páginas.'
        );
        $this->assertStringNotContainsString(
            'data.slug_pt',
            $html,
            'El input slug_pt sigue renderizado en el formulario de Páginas.'
        );
        $this->assertStringNotContainsString('Slug traducido — English', $html);
        $this->assertStringNotContainsString('Slug traduzido — Português', $html);
    }

    public function test_submitting_a_slug_en_value_through_the_form_is_ignored_and_not_persisted(): void
    {
        $this->actingAs($this->admin());

        // El componente Livewire no declara slug_en/slug_pt como campos del
        // formulario: intentar llenarlos vía fillForm (como si vinieran del
        // navegador) no debe reventar ni persistirse, porque el form ya no
        // los conoce.
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title_es' => 'Página sin slug traducido',
                'slug' => 'pagina-sin-slug-traducido',
                'slug_en' => 'translated-slug-en',
                'slug_pt' => 'slug-traduzido-pt',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = \App\Models\Page::where('slug', 'pagina-sin-slug-traducido')->first();

        $this->assertNotNull($page);
        $this->assertNull($page->slug_en, 'slug_en se persistió: el campo sigue vivo en el formulario.');
        $this->assertNull($page->slug_pt, 'slug_pt se persistió: el campo sigue vivo en el formulario.');
    }
}
