<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Support\HomeSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Secciones del rediseño del home: cada una se oculta (null) sin datos reales
 * y se rellena solo con lo que cargó el admin.
 */
class HomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    private function homeData(): array
    {
        $view = view('home');
        app(\App\View\Composers\HomeComposer::class)->compose($view);

        return $view->getData();
    }

    // ─── Tour estrella ───────────────────────────────────────────────────

    public function test_star_hidden_without_tour_or_with_unpublished_or_missing_tour(): void
    {
        $this->assertNull(HomeSections::star());
        $this->assertNull($this->homeData()['homeStar']);

        $draft = Tour::factory()->create(['is_published' => false]);
        Setting::set('home_star_tour_id', (string) $draft->id);
        $this->assertNull(HomeSections::star());

        Setting::set('home_star_tour_id', '9999');
        $this->assertNull(HomeSections::star());
    }

    public function test_star_shows_with_title_fallback_milestones_limit_and_locale_fallback(): void
    {
        $tour = Tour::factory()->create(['title_es' => 'Paracas y Huacachina', 'price' => 100, 'price_before' => 125]);
        Setting::set('home_star_tour_id', (string) $tour->id);
        Setting::set('home_star_lead_es', 'Un día inolvidable');
        Setting::set('home_star_milestones_es', json_encode([
            ['text' => 'Uno'], ['text' => ' '], ['text' => 'Dos'], ['text' => 'Tres'], ['text' => 'Cuatro'], ['text' => 'Cinco'],
        ]));

        $star = HomeSections::star('es');
        $this->assertSame($tour->id, $star['tour']->id);
        $this->assertSame('Paracas y Huacachina', $star['title']); // sin titular propio: nombre real del tour
        $this->assertSame('Un día inolvidable', $star['lead']);
        $this->assertSame(['Uno', 'Dos', 'Tres', 'Cuatro'], $star['milestones']); // vacíos fuera, máx. 4
        $this->assertSame(20, $star['discount_percent']);
        $this->assertSame(125.0, $star['price_before']);

        // EN sin textos propios cae a ES; con titular propio lo usa.
        $this->assertSame('Un día inolvidable', HomeSections::star('en')['lead']);
        Setting::set('home_star_title_en', 'A day between desert and sea');
        $this->assertSame('A day between desert and sea', HomeSections::star('en')['title']);
    }

    public function test_star_without_milestones_or_lead_returns_empty_not_invented(): void
    {
        $tour = Tour::factory()->create();
        Setting::set('home_star_tour_id', (string) $tour->id);
        $star = HomeSections::star();
        $this->assertSame([], $star['milestones']);
        $this->assertNull($star['lead']);
    }

    // ─── Comparador ──────────────────────────────────────────────────────

    public function test_compare_hidden_unless_two_distinct_published_tours(): void
    {
        $a = Tour::factory()->create();
        $b = Tour::factory()->create();
        $draft = Tour::factory()->create(['is_published' => false]);

        $this->assertNull(HomeSections::compare());

        Setting::set('home_compare_tour_a', (string) $a->id);
        $this->assertNull(HomeSections::compare(), 'solo uno');

        Setting::set('home_compare_tour_b', (string) $a->id);
        $this->assertNull(HomeSections::compare(), 'el mismo tour dos veces');

        Setting::set('home_compare_tour_b', (string) $draft->id);
        $this->assertNull(HomeSections::compare(), 'segundo despublicado');

        Setting::set('home_compare_tour_b', (string) $b->id);
        $cmp = HomeSections::compare();
        $this->assertCount(2, $cmp['tours']);
        $this->assertSame([$a->id, $b->id], array_column($cmp['tours'], 'id'));
        foreach (['title', 'url', 'image', 'price', 'duration', 'category', 'region', 'discount_percent'] as $k) {
            $this->assertArrayHasKey($k, $cmp['tours'][0]);
        }
        $this->assertNotNull($this->homeData()['homeCompare']);
    }

    // ─── Cifras de confianza ─────────────────────────────────────────────

    public function test_stats_have_null_values_when_not_loaded_and_no_invented_fallbacks(): void
    {
        $stats = HomeSections::stats();
        foreach (['rating', 'travelers', 'years', 'tours'] as $k) {
            $this->assertNull($stats[$k]['value'], $k);
            $this->assertNotSame('', $stats[$k]['label']);
        }
        $this->assertNull($stats['rating']['source']);
        $this->assertFalse($stats['any']);
    }

    public function test_stats_return_settings_as_written_with_localized_source(): void
    {
        Setting::set('stats_rating', '4.9');
        Setting::set('home_stat_travelers', '+8.000');
        Setting::set('stats_years', '  ');
        Setting::set('stats_rating_source_es', 'Tripadvisor');
        Setting::set('stats_rating_source_en', 'Tripadvisor (EN)');
        Setting::set('home_stat_travelers_label', 'Viajeros');

        $es = HomeSections::stats('es');
        $this->assertSame('4.9', $es['rating']['value']);
        $this->assertSame('+8.000', $es['travelers']['value']);
        $this->assertSame('Viajeros', $es['travelers']['label']);
        $this->assertNull($es['years']['value'], 'solo espacios = vacío');
        $this->assertNull($es['tours']['value']);
        $this->assertSame('Tripadvisor', $es['rating']['source']);
        $this->assertSame('Tripadvisor (EN)', HomeSections::stats('en')['rating']['source']);
        $this->assertSame('Tripadvisor', HomeSections::stats('pt')['rating']['source'], 'cae a ES');
        $this->assertTrue($es['any']);
        $this->assertSame('Rating', HomeSections::stats('en')['rating']['label']);
    }

    // ─── Distribución de reseñas ─────────────────────────────────────────

    private function review(array $o = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'name' => 'Viajero', 'quote_es' => 'Muy bien todo el tour', 'rating' => 5,
            'is_active' => true, 'status' => 'approved', 'is_featured' => false,
        ], $o));
    }

    public function test_review_stats_null_with_zero_visible_reviews(): void
    {
        $this->assertNull(HomeSections::reviewStats());

        // Pendientes, rechazadas o inactivas NO cuentan.
        $this->review(['status' => 'pending']);
        $this->review(['status' => 'rejected']);
        $this->review(['is_active' => false]);
        $this->assertNull(HomeSections::reviewStats());
        $this->assertNull($this->homeData()['homeReviewStats']);
    }

    public function test_review_stats_distribution_and_destinations_only_with_reviews(): void
    {
        $lima = Region::create(['name_es' => 'Lima', 'slug' => 'lima', 'is_active' => true, 'order' => 1]);
        $ica = Region::create(['name_es' => 'Ica', 'slug' => 'ica', 'is_active' => true, 'order' => 2]);
        Region::create(['name_es' => 'Cusco', 'slug' => 'cusco', 'is_active' => true, 'order' => 3]); // sin reseñas

        $tLima = Tour::factory()->create(['region_id' => $lima->id]);
        $tIca = Tour::factory()->create(['region_id' => $ica->id]);

        $r1 = $this->review(['tour_id' => $tLima->id, 'rating' => 5]);
        $this->review(['tour_id' => $tLima->id, 'rating' => 4]);
        $r3 = $this->review(['tour_id' => $tIca->id, 'rating' => 5]);
        $this->review(['tour_id' => null, 'rating' => 5]); // sin tour: cuenta en total, sin destino
        $this->review(['tour_id' => $tIca->id, 'rating' => 1, 'status' => 'pending']); // no cuenta

        $s = HomeSections::reviewStats('es');

        $this->assertSame(4, $s['total']);
        $this->assertSame(4.8, $s['average']); // (5+4+5+5)/4 = 4.75 -> 4.8
        $this->assertSame(3, $s['distribution'][5]['count']);
        $this->assertSame(75, $s['distribution'][5]['percent']);
        $this->assertSame(1, $s['distribution'][4]['count']);
        $this->assertSame(0, $s['distribution'][1]['count']);
        $this->assertLessThanOrEqual(100, array_sum(array_column($s['distribution'], 'percent')));

        $this->assertSame(['lima', 'ica'], array_column($s['destinations'], 'slug'));
        $this->assertSame(2, $s['destinations'][0]['count']);
        $this->assertSame(4.5, $s['destinations'][0]['average']);
        $this->assertSame('lima', $s['destination_by_testimonial'][$r1->id]);
        $this->assertSame('ica', $s['destination_by_testimonial'][$r3->id]);

        $this->assertNotNull($this->homeData()['homeReviewStats']);
    }

    // ─── Banda promo ─────────────────────────────────────────────────────

    private function offer(array $o = []): Offer
    {
        return Offer::create(array_merge([
            'title_es' => 'Oferta real', 'cta_label_es' => 'Ver', 'is_active' => true, 'order' => 0,
        ], $o));
    }

    public function test_promo_hidden_without_active_valid_offer(): void
    {
        $this->assertNull(HomeSections::promo());

        $this->offer(['is_active' => false]);
        $this->offer(['valid_until' => now()->subDay()]);
        $this->assertNull(HomeSections::promo());
        $this->assertNull($this->homeData()['homePromo']);
    }

    public function test_promo_shows_first_valid_offer_and_skips_offer_of_unpublished_tour(): void
    {
        $draft = Tour::factory()->create(['is_published' => false]);
        $this->offer(['title_es' => 'De tour borrador', 'tour_id' => $draft->id, 'order' => 0]);
        $this->offer(['title_es' => 'Vigente', 'valid_until' => now()->addDay(), 'order' => 1, 'cta_url' => '/es/tours', 'title_en' => 'Valid']);
        $this->offer(['title_es' => 'Segunda', 'order' => 2]);

        $promo = HomeSections::promo('es');
        $this->assertSame('Vigente', $promo['title']);
        $this->assertSame('/es/tours', $promo['cta_url']);
        $this->assertNotNull($promo['valid_until']);
        $this->assertSame('Valid', HomeSections::promo('en')['title']);
    }

    public function test_promo_discards_unsafe_cta_urls(): void
    {
        $this->offer(['cta_url' => 'javascript:alert(1)']);
        $this->assertNull(HomeSections::promo()['cta_url']);
    }

    // ─── Precio tachado ──────────────────────────────────────────────────

    public function test_discount_percent_only_when_price_before_is_higher(): void
    {
        $this->assertSame(20, Tour::factory()->make(['price' => 100, 'price_before' => 125])->discount_percent);
        $this->assertSame(13, Tour::factory()->make(['price' => 65, 'price_before' => 75])->discount_percent); // 13.33
        $this->assertSame(31, Tour::factory()->make(['price' => 100, 'price_before' => 145])->discount_percent); // 31.03
        $this->assertTrue(Tour::factory()->make(['price' => 100, 'price_before' => 125])->has_discount);

        $this->assertNull(Tour::factory()->make(['price' => 100, 'price_before' => null])->discount_percent);
        $this->assertNull(Tour::factory()->make(['price' => 100, 'price_before' => 0])->discount_percent);
        $this->assertNull(Tour::factory()->make(['price' => 100, 'price_before' => 100])->discount_percent);
        $this->assertNull(Tour::factory()->make(['price' => 100, 'price_before' => 80])->discount_percent);
        $this->assertNull(Tour::factory()->make(['price' => 0, 'price_before' => 80])->discount_percent);
        $this->assertFalse(Tour::factory()->make(['price' => 100, 'price_before' => 80])->has_discount);
    }

    public function test_discount_percent_is_null_when_rounding_to_zero(): void
    {
        $this->assertNull(Tour::factory()->make(['price' => 999, 'price_before' => 1000])->discount_percent); // 0.1%
    }
}
