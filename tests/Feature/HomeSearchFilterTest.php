<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Tour;
use App\Support\TourSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buscador del home: GET /{locale}/tours?duracion=&experiencia=&destino=
 * (whitelist, opciones solo con tours publicados, estado "sin resultados").
 */
class HomeSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    private Region $lima;

    private Region $ica;

    private Category $cultural;

    private Category $aventura;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lima = Region::create(['name_es' => 'Lima', 'name_en' => 'Lima', 'slug' => 'lima', 'is_active' => true, 'order' => 1]);
        $this->ica = Region::create(['name_es' => 'Ica', 'name_en' => 'Ica', 'slug' => 'ica', 'is_active' => true, 'order' => 2]);
        $this->cultural = Category::create(['name_es' => 'Tours Culturales', 'name_en' => 'Cultural Tours', 'slug' => 'cultural', 'is_active' => true, 'order' => 1]);
        $this->aventura = Category::create(['name_es' => 'Tours de Aventura', 'name_en' => 'Adventure Tours', 'slug' => 'aventura', 'is_active' => true, 'order' => 2]);
    }

    private function tour(string $title, string $duration, ?Region $region, ?Category $cat, array $o = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'title_es' => $title,
            'duration' => $duration,
            'region_id' => $region?->id,
            'category_id' => $cat?->id,
        ], $o));
    }

    private function titles(array $query): array
    {
        $res = $this->get('/es/tours?'.http_build_query($query))->assertOk();

        return $res->viewData('tours')->pluck('title_es')->sort()->values()->all();
    }

    public function test_duration_bucket_is_derived_from_the_free_text(): void
    {
        $this->assertSame('medio-dia', TourSearch::durationBucket('4 horas'));
        $this->assertSame('medio-dia', TourSearch::durationBucket('5 Hrs'));
        $this->assertSame('1-dia', TourSearch::durationBucket('Full Day'));
        $this->assertSame('1-dia', TourSearch::durationBucket('11 Hrs'));
        $this->assertSame('1-dia', TourSearch::durationBucket('1 Día'));
        $this->assertSame('2-mas-dias', TourSearch::durationBucket('2 Días'));
        $this->assertSame('2-mas-dias', TourSearch::durationBucket('3 days'));
        $this->assertNull(TourSearch::durationBucket(''));
        $this->assertNull(TourSearch::durationBucket(null));
        $this->assertNull(TourSearch::durationBucket('Según disponibilidad'));
    }

    public function test_filters_by_duration_experience_and_destination(): void
    {
        $this->tour('City Tour', '4 horas', $this->lima, $this->cultural);
        $this->tour('Huacachina', 'Full Day', $this->ica, $this->aventura);
        $this->tour('Nazca 2D', '2 Días', $this->ica, $this->aventura);

        $this->assertSame(['City Tour'], $this->titles(['duracion' => 'medio-dia']));
        $this->assertSame(['Huacachina'], $this->titles(['duracion' => '1-dia']));
        $this->assertSame(['Nazca 2D'], $this->titles(['duracion' => '2-mas-dias']));
        $this->assertSame(['City Tour'], $this->titles(['destino' => 'lima']));
        $this->assertSame(['Huacachina', 'Nazca 2D'], $this->titles(['experiencia' => 'aventura']));
        $this->assertSame(['Huacachina'], $this->titles(['experiencia' => 'aventura', 'destino' => 'ica', 'duracion' => '1-dia']));
    }

    public function test_unpublished_tours_never_appear(): void
    {
        $this->tour('Publicado', 'Full Day', $this->ica, $this->aventura);
        $this->tour('Borrador', 'Full Day', $this->ica, $this->aventura, ['is_published' => false]);

        $this->assertSame(['Publicado'], $this->titles(['destino' => 'ica']));
    }

    public function test_invalid_params_are_ignored_whitelist(): void
    {
        $this->tour('A', 'Full Day', $this->lima, $this->cultural);
        $this->tour('B', '4 horas', $this->ica, $this->aventura);

        // Valores fuera de la whitelist, con inyección o como arreglo: se ignoran (listado completo, sin 500).
        foreach ([
            ['duracion' => 'cualquiera'],
            ['destino' => "lima' OR 1=1 --"],
            ['experiencia' => '<script>alert(1)</script>'],
            ['destino' => ['lima']],
            ['duracion' => ['medio-dia']],
            ['destino' => str_repeat('a', 500)],
            ['destino' => 'region-que-no-existe'],
        ] as $query) {
            $res = $this->get('/es/tours?'.http_build_query($query))->assertOk();
            $this->assertSame(['A', 'B'], $res->viewData('tours')->pluck('title_es')->sort()->values()->all(), json_encode($query));
            $this->assertFalse($res->viewData('searchActive'));
            $this->assertFalse($res->viewData('searchNoResults'));
            $this->assertStringNotContainsString('<script>alert(1)</script>', $res->getContent());
        }
    }

    public function test_inactive_region_slug_is_not_a_valid_filter(): void
    {
        $this->ica->update(['is_active' => false]);
        $this->tour('B', '4 horas', $this->ica, $this->aventura);

        $res = $this->get('/es/tours?destino=ica')->assertOk();
        $this->assertFalse($res->viewData('searchActive'));
    }

    public function test_no_results_exposes_empty_state_flag_and_whatsapp_exit(): void
    {
        Setting::set('whatsapp', '51999111222');
        $this->tour('City Tour', '4 horas', $this->lima, $this->cultural);

        $res = $this->get('/es/tours?duracion=2-mas-dias&destino=lima')->assertOk();

        $this->assertTrue($res->viewData('searchActive'));
        $this->assertTrue($res->viewData('searchNoResults'));
        $this->assertCount(0, $res->viewData('tours'));
        $this->assertStringStartsWith('https://wa.me/51999111222?text=', $res->viewData('searchWhatsappUrl'));
        $this->assertCount(2, $res->viewData('searchChips'));
        $this->assertSame(['duracion', 'destino'], array_column($res->viewData('searchChips'), 'param'));
    }

    public function test_results_have_no_empty_state_nor_whatsapp(): void
    {
        $this->tour('City Tour', '4 horas', $this->lima, $this->cultural);

        $res = $this->get('/es/tours?destino=lima')->assertOk();
        $this->assertFalse($res->viewData('searchNoResults'));
        $this->assertNull($res->viewData('searchWhatsappUrl'));
    }

    public function test_options_only_include_choices_with_a_published_tour(): void
    {
        $this->tour('City Tour', '4 horas', $this->lima, $this->cultural);
        $this->tour('Borrador Ica', 'Full Day', $this->ica, $this->aventura, ['is_published' => false]);

        $opts = TourSearch::options('es');

        $this->assertSame(['medio-dia'], array_column($opts['durations'], 'value'));
        $this->assertSame(['cultural'], array_column($opts['experiences'], 'value'));
        $this->assertSame(['lima'], array_column($opts['destinations'], 'value'));
        $this->assertSame(1, $opts['destinations'][0]['count']);
        $this->assertTrue($opts['has_options']);
        $this->assertSame(url('/es/tours'), $opts['action']);
        $this->assertSame('duracion', $opts['params']['duration']);
    }

    public function test_options_are_localized_and_empty_without_tours(): void
    {
        $this->assertFalse(TourSearch::options('es')['has_options']);

        $this->tour('City Tour', '4 horas', $this->lima, $this->cultural);
        $en = TourSearch::options('en');
        $this->assertSame('Cultural Tours', $en['experiences'][0]['label']);
        $this->assertSame('Half day (up to 5 hours)', $en['durations'][0]['label']);
        $this->assertSame('Meio dia (até 5 horas)', TourSearch::options('pt')['durations'][0]['label']);
    }

    public function test_home_composer_hides_search_without_options(): void
    {
        $view = view('home');
        app(\App\View\Composers\HomeComposer::class)->compose($view);
        $this->assertNull($view->getData()['homeSearch']);

        $this->tour('City Tour', '4 horas', $this->lima, $this->cultural);
        $view = view('home');
        app(\App\View\Composers\HomeComposer::class)->compose($view);
        $this->assertIsArray($view->getData()['homeSearch']);
    }
}
