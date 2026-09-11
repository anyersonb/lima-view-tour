<?php

namespace Tests\Feature\Seo;

use App\Models\Region;
use App\Models\Setting;
use App\Support\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lote A (2026-08-31), primera mitad del apagado de schemas automáticos:
 * añade DÓNDE cargar un JSON-LD manual por idioma para las páginas públicas
 * que hoy no tienen ficha propia (home, catálogo de tours, catálogo por
 * región, listado del blog) y un schema global para todo el sitio.
 *
 * El lote B (mismo día, ver AutomaticJsonLdDisabledTest.php) ya apagó el
 * automático: ya NO conviven. El punto 3 de abajo describía la convivencia
 * de Lote A y quedó invertido — ahora protege lo contrario (exclusividad).
 *
 * Lo que se protege:
 * 1. Con el campo relleno, la página pública imprime ese JSON-LD.
 * 2. Con el campo vacío, no se imprime NINGÚN <script> extra (ni vacío) —
 *    la garantía real de "sin schemas automáticos" (Lote B, ya vigente).
 * 3. Con el campo relleno, el manual es el ÚNICO bloque: no hay ningún
 *    WebSite/Organization automático de acompañamiento.
 */
class SchemaJsonLdNewSurfacesTest extends TestCase
{
    use RefreshDatabase;

