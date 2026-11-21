<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Home TAL CUAL (21-nov): JSON de las ventanas.
 *  GET /{locale}/api/tours/{slug}/sheet   · /api/tours/finder · /api/reviews
 */
class HomeSheetApiTest extends TestCase
{
    use RefreshDatabase;

    private Region $lima;

    private Region $ica;

    private Category $historia;

    private Category $aventura;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('home-api');

        $this->lima = Region::create(['name_es' => 'Lima', 'name_en' => 'Lima', 'slug' => 'lima', 'is_active' => true, 'order' => 1]);
        $this->ica = Region::create(['name_es' => 'Ica', 'name_en' => 'Ica', 'slug' => 'ica', 'is_active' => true, 'order' => 2]);
        $this->historia = Category::create(['name_es' => 'Historia', 'name_en' => 'History', 'slug' => 'historia', 'is_active' => true, 'order' => 1]);
        $this->aventura = Category::create(['name_es' => 'Aventura', 'name_en' => 'Adventure', 'slug' => 'aventura', 'is_active' => true, 'order' => 2]);
    }

    private function tour(array $o = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'title_es' => 'Tour base', 'duration' => '4 horas', 'group_type' => 'Grupal',
            'region_id' => $this->lima->id, 'category_id' => $this->historia->id,
            'is_published' => true,
        ], $o));
    }

    // ─── sheet ───────────────────────────────────────────────────────────

    public function test_sheet_estructura_y_datos_reales(): void
    {
        Setting::set('whatsapp', '51999111222');
        $t = $this->tour([
            'title_es' => 'City Tour Lima con Catacumbas', 'subtitle_es' => 'Centro histórico',
            'description_es' => '<p>Recorre el <b>centro</b> &amp; más.</p>',
            'price' => 30, 'price_before' => 40, 'departure_time' => '08:30',
            'includes_es' => ['Guía', 'Entradas'], 'excludes_es' => ['Propinas'],
            'card_title_es' => 'City Tour + Catacumbas', 'card_location_es' => 'LIMA CENTRO',
        ]);

        $res = $this->getJson('/es/api/tours/'.$t->slug.'/sheet')->assertOk();
        $d = $res->json('data');

        $this->assertSame($t->id, $d['id']);
        $this->assertSame('City Tour Lima con Catacumbas', $d['title']);
        $this->assertSame('City Tour + Catacumbas', $d['card_title']);
        $this->assertSame('LIMA CENTRO', $d['location']);
        $this->assertSame('Centro histórico', $d['subtitle']);
        $this->assertSame('Recorre el centro & más.', $d['summary']); // texto plano
        $this->assertSame(['slug' => 'lima', 'name' => 'Lima'], $d['region']);
        $this->assertSame('4 horas', $d['duration']);
        $this->assertSame('08:30', $d['departure_time']);
        $this->assertSame(['Guía', 'Entradas'], $d['includes']);
        $this->assertSame(['Propinas'], $d['excludes']);
        $this->assertEquals(30, $d['price']['amount']);
        $this->assertEquals(40, $d['price']['before']);
        $this->assertSame(25, $d['price']['discount_percent']);
        $this->assertSame('now', $d['price']['kind']);
        $this->assertNull($d['rating']);                 // sin reseñas reales: null, no inventado
        $this->assertNotEmpty($d['gallery']);
        $this->assertSame(url('/es/tours/detalle/'.$t->slug), $d['urls']['page']);
        $this->assertStringEndsWith('#seccion-reserva', $d['urls']['dates']);
        // WhatsApp OFICIAL de Ajustes + mensaje prellenado con el tour.
        $this->assertStringStartsWith('https://wa.me/51999111222?text=', $d['urls']['whatsapp']);
        $this->assertStringContainsString(rawurlencode('City Tour Lima con Catacumbas'), $d['urls']['whatsapp']);
        // Flujo reservar.
        $this->assertSame(url('/es/carrito/agregar'), $d['reserve']['url']);
        $this->assertSame(url('/es/carrito'), $d['reserve']['cart_url']);
        $this->assertSame($t->id, $d['reserve']['fields']['tour_id']);
        $this->assertSame(20, $d['reserve']['adults_max']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $d['reserve']['min_date']);
    }

    public function test_sheet_es_cacheable_sin_cookies_y_sin_campos_internos(): void
    {
        $t = $this->tour();
        $res = $this->getJson('/es/api/tours/'.$t->slug.'/sheet')->assertOk();

        $this->assertStringContainsString('public', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', $res->headers->get('Cache-Control'));
        $this->assertEmpty($res->headers->getCookies(), 'la respuesta cacheable no debe fijar cookies');

        $keys = array_keys($res->json('data'));
        foreach (['seo_title', 'seo_keywords', 'max_capacity', 'bookings', 'comparison', 'rating_internal'] as $internal) {
            $this->assertNotContains($internal, $keys);
        }
    }

    public function test_sheet_404_json_para_no_publicado_borrado_o_inexistente(): void
    {
        $draft = $this->tour(['is_published' => false]);
        $gone = $this->tour();
        $gone->delete();

        foreach ([$draft->slug, $gone->slug, 'no-existe'] as $slug) {
            $this->getJson('/es/api/tours/'.$slug.'/sheet')->assertNotFound()->assertJson(['message' => 'not_found']);
        }
    }

    public function test_sheet_idioma_en_y_precio_privado_por_grupo(): void
    {
        $t = $this->tour(['title_en' => 'Lima at your pace', 'group_type' => 'Privado', 'price' => 200]);

        $d = $this->getJson('/en/api/tours/'.$t->slug.'/sheet')->assertOk()->json('data');

        $this->assertSame('Lima at your pace', $d['title']);
        $this->assertSame('group', $d['price']['unit']);
        $this->assertSame('private', $d['price']['kind']);
        $this->assertStringContainsString('/en/', $d['urls']['page']);
        $this->assertStringContainsString(rawurlencode('Hello'), $d['urls']['whatsapp']);
    }

    public function test_sheet_json_escapa_html_del_titulo(): void
    {
        $t = $this->tour(['title_es' => '<script>alert(1)</script> & "x"']);
        $res = $this->getJson('/es/api/tours/'.$t->slug.'/sheet')->assertOk();

        $this->assertStringNotContainsString('<script>', $res->getContent());   // < en el cuerpo
        $this->assertSame('<script>alert(1)</script> & "x"', $res->json('data.title')); // decodifica igual
    }

    public function test_sheet_expone_rating_real_y_pickup(): void
    {
        $t = $this->tour(['pickup_included' => true]);
        Testimonial::create(['tour_id' => $t->id, 'name' => 'A', 'quote_es' => 'Muy bien todo', 'rating' => 5, 'is_active' => true, 'status' => 'approved']);
        Testimonial::create(['tour_id' => $t->id, 'name' => 'B', 'quote_es' => 'Pendiente de moderar', 'rating' => 1, 'is_active' => false, 'status' => 'pending']);

        $d = $this->getJson('/es/api/tours/'.$t->slug.'/sheet')->json('data');

        $this->assertEquals(['rating' => 5, 'count' => 1], $d['rating']);
        $this->assertTrue($d['pickup']['included']);
    }

    // ─── flujo reservar desde la ventana ─────────────────────────────────

    public function test_reservar_desde_la_ventana_agrega_al_carrito_y_valida_fecha(): void
    {
        $t = $this->tour(['price' => 30]);
        $reserve = $this->getJson('/es/api/tours/'.$t->slug.'/sheet')->json('data.reserve');

        // Fecha válida (la primera reservable que informa el endpoint) -> success y el carrito la lleva.
        $ok = $this->postJson($reserve['url'], [
            'tour_id' => $reserve['fields']['tour_id'], 'travel_date' => $reserve['min_date'], 'adults' => 2, 'children' => 0,
        ])->assertOk()->assertJson(['success' => true, 'count' => 1]);
        $this->assertSame(url('/es/carrito'), $reserve['cart_url']);
        $this->get($reserve['cart_url'])->assertOk();

        // Fecha pasada o basura -> 422 con error en travel_date (nunca 500).
        $this->postJson($reserve['url'], ['tour_id' => $t->id, 'travel_date' => '2020-01-01', 'adults' => 2, 'children' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['travel_date']);
        $this->postJson($reserve['url'], ['tour_id' => $t->id, 'travel_date' => $reserve['min_date'], 'adults' => 99, 'children' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['adults']);

        // Fecha bloqueada por el admin: aparece en blocked_dates y el servidor la rechaza.
        // Insert directo con 'Y-m-d' (como una columna DATE de MySQL); el cast del modelo guardaría hora en SQLite.
        \Illuminate\Support\Facades\DB::table('blocked_dates')->insert([
            'date' => \Illuminate\Support\Carbon::parse($reserve['min_date'])->addDay()->toDateString(),
            'tour_id' => $t->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $blocked = $this->getJson('/es/api/tours/'.$t->slug.'/sheet')->json('data.reserve.blocked_dates');
        $this->assertCount(1, $blocked);
        $this->postJson($reserve['url'], ['tour_id' => $t->id, 'travel_date' => $blocked[0], 'adults' => 2, 'children' => 0])
            ->assertStatus(422)->assertJson(['success' => false]);
    }

    // ─── finder ──────────────────────────────────────────────────────────

    private function finder(string $qs): array
    {
        return $this->getJson('/es/api/tours/finder?'.$qs)->assertOk()->json('data');
    }

    public function test_finder_ordena_por_afinidad_y_marca_exactos(): void
    {
        $city = $this->tour(['title_es' => 'City Tour Lima', 'order' => 1]);                       // Lima · Historia · medio día
        $pach = $this->tour(['title_es' => 'Pachacámac', 'category_id' => $this->aventura->id, 'order' => 2]); // Lima · Aventura
        $ica = $this->tour(['title_es' => 'Paracas', 'region_id' => $this->ica->id, 'category_id' => $this->aventura->id, 'duration' => 'Full Day', 'order' => 3]);

        $d = $this->finder('duracion=medio-dia&experiencia=historia&destino=lima');

        $this->assertTrue($d['has_exact']);
        $this->assertSame(1, $d['exact_count']);
        $this->assertSame([$city->id, $pach->id, $ica->id], array_column($d['results'], 'id'));
        $this->assertSame([true, false, false], array_column($d['results'], 'exact'));
        // Puntajes del prototipo: 7+5+5 = 17 ; Lima+medio día pero no tema = 7-3+5 = 9 ; nada = -6-3-3 = -12
        $this->assertSame([17, 9, -12], array_column($d['results'], 'score'));
        $this->assertSame(['duracion', 'experiencia', 'destino'], array_column($d['applied'], 'param'));
        $this->assertSame(['Medio día (hasta 5 horas)', 'Historia', 'Lima'], array_column($d['applied'], 'label'));
    }

    public function test_finder_sin_coincidencia_exacta_devuelve_alternativas(): void
    {
        $this->tour(['title_es' => 'City Tour Lima']);
        $this->tour(['title_es' => 'Pachacámac']);

        // Ningún tour es de Ica + Aventura + 2 días: aun así hay resultados (alternativas).
        $d = $this->finder('destino=ica&experiencia=aventura&duracion=2-mas-dias');

        $this->assertFalse($d['has_exact']);
        $this->assertSame(0, $d['exact_count']);
        $this->assertCount(2, $d['results']);
        $this->assertSame([false, false], array_column($d['results'], 'exact'));
        $this->assertNotNull($d['whatsapp_url']);
        $this->assertStringContainsString('/es/tours?', $d['see_all_url']);
    }

    public function test_finder_texto_libre_exacto_primero_y_resto_como_alternativas(): void
    {
        $a = $this->tour(['title_es' => 'Catacumbas de Lima', 'order' => 5]);
        $this->tour(['title_es' => 'Otro paseo', 'order' => 1]);

        $d = $this->finder('q=catacumbas');

        $this->assertSame($a->id, $d['results'][0]['id']);
        $this->assertTrue($d['results'][0]['exact']);
        $this->assertFalse($d['results'][1]['exact']);
        $this->assertSame('catacumbas', $d['query']['q']);
    }

    public function test_finder_sin_filtros_lista_todos_como_exactos_y_respeta_limit(): void
    {
        foreach (range(1, 6) as $i) {
            $this->tour(['title_es' => "Tour $i", 'order' => $i]);
        }

        $d = $this->finder('');
        $this->assertFalse($d['has_filters']);
        $this->assertCount(4, $d['results']);                 // por defecto 4 (como el prototipo)
        $this->assertSame(6, $d['exact_count']);

        $this->assertCount(6, $this->finder('limit=12')['results']);
        $this->assertCount(1, $this->finder('limit=0')['results']);     // mínimo 1
        $this->assertCount(4, $this->finder('limit=abc')['results']);   // inválido: por defecto
        $this->assertCount(6, $this->finder('limit=999')['results']);   // tope 12
    }

    public function test_finder_whitelist_ignora_valores_y_parametros_extra(): void
    {
        $this->tour(['title_es' => 'Solo uno']);

        $d = $this->finder('destino=../../etc/passwd&experiencia=noexiste&duracion=7-dias&q[]=x&evil=1&destino[]=lima');

        $this->assertFalse($d['has_filters']);
        $this->assertSame([null, null, null, null], array_values($d['query']));
        $this->assertCount(1, $d['results']);

        $d = $this->finder('q='.urlencode("' OR '1'='1"));
        $this->assertTrue($d['has_filters']);
        $this->assertFalse($d['has_exact']);                  // LIKE escapado: no es inyección
    }

    public function test_finder_no_incluye_tours_no_publicados_ni_campos_internos(): void
    {
        $this->tour(['title_es' => 'Visible']);
        $this->tour(['title_es' => 'Borrador', 'is_published' => false]);

        $d = $this->finder('');

        $this->assertSame(1, $d['total_published']);
        $this->assertSame(['Visible'], array_column($d['results'], 'title'));
        $this->assertArrayNotHasKey('seo_title', $d['results'][0]);
        $this->assertArrayHasKey('sheet_url', $d['results'][0]);
    }

    public function test_finder_sin_tours_devuelve_lista_vacia(): void
    {
        $d = $this->finder('q=lima');

        $this->assertSame([], $d['results']);
        $this->assertFalse($d['has_exact']);
    }

    // ─── reviews ─────────────────────────────────────────────────────────

    private function review(array $o = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'name' => 'Viajero', 'quote_es' => 'Excelente experiencia', 'rating' => 5,
            'is_active' => true, 'status' => 'approved', 'is_featured' => false, 'source' => 'Google',
        ], $o));
    }

    public function test_reviews_estructura_filtros_fotos_y_paginacion(): void
    {
        $t = $this->tour();
        $this->review(['name' => 'Con fotos', 'tour_id' => $t->id, 'photos' => ['reviews/a.jpg', 'reviews/b.jpg'], 'avatar' => 'avatars/x.jpg', 'submitter_email' => 'secreto@x.com']);
        $this->review(['name' => 'Trip', 'source' => 'Tripadvisor', 'rating' => 4]);
        $this->review(['name' => 'Oculta', 'status' => 'pending', 'is_active' => false]);

        $d = $this->getJson('/es/api/reviews')->assertOk()->json('data');

        $this->assertSame(2, $d['summary']['total']);
        $this->assertSame(4.5, $d['summary']['average']);
        $this->assertSame(2, $d['pagination']['total']);
        $this->assertTrue($d['filters']['show_platform_filter']);
        $this->assertTrue($d['filters']['show_photos_filter']);
        $this->assertSame(1, $d['filters']['photos_count']);

        $card = collect($d['cards'])->firstWhere('name', 'Con fotos');
        $this->assertSame('google', $card['platform']);
        $this->assertCount(2, $card['photo_items']);
        $this->assertArrayHasKey('thumb', $card['photo_items'][0]);
        $this->assertArrayHasKey('full', $card['photo_items'][0]);
        $this->assertNotNull($card['avatar_url']);
        $this->assertSame('lima', $card['destination']);
        $this->assertSame('Lima', $card['destination_name']);
        $this->assertStringNotContainsString('secreto@x.com', json_encode($d));      // email nunca sale
        $this->assertStringNotContainsString('Oculta', json_encode($d));            // no moderada: no sale
        $this->assertEmpty($this->getJson('/es/api/reviews')->headers->getCookies());
    }

    public function test_reviews_filtros_whitelist(): void
    {
        $t = $this->tour();
        $this->review(['name' => 'G', 'tour_id' => $t->id, 'photos' => ['reviews/a.jpg']]);
        $this->review(['name' => 'T', 'source' => 'Tripadvisor']);

        $names = fn (string $qs) => array_column($this->getJson('/es/api/reviews?'.$qs)->assertOk()->json('data.cards'), 'name');

        $this->assertSame(['T'], $names('plataforma=tripadvisor'));
        $this->assertSame(['G'], $names('fotos=1'));
        $this->assertSame(['G'], $names('destino=lima'));
        $this->assertEqualsCanonicalizing(['G', 'T'], $names('plataforma=hackeado'));   // valor fuera de lista: se ignora
        $this->assertEqualsCanonicalizing(['G', 'T'], $names('destino[]=lima&fotos=si'));
        $this->assertSame(1, $this->getJson('/es/api/reviews?pagina=999')->json('data.pagination.page'));
    }

    public function test_reviews_sin_datos_devuelve_estructura_vacia(): void
    {
        $d = $this->getJson('/es/api/reviews')->assertOk()->json('data');

        $this->assertNull($d['summary']);
        $this->assertSame([], $d['cards']);
        $this->assertSame(0, $d['pagination']['total']);
    }

    // ─── rate limit ──────────────────────────────────────────────────────

    public function test_rate_limit_60_por_minuto_por_ip(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/es/api/reviews')->assertOk();
        }

        $this->getJson('/es/api/reviews')->assertStatus(429);
        $this->getJson('/es/api/tours/finder')->assertStatus(429);   // el límite es compartido por IP
    }

    public function test_solo_get_los_endpoints_no_aceptan_escrituras(): void
    {
        $t = $this->tour();

        $this->postJson('/es/api/tours/finder')->assertStatus(405);
        $this->postJson('/es/api/tours/'.$t->slug.'/sheet')->assertStatus(405);
        $this->deleteJson('/es/api/reviews')->assertStatus(405);
    }
}
