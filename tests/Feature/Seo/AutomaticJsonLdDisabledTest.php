<?php

namespace Tests\Feature\Seo;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lote B (2026-08-31): apaga los emisores automáticos de JSON-LD (el
 * Organization/WebSite global de <x-jsonld />, el WebSite duplicado y el
 * FAQPage automático de la home, el Product+TouristTrip y el FAQPage
 * automáticos de la ficha de tour, y el @graph BlogPosting+BreadcrumbList
 * automático del artículo de blog).
 *
 * Contrato que se protege en TODAS las superficies públicas:
 * 1. Con todos los campos de JSON-LD del CMS vacíos, la página no imprime
 *    NINGÚN <script type="application/ld+json">.
 * 2. Con el campo manual relleno, la página imprime exactamente los bloques
 *    del CMS — ni uno automático de más.
 */
class AutomaticJsonLdDisabledTest extends TestCase
{
    use RefreshDatabase;

    private function jsonLdBlocks(string $html): array
    {
        preg_match_all(
            '#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si',
            $html,
            $m
        );

        return $m[1];
    }

    private function assertsZeroJsonLd(string $html, string $context): void
    {
        $blocks = $this->jsonLdBlocks($html);

        $this->assertCount(
            0,
            $blocks,
            "Se esperaban 0 bloques application/ld+json en {$context}, se encontraron ".count($blocks).
            (empty($blocks) ? '' : ': '.json_encode($blocks))
        );
    }

    // ── Home ─────────────────────────────────────────────────────────────

    public function test_home_emits_no_jsonld_when_every_field_is_empty(): void
    {
        $html = $this->get('/es')->assertOk()->getContent();

        $this->assertsZeroJsonLd($html, 'la home');
    }

    public function test_home_emits_exactly_one_jsonld_block_when_the_custom_schema_is_filled(): void
    {
        \App\Models\Setting::set(
            \App\Support\PageSeo::settingKey('home', 'schema', 'es'),
            json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'MARCA-HOME'])
        );

        $html = $this->get('/es')->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $this->assertCount(1, $blocks);
        $this->assertStringContainsString('MARCA-HOME', $blocks[0]);
    }

    // ── Catálogo de tours (listado) ──────────────────────────────────────

    public function test_tours_index_emits_no_jsonld_when_every_field_is_empty(): void
    {
        $html = $this->get('/es/tours')->assertOk()->getContent();

        $this->assertsZeroJsonLd($html, 'el listado de tours');
    }

    public function test_tours_index_emits_exactly_one_jsonld_block_when_the_custom_schema_is_filled(): void
    {
        \App\Models\Setting::set(
            \App\Support\PageSeo::settingKey('tours', 'schema', 'es'),
            json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'MARCA-TOURS'])
        );

        $html = $this->get('/es/tours')->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $this->assertCount(1, $blocks);
        $this->assertStringContainsString('MARCA-TOURS', $blocks[0]);
    }

    // ── Ficha de tour ─────────────────────────────────────────────────────

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge(['is_published' => true], $overrides));
    }

    public function test_tour_show_emits_no_jsonld_when_every_field_is_empty(): void
    {
        // Incluye FAQs del tour rellenas: el FAQPage automático que las leía
        // está apagado, así que igual deben dar 0 bloques.
        $tour = $this->tour([
            'slug' => 'city-tour-lima',
            'faqs_es' => [
                ['question' => '¿Pregunta?', 'answer' => 'Respuesta.'],
            ],
        ]);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();

        $this->assertsZeroJsonLd($html, 'la ficha del tour');
    }

    public function test_tour_show_emits_exactly_one_jsonld_block_when_the_custom_schema_is_filled(): void
    {
        $tour = $this->tour([
            'slug' => 'city-tour-lima',
            'schema_jsonld_es' => json_encode(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'MARCA-TOUR']),
            'faqs_es' => [
                ['question' => '¿Pregunta?', 'answer' => 'Respuesta.'],
            ],
        ]);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $this->assertCount(1, $blocks);
        $this->assertStringContainsString('MARCA-TOUR', $blocks[0]);
    }

    // ── Blog — listado ───────────────────────────────────────────────────

    public function test_blog_index_emits_no_jsonld_when_every_field_is_empty(): void
    {
        $html = $this->get('/es/blog')->assertOk()->getContent();

        $this->assertsZeroJsonLd($html, 'el listado de blog');
    }

    public function test_blog_index_emits_exactly_one_jsonld_block_when_the_custom_schema_is_filled(): void
    {
        \App\Models\Setting::set(
            \App\Support\PageSeo::settingKey('blog', 'schema', 'es'),
            json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'MARCA-BLOG-INDEX'])
        );

        $html = $this->get('/es/blog')->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $this->assertCount(1, $blocks);
        $this->assertStringContainsString('MARCA-BLOG-INDEX', $blocks[0]);
    }

    // ── Artículo de blog ──────────────────────────────────────────────────

    private function createPost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title_es' => 'Guía de Machu Picchu',
            'excerpt_es' => 'Todo lo que necesitas saber para visitar Machu Picchu.',
            'body_es' => '<p>Contenido de prueba en español.</p>',
            'title_en' => 'Machu Picchu Guide',
            'excerpt_en' => 'Everything you need to know to visit Machu Picchu.',
            'body_en' => '<p>Test content in English.</p>',
            'title_pt' => 'Guia de Machu Picchu',
            'excerpt_pt' => 'Tudo o que você precisa saber para visitar Machu Picchu.',
            'body_pt' => '<p>Conteúdo de teste em português.</p>',
            'is_published' => true,
        ], $overrides));
    }

    public function test_blog_show_emits_no_jsonld_when_every_field_is_empty(): void
    {
        $post = $this->createPost(['slug' => 'guia-machu-picchu']);

        $html = $this->get("/es/blog/{$post->slug}")->assertOk()->getContent();

        $this->assertsZeroJsonLd($html, 'el artículo de blog');
    }

    public function test_blog_show_emits_exactly_one_jsonld_block_when_the_custom_schema_is_filled(): void
    {
        $post = $this->createPost([
            'slug' => 'guia-machu-picchu',
            'schema_jsonld_es' => json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => 'MARCA-BLOG-POST']),
        ]);

        $html = $this->get("/es/blog/{$post->slug}")->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $this->assertCount(1, $blocks);
        $this->assertStringContainsString('MARCA-BLOG-POST', $blocks[0]);
    }

    // ── Página institucional (about / nosotros) ──────────────────────────

    public function test_institutional_page_emits_no_jsonld_when_every_field_is_empty(): void
    {
        // Sin fila Page en absoluto: el caso "todo vacío" más extremo.
        $html = $this->get('/es/nosotros')->assertOk()->getContent();

        $this->assertsZeroJsonLd($html, 'la página institucional /nosotros');
    }

    public function test_institutional_page_emits_exactly_one_jsonld_block_when_the_custom_schema_is_filled(): void
    {
        Page::create([
            'slug' => 'nosotros',
            'title_es' => 'Nosotros',
            'schema_jsonld_es' => json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'MARCA-NOSOTROS']),
        ]);

        $html = $this->get('/es/nosotros')->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $this->assertCount(1, $blocks);
        $this->assertStringContainsString('MARCA-NOSOTROS', $blocks[0]);
    }
}
