<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\TourResource;
use App\Models\Page;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Incidente 30/08/2026: el commit 7791956 (25/08) añadió
 * `$recordRouteKeyName = 'id'` a TourResource y PageResource para que
 * renombrar el slug no invalide la URL de administración. Pero en Filament
 * 3.2 esa propiedad solo está cableada en la RESOLUCIÓN de rutas
 * (Resource::resolveRecordRouteBinding() → getRecordRouteKeyName()): la
 * GENERACIÓN (Resource::getUrl()) nunca la consulta, arma la ruta con
 * route(..., ['record' => $model]) y Laravel llama a $model->getRouteKey(),
 * que en Tour y Page devuelve el slug.
 *
 * Resultado sin el fix: el panel pintaba /admin/tours/{slug}/edit y la
 * resolución buscaba ese slug por id → 404 en el 100% de tours y páginas.
 *
 * Este test prueba las DOS mitades a la vez con una petición HTTP real —NO
 * Livewire::test(EditTour::class, ['record' => $id]), que monta el
 * componente pasando el id directamente y se salta tanto la generación como
 * la resolución de la URL, así que no habría detectado el bug—:
 *
 *  1. la URL que genera el Resource contiene el id, no el slug;
 *  2. abrir ESA URL con una request real resuelve el registro correcto.
 */
class RecordRouteKeyNameTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_the_tour_edit_url_is_keyed_by_id_and_resolves(): void
    {
        $this->actingAs($this->admin());

        $tour = Tour::factory()->create([
            'slug' => 'tour-de-prueba-route-key',
            'title_es' => 'Tour de prueba route key',
        ]);

        $url = TourResource::getUrl('edit', ['record' => $tour]);

        $this->assertStringContainsString('/admin/tours/'.$tour->id.'/edit', $url);
        $this->assertStringNotContainsString($tour->slug, $url);

        // El título vive dentro de una Tab: Filament solo lo vuelca como texto
        // plano visible en la tab activa; el slug, en cambio, siempre se
        // pinta como texto plano en el helperText del campo. Es una prueba
        // más estable de que se abrió ESTE registro que el título.
        $this->get($url)
            ->assertOk()
            ->assertSee($tour->slug);
    }

    public function test_the_page_edit_url_is_keyed_by_id_and_resolves(): void
    {
        $this->actingAs($this->admin());

        $page = Page::create([
            'slug' => 'pagina-de-prueba-route-key',
            'title_es' => 'Página de prueba route key',
        ]);

        $url = PageResource::getUrl('edit', ['record' => $page]);

        $this->assertStringContainsString('/admin/pages/'.$page->id.'/edit', $url);
        $this->assertStringNotContainsString($page->slug, $url);

        $this->get($url)
            ->assertOk()
            ->assertSee($page->slug);
    }
}
