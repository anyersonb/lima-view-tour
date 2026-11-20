<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Support\HomeFinalSections;
use App\Support\HomeSearchChips;
use App\Support\TourSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Home FINAL (diseño aprobado 20-nov): buscador de texto (?q=), chips
 * rápidos, "Elige tu experiencia", destinos, banda de confianza y reseñas con
 * filtro por plataforma / "Con fotos". Todo dato es real: sin dato = oculto.
 */
class HomeFinalDataTest extends TestCase
{
    use RefreshDatabase;

    private Region $lima;

    private Region $cusco;

    private Category $cultural;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');

        $this->lima = Region::create(['name_es' => 'Lima', 'name_en' => 'Lima', 'slug' => 'lima', 'is_active' => true, 'order' => 1]);
        $this->cusco = Region::create(['name_es' => 'Cusco', 'name_en' => 'Cusco', 'slug' => 'cusco', 'is_active' => true, 'order' => 2]);
        $this->cultural = Category::create(['name_es' => 'Historia', 'name_en' => 'History', 'slug' => 'historia', 'is_active' => true, 'order' => 1]);
    }

    private function tour(array $o = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'title_es' => 'Tour base', 'duration' => '4 horas',
            'region_id' => $this->lima->id, 'category_id' => $this->cultural->id,
            'is_published' => true, 'is_featured' => false,
        ], $o));
    }

    private function review(array $o = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'name' => 'Viajero', 'quote_es' => 'Muy bien todo el tour', 'rating' => 5,
            'is_active' => true, 'status' => 'approved', 'is_featured' => true, 'source' => 'Google',
        ], $o));
    }

    // ─── 1. Búsqueda de texto ?q= ────────────────────────────────────────

    private function found(string $qs): array
    {
        return $this->get('/es/tours?'.$qs)->assertOk()->viewData('tours')->pluck('title_es')->sort()->values()->all();
    }

    public function test_q_busca_en_titulo_subtitulo_region_y_categoria(): void
    {
        $this->tour(['title_es' => 'Pachacámac y Museo']);
        $this->tour(['title_es' => 'Otro paseo', 'subtitle_es' => 'Entre catacumbas y plazas']);
        $this->tour(['title_es' => 'Machu Picchu', 'region_id' => $this->cusco->id, 'category_id' => null]);

        $this->assertSame(['Pachacámac y Museo'], $this->found('q=Pachac'));
        $this->assertSame(['Otro paseo'], $this->found('q=catacumbas'));
        $this->assertSame(['Machu Picchu'], $this->found('q=cusco'));
        // Categoría "Historia": los dos tours de Lima la tienen, Cusco no.
        $this->assertSame(['Otro paseo', 'Pachacámac y Museo'], $this->found('q=historia'));
        // Varias palabras: todas deben coincidir.
        $this->assertSame(['Otro paseo'], $this->found('q=lima+catacumbas'));
    }

    public function test_q_busca_en_el_idioma_actual_y_en_es(): void
    {
        $this->tour(['title_es' => 'Isla de Paracas', 'title_en' => 'Paracas Island Tour']);

        $this->get('/en/tours?q=Island')->assertOk()->assertViewHas('tours', fn ($t) => $t->count() === 1);
        $this->get('/en/tours?q=Isla')->assertOk()->assertViewHas('tours', fn ($t) => $t->count() === 1);
        // En ES no se busca en el título EN.
        $this->assertSame([], $this->found('q=Island'));
    }

    public function test_q_escapa_like_y_no_es_inyectable(): void
    {
        $this->tour(['title_es' => 'Lima 100% real']);
        $this->tour(['title_es' => 'Lima clásica']);

        // '%' y '_' son literales, no comodines.
        $this->assertSame(['Lima 100% real'], $this->found('q='.urlencode('100%')));
        $this->assertSame(['Lima 100% real'], $this->found('q='.urlencode('%'))); // solo el que contiene '%' literal
        $this->assertSame([], $this->found('q='.urlencode('Li_a')));
        // Intentos de inyección: sin error y sin resultados.
        foreach (["' OR '1'='1", "'; DROP TABLE tours; --", '" OR 1=1 --', 'Lima!%', '\\'] as $payload) {
            $this->get('/es/tours?q='.urlencode($payload))->assertOk();
        }
        $this->assertTrue(Schema::hasTable('tours'));
        $this->assertSame([], $this->found('q='.urlencode("' OR '1'='1")));
    }

    public function test_q_se_sanea_longitud_arrays_y_se_refleja_escapado(): void
    {
        $this->tour(['title_es' => 'Lima clásica']);

        $this->assertSame(80, mb_strlen(TourSearch::normalizeQuery(str_repeat('a', 200))));
        $this->assertNull(TourSearch::normalizeQuery(['x']));
        $this->assertNull(TourSearch::normalizeQuery("  \t\n "));
        $this->assertSame('Lima clásica', TourSearch::normalizeQuery("Lima\x00  \n clásica"));

        // q[]= se ignora: listado completo.
        $this->assertSame(['Lima clásica'], $this->found('q[]=zzz'));

        // Sin resultados: estado vacío + WhatsApp, y el texto sale escapado.
        Setting::set('whatsapp', '51999999999');
        $res = $this->get('/es/tours?q='.urlencode('<script>alert(1)</script>'))->assertOk();
        $res->assertViewHas('searchNoResults', true);
        $res->assertViewHas('searchWhatsappUrl', fn ($u) => str_starts_with((string) $u, 'https://wa.me/51999999999'));
        $res->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_q_es_compartible_con_chip_que_la_quita_y_se_combina_con_filtros(): void
    {
        $this->tour(['title_es' => 'Lima clásica', 'duration' => 'Full Day']);
        $this->tour(['title_es' => 'Lima nocturna', 'duration' => '3 horas']);

        $res = $this->get('/es/tours?q=lima&duracion=medio-dia')->assertOk();
        $this->assertSame(['Lima nocturna'], $res->viewData('tours')->pluck('title_es')->all());
        $this->assertSame('lima', $res->viewData('searchQuery'));
        $chips = collect($res->viewData('searchChips'));
        $qChip = $chips->firstWhere('param', 'q');
        $this->assertSame('lima', $qChip['label']);
        $this->assertStringNotContainsString('q=', $qChip['remove_url']);
        $this->assertStringContainsString('duracion=medio-dia', $qChip['remove_url']);
    }

    public function test_buscar_legacy_tambien_escapa_like(): void
    {
        $this->tour(['title_es' => 'Lima 100% real']);
        $this->tour(['title_es' => 'Lima clásica']);

        $this->get('/es/buscar?q='.urlencode('100%'))->assertOk()
            ->assertViewHas('tours', fn ($t) => $t->total() === 1);
        $this->get('/es/buscar?q='.urlencode('Li_a'))->assertOk()
            ->assertViewHas('tours', fn ($t) => $t->total() === 0);
    }

    // ─── 2. Chips rápidos ────────────────────────────────────────────────

    public function test_chips_vacios_sin_tours(): void
    {
        $this->assertSame([], HomeSearchChips::all());
    }

    public function test_chips_automaticos_solo_con_datos_reales(): void
    {
        $this->tour(['duration' => '4 horas']); // medio día, categoría Historia
        Category::create(['name_es' => 'Arqueología', 'slug' => 'arqueologia', 'is_active' => true, 'order' => 2]); // sin tours

        $chips = collect(HomeSearchChips::all());
        $this->assertSame(['duracion:medio-dia', 'categoria:historia'], $chips->pluck('key')->all());
        $this->assertSame('Medio día', $chips[0]['label']);
        $this->assertSame('Historia', $chips[1]['label']);
        $this->assertStringContainsString('/es/tours?duracion=medio-dia', $chips[0]['url']);
        // No hay tour de día completo => no hay chip "Día completo"; "Arqueología" no existe sin tours.
        $this->assertNotContains('duracion:1-dia', $chips->pluck('key')->all());
        $this->assertNotContains('categoria:arqueologia', $chips->pluck('key')->all());

        $this->tour(['duration' => 'Full Day']);
        $this->assertContains('duracion:1-dia', collect(HomeSearchChips::all())->pluck('key')->all());
    }

    public function test_chips_del_admin_reemplazan_a_los_automaticos_y_se_traducen(): void
    {
        $this->tour(['duration' => '4 horas']);
        Setting::set('home_search_chips', json_encode([
            ['label_es' => 'Historia viva', 'label_en' => 'Living history', 'type' => 'categoria', 'value' => 'historia'],
            ['label_es' => 'Sin tours', 'type' => 'destino', 'value' => 'cusco'],          // región sin tours: se omite
            ['label_es' => 'Roto', 'type' => 'categoria', 'value' => 'no-existe'],          // slug inexistente: se omite
            ['label_es' => 'Raro', 'type' => 'otro', 'value' => 'lima'],                    // tipo inválido: se omite
        ]));

        $this->assertSame(['Historia viva'], array_column(HomeSearchChips::all('es'), 'label'));
        $this->assertSame(['Living history'], array_column(HomeSearchChips::all('en'), 'label'));
        $this->assertSame(['Historia viva'], array_column(HomeSearchChips::all('pt'), 'label')); // cae a ES
    }

    public function test_chips_del_admin_sin_ninguno_valido_caen_a_los_automaticos(): void
    {
        $this->tour(['duration' => '4 horas']);
        Setting::set('home_search_chips', json_encode([['label_es' => 'X', 'type' => 'destino', 'value' => 'cusco']]));

        $this->assertContains('duracion:medio-dia', array_column(HomeSearchChips::all(), 'key'));
    }

    // ─── 3. Elige tu experiencia ─────────────────────────────────────────

    public function test_pick_tours_null_sin_destacados(): void
    {
        $this->tour(['is_featured' => false]);
        $this->assertNull(HomeFinalSections::pickTours());
    }

    public function test_pick_tours_destacados_orden_maximo_4_y_datos_reales(): void
    {
        $a = $this->tour(['title_es' => 'A', 'is_featured' => true, 'featured_order' => 2, 'badge_text' => '  MÁS VENDIDO ', 'price' => 100, 'price_before' => 125, 'pickup_included' => true, 'free_cancellation' => false, 'language' => 'Español / Inglés']);
        $b = $this->tour(['title_es' => 'B', 'is_featured' => true, 'featured_order' => 1, 'badge_text' => '', 'price' => 50, 'price_before' => 50, 'pickup_included' => false, 'free_cancellation' => false, 'language' => '', 'duration' => '', 'group_type' => '']);
        foreach (['C', 'D', 'E'] as $t) {
            $this->tour(['title_es' => $t, 'is_featured' => true, 'featured_order' => null]);
        }
        $this->tour(['title_es' => 'No destacado', 'is_featured' => false]);
        $this->tour(['title_es' => 'Despublicado', 'is_featured' => true, 'is_published' => false]);

        $pick = HomeFinalSections::pickTours();
        $this->assertCount(4, $pick);
        $this->assertSame(['B', 'A'], array_slice(array_column($pick, 'title'), 0, 2));

        [$cardB, $cardA] = [$pick[0], $pick[1]];
        // B: sin badge, sin descuento, sin chips de recojo/idioma/duración.
        $this->assertNull($cardB['badge']);
        $this->assertNull($cardB['price_before']);
        $this->assertNull($cardB['discount_percent']);
        $this->assertSame([], array_column(array_filter($cardB['chips'], fn ($c) => in_array($c['type'], ['pickup', 'language', 'duration', 'cancellation'], true)), 'type'));
        // A: datos reales.
        $this->assertSame('MÁS VENDIDO', $cardA['badge']);
        $this->assertSame(20, $cardA['discount_percent']);
        $this->assertSame(125.0, $cardA['price_before']);
        $this->assertSame('Lima', $cardA['region']);
        $types = array_column($cardA['chips'], 'type');
        $this->assertContains('pickup', $types);
        $this->assertContains('language', $types);
        $this->assertNotContains('cancellation', $types);
        $this->assertNull($cardA['rating']);
        $this->assertStringContainsString('/es/tours/detalle/', $cardA['url']);
        $this->assertStringEndsWith('#seccion-reserva', $cardA['dates_url']);
    }

    // ─── 4. Destinos ─────────────────────────────────────────────────────

    public function test_destinos_solo_regiones_activas_con_tours_en_orden_y_la_primera_es_grande(): void
    {
        $this->lima->update(['eyebrow_es' => 'EXPLORA LA CAPITAL', 'description_es' => 'Historia y mar.', 'hero_image' => 'assets/banners/lima.jpg']);
        Region::create(['name_es' => 'Ica', 'slug' => 'ica', 'is_active' => true, 'order' => 3]);      // sin tours
        Region::create(['name_es' => 'Piura', 'slug' => 'piura', 'is_active' => false, 'order' => 0]);  // inactiva
        $this->tour();
        $this->tour(['region_id' => $this->cusco->id, 'title_es' => 'Machu']);
        Tour::factory()->create(['region_id' => $this->cusco->id, 'is_published' => false, 'title_es' => 'Oculto']);

        $d = HomeFinalSections::destinations();

        $this->assertSame(['lima', 'cusco'], array_column($d, 'slug'));
        $this->assertTrue($d[0]['featured']);
        $this->assertFalse($d[1]['featured']);
        $this->assertSame('EXPLORA LA CAPITAL', $d[0]['eyebrow']);
        $this->assertSame('Historia y mar.', $d[0]['description']);
        $this->assertStringContainsString('lima.jpg', $d[0]['image']);
        $this->assertStringEndsWith('/es/tours/categoria/lima', $d[0]['url']);
        // Cusco sin imagen/eyebrow/bajada cargados: null (la vista decide ocultar).
        $this->assertNull($d[1]['image']);
        $this->assertNull($d[1]['eyebrow']);
        $this->assertNull($d[1]['description']);
        // Un tour despublicado no cuenta.
        $this->assertSame(1, $d[1]['tours_count']);
    }

    public function test_destinos_vacio_sin_tours(): void
    {
        $this->assertSame([], HomeFinalSections::destinations());
    }

    // ─── 5. Banda de confianza ───────────────────────────────────────────

    public function test_trust_null_sin_datos_y_con_datos_reales_en_orden(): void
    {
        $this->assertNull(HomeFinalSections::trust());

        Setting::set('stats_years', '+11');
        Setting::set('stats_rating', '4.9');
        Setting::set('stats_rating_source_es', 'Tripadvisor');
        $t = HomeFinalSections::trust();
        $this->assertSame(['years', 'rating'], array_column($t['items'], 'key'));
        $this->assertSame('Tripadvisor', $t['items'][1]['source']);

        // Cancelación corta por idioma (con fallback a ES).
        Setting::set('home_trust_cancel_title_es', 'Cancelación gratuita');
        Setting::set('home_trust_cancel_text_es', 'Hasta 24 h antes');
        $t = HomeFinalSections::trust();
        $this->assertSame('cancellation', $t['items'][0]['key']);
        $this->assertSame('Cancelación gratuita', $t['items'][0]['value']);
        $this->assertSame('Hasta 24 h antes', $t['items'][0]['label']);
    }

    public function test_cancelacion_cae_al_resumen_legal_y_se_oculta_si_no_hay_nada(): void
    {
        $this->assertNull(HomeFinalSections::cancellation());

        Setting::set('legal_cancellation_summary_es', 'Puedes cancelar hasta 24 horas antes sin costo.');
        $c = HomeFinalSections::cancellation('es');
        $this->assertSame('Cancelación', $c['title']);
        $this->assertSame('Puedes cancelar hasta 24 horas antes sin costo.', $c['text']);
        $this->assertSame('Cancellation', HomeFinalSections::cancellation('en')['title']);
    }

    // ─── 6. Reseñas: plataforma, fotos, avatar ───────────────────────────

    public function test_platform_key_normaliza_origenes(): void
    {
        $this->assertSame('google', HomeFinalSections::platformKey('Google Maps'));
        $this->assertSame('tripadvisor', HomeFinalSections::platformKey(' TripAdvisor '));
        $this->assertSame('viator', HomeFinalSections::platformKey('VIATOR'));
        $this->assertSame('web', HomeFinalSections::platformKey('Web'));
        $this->assertSame('web', HomeFinalSections::platformKey(null));
        $this->assertSame('web', HomeFinalSections::platformKey('Facebook'));
    }

    public function test_reviews_null_sin_tarjetas(): void
    {
        $this->assertNull(HomeFinalSections::reviews([]));
    }

    public function test_reviews_filtros_solo_con_datos_visibles(): void
    {
        $tour = $this->tour(['title_es' => 'City Tour']);
        $this->review(['source' => 'Google', 'tour_id' => $tour->id]);

        $r = HomeFinalSections::reviews(Testimonial::published()->featured()->get());
        $this->assertSame(['google'], array_column($r['platforms'], 'key'));
        $this->assertFalse($r['show_platform_filter']);   // una sola plataforma: sin filtro
        $this->assertFalse($r['show_photos_filter']);     // ninguna con fotos: sin "Con fotos"
        $this->assertSame(0, $r['photos_count']);
        $this->assertFalse($r['show_destination_filter']);

        $this->review(['source' => 'Tripadvisor', 'name' => 'Ana']);
        // Pendiente / inactiva / rechazada de Viator: no deben aparecer.
        $this->review(['source' => 'Viator', 'status' => 'pending']);
        $this->review(['source' => 'Viator', 'is_active' => false]);

        $r = HomeFinalSections::reviews(Testimonial::published()->featured()->get());
        $this->assertSame(['google', 'tripadvisor'], array_column($r['platforms'], 'key'));
        $this->assertTrue($r['show_platform_filter']);
        $this->assertNotContains('viator', array_column($r['platforms'], 'key'));

        $this->review(['source' => 'Viator']);
        $r = HomeFinalSections::reviews(Testimonial::published()->featured()->get());
        $this->assertSame(['google', 'tripadvisor', 'viator'], array_column($r['platforms'], 'key'));
    }

    public function test_reviews_fotos_has_photos_avatar_y_destino(): void
    {
        $tour = $this->tour(['title_es' => 'City Tour']);
        $this->review(['name' => 'Con fotos', 'tour_id' => $tour->id, 'avatar' => 'https://example.com/a.jpg',
            'photos' => ['reviews/1.jpg', 'reviews/2.jpg', 'reviews/3.jpg', 'reviews/4.jpg', 'reviews/5.jpg']]);
        $this->review(['name' => 'Sin fotos', 'source' => 'Tripadvisor']);

        $r = HomeFinalSections::reviews(Testimonial::published()->featured()->orderBy('id')->get());
        [$con, $sin] = $r['cards'];

        $this->assertTrue($con['has_photos']);
        $this->assertCount(4, $con['photos']);                      // máx. 4
        $this->assertStringContainsString('reviews/1.jpg', $con['photos'][0]);
        $this->assertSame('https://example.com/a.jpg', $con['avatar_url']);
        $this->assertSame('lima', $con['destination']);
        $this->assertSame('City Tour', $con['tour']['title']);

        $this->assertFalse($sin['has_photos']);
        $this->assertSame([], $sin['photos']);
        $this->assertNull($sin['avatar_url']);
        $this->assertNull($sin['destination']);
        $this->assertNull($sin['tour']);

        $this->assertTrue($r['show_photos_filter']);
        $this->assertSame(1, $r['photos_count']);
    }

    public function test_reviews_acepta_objetos_de_api_google(): void
    {
        $api = (object) ['name' => 'G User', 'rating' => 4, 'quote' => 'Great', 'source' => 'google', 'avatar' => 'https://lh3.example/p.jpg', 'country' => null, 'tour' => null, 'is_featured' => false];

        $r = HomeFinalSections::reviews([$api]);

        $this->assertSame('google', $r['cards'][0]['platform']);
        $this->assertNull($r['cards'][0]['id']);
        $this->assertFalse($r['cards'][0]['has_photos']);
        $this->assertSame('https://lh3.example/p.jpg', $r['cards'][0]['avatar_url']);
    }

    public function test_columna_photos_existe_y_guarda_json(): void
    {
        $this->assertTrue(Schema::hasColumn('testimonials', 'photos'));

        $t = $this->review(['photos' => ['reviews/a.jpg']]);
        $this->assertSame(['reviews/a.jpg'], $t->fresh()->photos);
        $this->assertCount(1, $t->fresh()->photo_urls);
    }

    // ─── Home: el composer expone todo y no rompe ────────────────────────

    public function test_home_renderiza_con_y_sin_datos(): void
    {
        $this->get('/es')->assertOk();

        $this->tour(['is_featured' => true]);
        $this->review(['photos' => ['reviews/a.jpg']]);

        $res = $this->get('/es')->assertOk();
        $res->assertViewHas('homeChips', fn ($c) => count($c) >= 1);
        $res->assertViewHas('homePick', fn ($p) => count($p) === 1);
        $res->assertViewHas('homeDestinations', fn ($d) => count($d) === 1);
        $res->assertViewHas('homeReviews', fn ($r) => $r['show_photos_filter'] === true);
        $res->assertViewHas('homeTrust');
    }
}
