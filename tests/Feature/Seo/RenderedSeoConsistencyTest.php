<?php

namespace Tests\Feature\Seo;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cierra la brecha del gate de QA que quedó sin correr (disco lleno):
 *
 *  - Hallazgo Alto de CRO (histórico): el JSON-LD autogenerado del tour
 *    declaraba Product.url / @id / offers.url con el slug ES aunque la
 *    página fuera la traducida, contradiciendo su propio <link rel=canonical>.
 *    El fix (tours/show.blade.php → slugFor($locale)) NO tenía test de render.
 *
 *    Lote B (2026-08-31) apagó el Product+TouristTrip automático de la
 *    ficha de tour (ver AutomaticJsonLdDisabledTest.php): ya no hay bloque
 *    Product que inspeccionar, así que la coherencia url/@id-vs-canonical
 *    que este test protegía dejó de ser una invariante nuestra — el
 *    marcado ahora lo escribe el equipo de SEO a mano y su contenido es
 *    responsabilidad suya. Lo que SIGUE siendo nuestro es que el propio
 *    <link rel=canonical> siga siendo correcto (layouts/app.blade.php no
 *    depende de si el SEO llenó o no el JSON-LD manual) y que no reaparezca
 *    ningún Product automático. El test de abajo quedó reconvertido a eso.
 *
 *  - Hallazgo Medio: el fallback ES de BlogPost solo estaba cubierto por un
 *    test unitario sobre el modelo, no sobre el <title> realmente emitido.
 */
class RenderedSeoConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $overrides = []): Tour
    {
        $category = Category::create([
            'name_es' => 'Cusco', 'name_en' => 'Cusco', 'name_pt' => 'Cusco',
            'slug' => 'cusco-'.uniqid(),
        ]);

        return Tour::create(array_merge([
            'category_id' => $category->id,
            'title_es' => 'Laguna Humantay',
            'title_en' => 'Humantay Lake',
            'title_pt' => 'Lagoa Humantay',
            'description_es' => 'Descripción en español.',
            'description_en' => 'Description in English.',
            'description_pt' => 'Descrição em português.',
            'price' => 120.00,
            'is_published' => true,
        ], $overrides));
    }

    /** Extrae los bloques JSON-LD del HTML y devuelve los decodificados. */
    private function jsonLdBlocks(string $html): array
    {
        preg_match_all(
            '#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si',
            $html,
            $m
        );

        $out = [];
        foreach ($m[1] as $raw) {
            $decoded = json_decode(trim($raw), true);
            $this->assertSame(
                JSON_ERROR_NONE,
                json_last_error(),
                'Hay un bloque JSON-LD que NO es parseable: '.json_last_error_msg()
            );
            $out[] = $decoded;
        }

        return $out;
    }

    /** @test */
    public function el_canonical_del_tour_traducido_sigue_siendo_correcto_y_no_hay_producto_automatico(): void
    {
        $this->tour(['slug' => 'laguna-humantay', 'slug_en' => 'humantay-lake']);

        $html = $this->get('/en/tours/detalle/humantay-lake')->assertOk()->getContent();

        // Canonical realmente emitido: esto SIGUE siendo responsabilidad
        // nuestra (layouts/app.blade.php), independiente de si el equipo de
        // SEO cargó o no un JSON-LD manual — por eso sigue protegido aquí.
        preg_match('#<link rel="canonical" href="([^"]+)"#i', $html, $c);
        $this->assertNotEmpty($c[1] ?? null, 'No se emitió <link rel="canonical">.');
        $canonical = $c[1];

        $this->assertStringContainsString('humantay-lake', $canonical);
        $this->assertStringNotContainsString('laguna-humantay', $canonical);

        // Lote B apagó el Product+TouristTrip automático: ya no podemos (ni
        // debemos) garantizar que su url/@id coincida con el canonical
        // porque ese bloque ya no existe — lo escribe el SEO a mano y su
        // contenido es responsabilidad suya. Lo que seguimos garantizando
        // es que NO reaparezca ningún Product automático.
        $product = null;
        foreach ($this->jsonLdBlocks($html) as $block) {
            $types = (array) ($block['@type'] ?? []);
            if (in_array('Product', $types, true)) {
                $product = $block;
                break;
            }
        }
        $this->assertNull($product, 'Apareció un bloque JSON-LD Product: el automático debería estar apagado (Lote B).');
    }

    /** @test */
    public function el_title_de_un_post_solo_en_espanol_no_cae_al_titulo_generico_del_sitio(): void
    {
        BlogPost::create([
            'slug' => 'articulo-solo-es',
            'title_es' => 'Guía para visitar Machu Picchu',
            'excerpt_es' => 'Resumen en español.',
            'body_es' => '<p>Cuerpo en español.</p>',
            // Columnas NOT NULL sin default: sin traducción se guardan como ''.
            'title_en' => '', 'excerpt_en' => '', 'body_en' => '',
            'title_pt' => '', 'excerpt_pt' => '', 'body_pt' => '',
            'is_published' => true,
        ]);

        $html = $this->get('/en/blog/articulo-solo-es')->assertOk()->getContent();

        preg_match('#<title>(.*?)</title>#si', $html, $t);
        $title = trim($t[1] ?? '');

        $this->assertNotSame('', $title, 'El <title> salió vacío.');
        $this->assertStringContainsString(
            'Machu Picchu',
            $title,
            "El <title> cayó al genérico del sitio en vez del título español. Emitido: [{$title}]"
        );
    }

    /** @test */
    public function escenario_del_dia_del_deploy_todas_las_columnas_traducidas_vacias(): void
    {
        // En producción el SQL agrega las columnas VACÍAS. Este es el estado
        // real del día del deploy: nada puede cambiar de comportamiento.
        $tour = $this->tour(['slug' => 'city-tour-lima', 'slug_en' => null, 'slug_pt' => null]);

        foreach (['es', 'en', 'pt'] as $locale) {
            $this->get("/{$locale}/tours/detalle/city-tour-lima")
                ->assertOk();  // fallback al slug ES: ninguna URL viva se rompe
        }

        // Y el hreflang debe seguir emitiendo las 3, todas resolubles.
        $html = $this->get('/es/tours/detalle/city-tour-lima')->getContent();
        preg_match_all('#<link rel="alternate" hreflang="(es|en|pt)" +href="([^"]+)"#i', $html, $m);

        $this->assertCount(3, $m[2], 'No se emitieron los 3 hreflang.');
        foreach ($m[2] as $url) {
            $this->assertStringContainsString('city-tour-lima', $url);
            $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
        }
    }
}
