<?php

namespace Tests\Feature\Seo;

use App\Models\SlugRedirect;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El slug español dejó de estar blindado el 2026-08-25. La condición para
 * abrirlo era que renombrar NO dejara la URL vieja en 404: acá se verifica esa
 * condición, que es el motivo por el que el campo estuvo bloqueado un mes y
 * medio.
 */
class SlugRenameRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'is_published' => true,
        ], $overrides));
    }

    /** @test */
    public function renaming_the_spanish_slug_keeps_the_old_url_alive_with_a_301(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima']);

        $tour->update(['slug' => 'city-tour-lima-centro-historico']);

        $response = $this->get('/es/tours/detalle/city-tour-lima');

        $response->assertStatus(301);
        $response->assertRedirect('/es/tours/detalle/city-tour-lima-centro-historico');
    }

    /** @test */
    public function the_new_url_answers_200_after_the_rename(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima']);

        $tour->update(['slug' => 'city-tour-lima-centro-historico']);

        $this->get('/es/tours/detalle/city-tour-lima-centro-historico')->assertOk();
    }

    /** @test */
    public function renaming_the_spanish_slug_also_rescues_the_english_and_portuguese_urls(): void
    {
        // Sin slug_en/slug_pt propios, EN y PT responden por el slug español:
        // al renombrarlo se caen TRES URLs, no una.
        $tour = $this->tour([
            'slug' => 'laguna-humantay',
            'slug_en' => null,
            'slug_pt' => null,
        ]);

        $tour->update(['slug' => 'laguna-de-humantay']);

        $this->get('/en/tours/detalle/laguna-humantay')
            ->assertStatus(301)
            ->assertRedirect('/en/tours/detalle/laguna-de-humantay');

        $this->get('/pt/tours/detalle/laguna-humantay')
            ->assertStatus(301)
            ->assertRedirect('/pt/tours/detalle/laguna-de-humantay');
    }

    /** @test */
    public function a_locale_with_its_own_slug_is_untouched_by_a_spanish_rename(): void
    {
        $tour = $this->tour([
            'slug' => 'laguna-humantay',
            'slug_en' => 'humantay-lake',
        ]);

        $tour->update(['slug' => 'laguna-de-humantay']);

        // La URL inglesa nunca dependió del slug español, así que no hay
        // redirect que registrar para 'en'.
        $this->get('/en/tours/detalle/humantay-lake')->assertOk();

        $this->assertDatabaseMissing('slug_redirects', [
            'locale' => 'en',
            'old_slug' => 'laguna-humantay',
        ]);
    }

    /** @test */
    public function two_consecutive_renames_resolve_the_oldest_url_in_a_single_hop(): void
    {
        $tour = $this->tour(['slug' => 'uno']);

        $tour->update(['slug' => 'dos']);
        $tour->refresh()->update(['slug' => 'tres']);

        // Sin cadena 301 → 301 → 200: la tabla guarda el slug viejo y la
        // resolución lee el slug ACTUAL del registro.
        $this->get('/es/tours/detalle/uno')
            ->assertStatus(301)
            ->assertRedirect('/es/tours/detalle/tres');

        $this->get('/es/tours/detalle/dos')
            ->assertStatus(301)
            ->assertRedirect('/es/tours/detalle/tres');
    }

    /** @test */
    public function going_back_to_a_previous_slug_removes_its_redirect_instead_of_looping(): void
    {
        $tour = $this->tour(['slug' => 'original']);

        $tour->update(['slug' => 'nuevo']);
        $tour->refresh()->update(['slug' => 'original']);

        // Si la fila de 'original' sobreviviera, la ficha competiría con su
        // propio 301 y el navegador entraría en bucle.
        $this->assertDatabaseMissing('slug_redirects', [
            'locale' => 'es',
            'old_slug' => 'original',
        ]);

        $this->get('/es/tours/detalle/original')->assertOk();
        $this->get('/es/tours/detalle/nuevo')
            ->assertStatus(301)
            ->assertRedirect('/es/tours/detalle/original');
    }

    /** @test */
    public function the_old_url_of_an_unpublished_tour_stays_a_404(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima']);
        $tour->update(['slug' => 'otro-slug']);
        $tour->refresh()->update(['is_published' => false]);

        // Nunca se redirige a una ficha oculta: el scope published() manda
        // igual que en la resolución normal.
        $this->get('/es/tours/detalle/city-tour-lima')->assertNotFound();
    }

    /** @test */
    public function saving_without_touching_the_slug_does_not_create_history(): void
    {
        $tour = $this->tour(['slug' => 'city-tour-lima']);

        $tour->update(['title_es' => 'Otro título']);

        $this->assertSame(0, SlugRedirect::query()->count());
    }
}
