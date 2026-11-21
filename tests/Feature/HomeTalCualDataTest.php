<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Tour;
use App\Support\HomeFinalSections;
use App\Support\HomeSections;
use App\Support\ToursSeoSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Home TAL CUAL (21-nov), datos: campos de tarjeta, tour estrella completo,
 * ventanas de la banda de confianza, bloques SEO en /tours y el comando de cifras.
 */
class HomeTalCualDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    private function tour(array $o = []): Tour
    {
        $lima = Region::firstOrCreate(['slug' => 'lima'], ['name_es' => 'Lima', 'name_en' => 'Lima', 'is_active' => true, 'order' => 1]);

        return Tour::factory()->create(array_merge([
            'title_es' => 'Tour de día completo al oasis de Huacachina e Islas Ballestas', 'duration' => '4 horas',
            'region_id' => $lima->id, 'is_published' => true, 'is_featured' => true, 'group_type' => 'Grupal',
        ], $o));
    }

    // ─── 1. Campos de tarjeta ────────────────────────────────────────────

    public function test_migracion_agrega_columnas_y_down_las_quita(): void
    {
        $cols = ['card_title_es', 'card_title_en', 'card_title_pt', 'card_chips_es', 'card_chips_en', 'card_chips_pt',
            'card_location_es', 'card_location_en', 'card_location_pt'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('tours', $c), $c);
        }

        $migration = require base_path('database/migrations/2026_11_21_000001_add_card_fields_to_tours_table.php');
        $migration->down();
        foreach ($cols as $c) {
            $this->assertFalse(Schema::hasColumn('tours', $c), "down() debe quitar $c");
        }

        $migration->up();
        $migration->up(); // idempotente
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('tours', $c), "up() debe recrear $c");
        }
    }

    public function test_tarjeta_sin_campos_nuevos_usa_lo_de_siempre(): void
    {
        $t = $this->tour(['pickup_included' => true, 'free_cancellation' => false]);

        $card = HomeFinalSections::pickCard($t, 'es');

        $this->assertSame($t->title_es, $card['card_title']);          // fallback al título del tour
        $this->assertSame('Lima', $card['location']);                  // fallback a la región
        $types = array_column($card['chips'], 'type');
        $this->assertContains('duration', $types);
        $this->assertContains('pickup', $types);
        $this->assertContains('group', $types);
        $this->assertSame('regular', $card['price_kind']);
        $this->assertSame('pp', $card['price_unit']);
    }

    public function test_tarjeta_con_titulo_corto_chips_curados_y_ubicacion_por_idioma(): void
    {
        $t = $this->tour([
            'card_title_es' => 'Paracas + Huacachina', 'card_title_en' => 'Paracas + Huacachina Day',
            'card_location_es' => 'ICA + PARACAS',
            'card_chips_es' => [['text' => 'Full day'], ['text' => 'Ballestas'], ['text' => 'Recojo'], ['text' => 'Aventura', 'accent' => true], ['text' => 'quinto sobra']],
            'price' => 70, 'price_before' => 100,
        ]);

        $es = HomeFinalSections::pickCard($t->fresh(), 'es');
        $this->assertSame('Paracas + Huacachina', $es['card_title']);
        $this->assertSame('ICA + PARACAS', $es['location']);
        $this->assertSame(['Full day', 'Ballestas', 'Recojo', 'Aventura'], array_column($es['chips'], 'label')); // máx. 4
        $this->assertSame([false, false, false, true], array_column($es['chips'], 'accent'));
        $this->assertSame(['custom'], array_unique(array_column($es['chips'], 'type')));
        $this->assertSame('now', $es['price_kind']);
        $this->assertSame(30, $es['discount_percent']);

        // EN: título propio; ubicación y chips caen a ES.
        $en = HomeFinalSections::pickCard($t->fresh(), 'en');
        $this->assertSame('Paracas + Huacachina Day', $en['card_title']);
        $this->assertSame('ICA + PARACAS', $en['location']);
        $this->assertSame(['Full day', 'Ballestas', 'Recojo', 'Aventura'], array_column($en['chips'], 'label'));
        $this->assertStringContainsString('/en/api/tours/', $en['sheet_url']);
    }

    public function test_tarjeta_privada_etiqueta_por_grupo(): void
    {
        $t = $this->tour(['group_type' => 'Privado', 'price' => 200, 'price_before' => null]);

        $card = HomeFinalSections::pickCard($t, 'es');

        $this->assertSame('private', $card['price_kind']);
        $this->assertSame('group', $card['price_unit']);
        $this->assertSame('private', $card['modality']);
    }

    public function test_chips_curados_vacios_o_basura_se_descartan(): void
    {
        $t = $this->tour(['card_chips_es' => [['text' => '  '], ['text' => ''], 'Catacumbas', ['accent' => true]]]);

        $this->assertSame([['text' => 'Catacumbas', 'accent' => false]], $t->fresh()->cardChips('es'));
    }

    // ─── 2. Tour estrella completo ───────────────────────────────────────

    public function test_estrella_completa_con_fallback_por_idioma_y_compatibilidad_de_hitos(): void
    {
        $t = $this->tour(['price' => 70]);
        Setting::set('home_star_tour_id', (string) $t->id);
        Setting::set('home_star_badge_es', 'EL TOUR MÁS VENDIDO EN LIMA');
        Setting::set('home_star_headline_es', 'Aventura en un solo día.');
        Setting::set('home_star_subtitle_es', 'Océano · dunas · buggy · oasis');
        Setting::set('home_star_question_es', '¿Por qué es el tour más vendido en Lima?');
        Setting::set('home_star_lead_es', 'Recorre las Islas Ballestas...');
        Setting::set('home_star_chips_es', json_encode([['text' => 'BUGGY'], ['text' => 'MIRADOR'], ['text' => 'OASIS'], ['text' => 'MAR'], ['text' => 'SOBRA']]));
        Setting::set('home_star_milestones_es', json_encode([
            ['text' => 'Buggies', 'detail' => 'Adrenalina en dunas'],
            ['text' => 'Mirador'],                 // formato anterior: solo texto = título, sin detalle
            'Oasis',                               // string suelto de versiones viejas
            ['text' => ' ', 'detail' => 'huérfano'], // sin título: se descarta
        ]));

        $s = HomeSections::star('es');

        $this->assertSame('EL TOUR MÁS VENDIDO EN LIMA', $s['badge']);
        $this->assertSame('Aventura en un solo día.', $s['headline']);
        $this->assertSame('Océano · dunas · buggy · oasis', $s['subtitle']);
        $this->assertSame('¿Por qué es el tour más vendido en Lima?', $s['question']);
        $this->assertSame(['BUGGY', 'MIRADOR', 'OASIS', 'MAR'], $s['chips']);                 // máx. 4
        $this->assertSame(['Buggies', 'Mirador', 'Oasis'], $s['milestones']);                 // contrato anterior intacto
        $this->assertSame([
            ['title' => 'Buggies', 'detail' => 'Adrenalina en dunas'],
            ['title' => 'Mirador', 'detail' => null],
            ['title' => 'Oasis', 'detail' => null],
        ], $s['milestone_items']);

        // EN sin textos propios cae a ES; PT con badge propio lo usa.
        Setting::set('home_star_badge_pt', 'O TOUR MAIS VENDIDO DE LIMA');
        $this->assertSame('Aventura en un solo día.', HomeSections::star('en')['headline']);
        $this->assertSame('O TOUR MAIS VENDIDO DE LIMA', HomeSections::star('pt')['badge']);
    }

    public function test_estrella_sin_textos_nuevos_devuelve_null_o_vacio(): void
    {
        $t = $this->tour();
        Setting::set('home_star_tour_id', (string) $t->id);

        $s = HomeSections::star('es');

        $this->assertNull($s['badge']);
        $this->assertNull($s['headline']);
        $this->assertNull($s['subtitle']);
        $this->assertNull($s['question']);
        $this->assertSame([], $s['chips']);
        $this->assertSame([], $s['milestone_items']);
    }

    // ─── 5. Beneficios con ventana ───────────────────────────────────────

    public function test_beneficios_detalle_vacio_no_es_clicable_y_con_texto_si(): void
    {
        Setting::set('stats_years', '+10');
        Setting::set('home_stat_travelers', '+13.000');
        Setting::set('stats_rating', '4.9');
        Setting::set('home_trust_cancel_title_es', 'Cancelación gratuita');
        Setting::set('home_trust_detail_cancel_es', 'Hasta 24 horas antes, según el tour.');
        Setting::set('home_trust_detail_years_es', '   '); // en blanco = sin ventana

        $items = collect(HomeFinalSections::trust('es')['items'])->keyBy('key');

        $this->assertSame('Hasta 24 horas antes, según el tour.', $items['cancellation']['detail']);
        $this->assertNull($items['years']['detail']);
        $this->assertNull($items['travelers']['detail']);
        $this->assertNull($items['rating']['detail']);

        // EN cae a ES.
        $this->assertSame('Hasta 24 horas antes, según el tour.', collect(HomeFinalSections::trust('en')['items'])->firstWhere('key', 'cancellation')['detail']);
        $this->assertNull(HomeFinalSections::trustDetail('otra-cosa', 'es'));
    }

    public function test_pago_flexible_oculto_sin_titulo_y_con_detalle_por_defecto(): void
    {
        Setting::set('home_trust_cancel_title_es', 'Cancelación gratuita');
        $this->assertNull(collect(HomeFinalSections::trust('es')['items'])->firstWhere('key', 'pay'));

        Setting::set('home_trust_pay_title_es', 'Paga el 50% para separar tu cupo');
        $pay = collect(HomeFinalSections::trust('es')['items'])->firstWhere('key', 'pay');
        $this->assertSame('Paga el 50% para separar tu cupo', $pay['value']);
        $this->assertNull($pay['label']);
        $this->assertSame(__('ui.trust_pay_detail_default', [], 'es'), $pay['detail']); // vacío = texto traducido

        Setting::set('home_trust_pay_detail_es', 'Texto propio.');
        $this->assertSame('Texto propio.', collect(HomeFinalSections::trust('es')['items'])->firstWhere('key', 'pay')['detail']);
        // EN cae al título/detalle en español (no hay EN cargado).
        $this->assertSame('Texto propio.', collect(HomeFinalSections::trust('en')['items'])->firstWhere('key', 'pay')['detail']);
        $this->assertSame('pay', collect(HomeFinalSections::trust('es')['items'])->last()['key']);
    }

    // ─── Ventanas: URLs y sugerencias ────────────────────────────────────

    public function test_api_del_home_trae_urls_whatsapp_oficial_y_sugerencias_reales(): void
    {
        Setting::set('whatsapp', '51988777666');
        $cat = Category::create(['name_es' => 'Historia', 'slug' => 'historia', 'is_active' => true, 'order' => 1]);
        $this->tour(['category_id' => $cat->id]);

        $api = HomeFinalSections::api('es');

        $this->assertStringEndsWith('/es/api/tours/finder', $api['finder_url']);
        $this->assertStringEndsWith('/es/api/reviews', $api['reviews_url']);
        $this->assertStringEndsWith('/es/api/tours/__SLUG__/sheet', $api['sheet_url_template']);
        $this->assertStringStartsWith('https://wa.me/51988777666?text=', $api['whatsapp_url']);
        $this->assertSame([['label' => 'Lima', 'q' => 'Lima'], ['label' => 'Historia', 'q' => 'Historia']], $api['suggestions']);
    }

    public function test_home_expone_homeapi_a_la_vista(): void
    {
        $this->tour();
        $res = $this->get('/es')->assertOk();

        $this->assertIsArray($res->viewData('homeApi'));
        $this->assertArrayHasKey('finder_url', $res->viewData('homeApi'));
    }

    // ─── 7. Bloques SEO que pasan a /tours ───────────────────────────────

    public function test_tours_expone_bloques_seo_al_final_y_no_con_filtro_activo(): void
    {
        $this->tour(['is_featured' => false, 'show_in_day_trips' => true, 'title_es' => 'Full Day Lima']);

        $res = $this->get('/es/tours')->assertOk();
        $seo = $res->viewData('seoSections');

        $this->assertSame('Tours de un día desde Lima', $seo['day_trips']['title']);
        $this->assertSame(['Full Day Lima'], array_column($seo['day_trips']['tours'], 'title'));
        $this->assertSame('Cómo elegir tu tour en Perú', $seo['choose']['title']);
        $this->assertCount(4, $seo['choose']['items']);
        $this->assertNotSame('', $seo['choose']['items'][0]['title']);
        $this->assertNull($seo['choose']['compare']);

        // Con búsqueda activa, sin bloque SEO.
        $this->assertNull($this->get('/es/tours?q=lima')->assertOk()->viewData('seoSections'));
    }

    public function test_bloques_seo_usan_textos_del_admin_y_el_idioma(): void
    {
        $this->tour(['is_featured' => false]);
        Setting::set('home_sec_cities_title_en', 'Day trips out of Lima');
        Setting::set('home_sec_choose_title_es', 'Elige <b>bien</b>');
        Setting::set('home_choose_items', json_encode([['title_es' => 'Paso uno', 'desc_es' => 'Texto uno', 'title_en' => 'Step one']]));

        $en = ToursSeoSections::all('en');
        $this->assertSame('Day trips out of Lima', $en['day_trips']['title']);
        $this->assertSame([['title' => 'Step one', 'desc' => 'Texto uno']], $en['choose']['items']);   // desc cae a ES

        $this->assertSame('Elige bien', ToursSeoSections::all('es')['choose']['title']);               // sin HTML
    }

    public function test_tours_sin_tours_publicados_no_rompe_y_oculta_day_trips(): void
    {
        $seo = $this->get('/es/tours')->assertOk()->viewData('seoSections');

        $this->assertNull($seo['day_trips']);
        $this->assertNotNull($seo['choose']);
    }

    // ─── Comando de cifras ───────────────────────────────────────────────

    public function test_comando_cifras_es_idempotente(): void
    {
        $this->assertSame(0, Artisan::call('lvt:home-figures'));
        $this->assertSame('+13.000', Setting::get('home_stat_travelers'));
        $this->assertSame('+10', Setting::get('stats_years'));
        $count = Setting::count();

        $this->artisan('lvt:home-figures')->expectsOutputToContain('ya vale')->assertExitCode(0);
        $this->assertSame($count, Setting::count());
        $this->assertSame('+13.000', Setting::get('home_stat_travelers'));
    }

    public function test_comando_cifras_dry_run_no_guarda_y_clear_labels_limpia(): void
    {
        Artisan::call('lvt:home-figures', ['--dry-run' => true]);
        $this->assertNull(Setting::get('home_stat_travelers'));
        $this->assertNull(Setting::get('stats_years'));

        Setting::set('home_stat_travelers', '+8,000');
        Setting::set('home_stat_travelers_label', 'Viajeros felices');
        Artisan::call('lvt:home-figures');
        $this->assertSame('Viajeros felices', Setting::get('home_stat_travelers_label')); // sin flag: no se toca
        $this->assertSame('+13.000', Setting::get('home_stat_travelers'));                // sobrescribe el valor viejo

        Artisan::call('lvt:home-figures', ['--clear-labels' => true]);
        $this->assertNull(Setting::get('home_stat_travelers_label'));
        $this->assertSame('Viajeros satisfechos', HomeSections::stats('es')['travelers']['label']); // vuelve la traducida
        $this->assertSame('Satisfied travelers', HomeSections::stats('en')['travelers']['label']);
    }
}