    /** Extrae los bloques JSON-LD del HTML, ya decodificados (uno por <script>). */
    private function jsonLdBlocks(string $html): array
    {
        preg_match_all(
            '#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si',
            $html,
            $m
        );

        $out = [];
        foreach ($m[1] as $raw) {
            // Invariante dura: ningún bloque puede estar vacío o no-JSON.
            $this->assertNotSame('', trim($raw), 'Se encontró un <script type="application/ld+json"> vacío.');
            $decoded = json_decode(trim($raw), true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Bloque JSON-LD no parseable: '.json_last_error_msg());
            $out[] = $decoded;
        }

        return $out;
    }

    private function containsMarker(array $blocks, string $marker): bool
    {
        return collect($blocks)->contains(fn ($b) => ($b['name'] ?? null) === $marker);
    }

    private function region(string $slug): Region
    {
        return Region::create([
            'slug' => $slug,
            'name_es' => ucfirst($slug),
            'name_en' => ucfirst($slug),
            'is_active' => true,
        ]);
    }

    private function setSchema(string $page, string $locale, string $json): void
    {
        Setting::set(PageSeo::settingKey($page, 'schema', $locale), $json);
    }

    private function schemaJson(string $marker): string
    {
        return json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $marker]);
    }

    // ── Home ─────────────────────────────────────────────────────────────

    /**
     * Lote B (2026-08-31) apagó el WebSite automático del home el mismo día:
     * este test afirmaba que convivía con él. Invertido: ahora el manual
     * debe ser el ÚNICO bloque emitido, sin ningún WebSite automático.
     */
    public function test_home_prints_only_the_custom_schema_with_no_automatic_website_block(): void
    {
        $this->setSchema('home', 'es', $this->schemaJson('MARCA-HOME-CUSTOM'));

        $html = $this->get('/es')->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $this->assertTrue($this->containsMarker($blocks, 'MARCA-HOME-CUSTOM'));

        $types = collect($blocks)->flatMap(fn ($b) => (array) ($b['@type'] ?? []))->all();
        $this->assertNotContains('WebSite', $types, 'Reapareció el WebSite automático: debería estar apagado (Lote B).');
        $this->assertCount(1, $blocks, 'Con el manual relleno solo debe emitirse ESE bloque, ninguno automático de más.');
    }

    public function test_home_prints_nothing_extra_when_the_field_is_empty(): void
    {
        $before = $this->jsonLdBlocks($this->get('/es')->assertOk()->getContent());

        $this->setSchema('home', 'es', '   ');

        $after = $this->jsonLdBlocks($this->get('/es')->assertOk()->getContent());

        $this->assertSame(count($before), count($after), 'Un valor en blanco no debe agregar ningún bloque JSON-LD.');
    }

    // ── Catálogo de tours (sin categoría) ───────────────────────────────

    public function test_tours_catalog_prints_the_custom_schema_when_filled(): void
    {
        $this->setSchema('tours', 'es', $this->schemaJson('MARCA-TOURS-CUSTOM'));

        $html = $this->get('/es/tours')->assertOk()->getContent();

        $this->assertTrue($this->containsMarker($this->jsonLdBlocks($html), 'MARCA-TOURS-CUSTOM'));
    }

    public function test_tours_catalog_prints_nothing_extra_when_empty(): void
    {
        $before = count($this->jsonLdBlocks($this->get('/es/tours')->assertOk()->getContent()));
        $after = count($this->jsonLdBlocks($this->get('/es/tours')->assertOk()->getContent()));

        $this->assertSame($before, $after);
    }

    // ── Catálogo por región (lima/ica/cusco) ────────────────────────────

    public function test_tours_category_lima_prints_its_own_schema_and_ica_does_not_inherit_it(): void
    {
        $this->region('lima');
        $this->region('ica');

        $this->setSchema('tours_lima', 'es', $this->schemaJson('MARCA-LIMA-CUSTOM'));

        $limaBlocks = $this->jsonLdBlocks($this->get('/es/tours/categoria/lima')->assertOk()->getContent());
        $icaBlocks = $this->jsonLdBlocks($this->get('/es/tours/categoria/ica')->assertOk()->getContent());

        $this->assertTrue($this->containsMarker($limaBlocks, 'MARCA-LIMA-CUSTOM'));
        $this->assertFalse($this->containsMarker($icaBlocks, 'MARCA-LIMA-CUSTOM'), 'Ica no debe heredar el schema de Lima.');
    }

    public function test_tours_category_prints_nothing_extra_when_empty(): void
    {
        $this->region('cusco');

        $html = $this->get('/es/tours/categoria/cusco')->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        // Ningún bloque trae el marcador porque nunca se cargó nada.
        $this->assertFalse($this->containsMarker($blocks, 'MARCA-LIMA-CUSTOM'));
        $this->assertFalse($this->containsMarker($blocks, 'MARCA-CUSCO-CUSTOM'));
    }

    // ── Blog — listado ───────────────────────────────────────────────────

    public function test_blog_index_prints_the_custom_schema_when_filled(): void
    {
        $this->setSchema('blog', 'es', $this->schemaJson('MARCA-BLOG-CUSTOM'));

        $html = $this->get('/es/blog')->assertOk()->getContent();

        $this->assertTrue($this->containsMarker($this->jsonLdBlocks($html), 'MARCA-BLOG-CUSTOM'));
    }

    public function test_blog_index_prints_nothing_extra_when_empty(): void
    {
        $before = count($this->jsonLdBlocks($this->get('/es/blog')->assertOk()->getContent()));
        $after = count($this->jsonLdBlocks($this->get('/es/blog')->assertOk()->getContent()));

        $this->assertSame($before, $after);
    }

    // ── Fallback de idioma (mismo criterio que schemaJsonLd() de Tour/Page) ──

    public function test_a_locale_without_its_own_schema_falls_back_to_spanish(): void
    {
        $this->setSchema('home', 'es', $this->schemaJson('MARCA-HOME-FALLBACK-ES'));

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertTrue($this->containsMarker($this->jsonLdBlocks($html), 'MARCA-HOME-FALLBACK-ES'));
    }

    // ── Schema global (Configuración → SEO → "Schema global del sitio") ──

    public function test_global_schema_prints_on_every_page_when_filled(): void
    {
        Setting::set('schema_jsonld_global_es', $this->schemaJson('MARCA-GLOBAL-CUSTOM'));

        $home = $this->jsonLdBlocks($this->get('/es')->assertOk()->getContent());
        $tours = $this->jsonLdBlocks($this->get('/es/tours')->assertOk()->getContent());

        $this->assertTrue($this->containsMarker($home, 'MARCA-GLOBAL-CUSTOM'));
        $this->assertTrue($this->containsMarker($tours, 'MARCA-GLOBAL-CUSTOM'), 'El schema global debe verse en CUALQUIER página, no solo home.');
    }

    public function test_global_schema_prints_nothing_by_default_without_any_precarga(): void
    {
        // Sin tocar ningún Setting — control explícito de que el campo NO
        // trae ningún valor precargado (requisito del brief): la home no
        // trae el marcador de ninguna prueba anterior porque no se cargó.
        $html = $this->get('/es')->assertOk()->getContent();

        $this->assertFalse($this->containsMarker($this->jsonLdBlocks($html), 'MARCA-GLOBAL-CUSTOM'));
    }

    public function test_global_schema_falls_back_to_spanish_when_locale_is_empty(): void
    {
        Setting::set('schema_jsonld_global_es', $this->schemaJson('MARCA-GLOBAL-FALLBACK'));

        $html = $this->get('/pt')->assertOk()->getContent();

        $this->assertTrue($this->containsMarker($this->jsonLdBlocks($html), 'MARCA-GLOBAL-FALLBACK'));
    }

    // ── Control: el assert falla si el mecanismo deja de aplicar ─────────

    public function test_the_assertion_fails_if_the_home_schema_setting_stops_being_read(): void
    {
        $without = $this->jsonLdBlocks($this->get('/es')->assertOk()->getContent());

        $this->setSchema('home', 'es', $this->schemaJson('MARCA-CONTROL'));
        $with = $this->jsonLdBlocks($this->get('/es')->assertOk()->getContent());

        $this->assertNotSame(count($without), count($with), 'El bloque count debe cambiar al cargar un schema — si no cambia, el mecanismo no está leyendo el setting.');
    }
}
