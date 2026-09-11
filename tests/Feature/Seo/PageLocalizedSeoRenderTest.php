<?php

namespace Tests\Feature\Seo;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ronda 3 del lote SEO i18n (2026-08-19): el bloque "SEO — [idioma]" del
 * panel Filament → Páginas (meta título, meta descripción y JSON-LD, por
 * cada idioma) estaba COMPLETAMENTE INERTE para about.blade.php, contact.blade.php,
 * reviews.blade.php y las páginas legales — se guardaba en BD y ninguna
 * vista lo leía. Estos tests ejercitan el HTML REALMENTE renderizado (no el
 * modelo aislado): sin el fix de las vistas/controllers, todos fallan porque
 * el <title>/<meta description>/JSON-LD emitidos son siempre los fijos del
 * lang file, sin importar lo que haya en la tabla `pages`.
 */
class PageLocalizedSeoRenderTest extends TestCase
{
    use RefreshDatabase;

    /** Extrae los bloques JSON-LD del HTML, ya decodificados. */
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

    private function metaDescription(string $html): string
    {
        preg_match('#<meta name="description" content="([^"]*)"#i', $html, $m);

        return $m[1] ?? '';
    }

    private function title(string $html): string
    {
        preg_match('#<title>(.*?)</title>#si', $html, $m);

        return trim($m[1] ?? '');
    }

    // ── /nosotros (about.blade.php) ─────────────────────────────────────

    public function test_about_page_uses_cms_meta_title_description_and_jsonld_when_set(): void
    {
        Page::create([
            'slug' => 'nosotros',
            'title_es' => 'Nosotros',
            'meta_title_es' => 'META TITLE NOSOTROS CUSTOM',
            'meta_description_es' => 'META DESCRIPTION NOSOTROS CUSTOM',
            'schema_jsonld_es' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'MARCA-JSONLD-NOSOTROS',
            ]),
        ]);

        $html = $this->get('/es/nosotros')->assertOk()->getContent();

        $this->assertStringContainsString('META TITLE NOSOTROS CUSTOM', $this->title($html));
        $this->assertSame('META DESCRIPTION NOSOTROS CUSTOM', $this->metaDescription($html));

        $found = collect($this->jsonLdBlocks($html))
            ->contains(fn ($b) => ($b['name'] ?? null) === 'MARCA-JSONLD-NOSOTROS');
        $this->assertTrue($found, 'El JSON-LD manual de la página no se inyectó en el HTML.');
    }

    public function test_about_page_falls_back_to_default_text_without_a_page_row(): void
    {
        // Sin registro Page en absoluto (StaticPagesSeeder no corrió) — no debe romper.
        $html = $this->get('/en/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('About us', $this->title($html));
        $this->assertStringContainsString(
            'We are professional planners for your vacation',
            $this->metaDescription($html)
        );
    }

    // ── /contacto (contact.blade.php) ───────────────────────────────────

    public function test_contact_page_uses_locale_specific_meta_title_over_spanish_fallback(): void
    {
        Page::create([
            'slug' => 'contacto',
            'title_es' => 'Contáctanos',
            'meta_title_es' => 'META TITLE CONTACTO ES',
            'meta_title_en' => 'META TITLE CONTACTO EN',
        ]);

        $html = $this->get('/en/contact-us')->assertOk()->getContent();

        $this->assertStringContainsString('META TITLE CONTACTO EN', $this->title($html));
        $this->assertStringNotContainsString('META TITLE CONTACTO ES', $this->title($html));
    }

    public function test_contact_page_falls_back_to_default_text_without_a_page_row(): void
    {
        $html = $this->get('/es/contacto')->assertOk()->getContent();

        $this->assertStringContainsString('Contacto', $this->title($html));
    }

    // ── /resenas (reviews.blade.php) ────────────────────────────────────

    public function test_reviews_page_uses_cms_meta_description_and_jsonld_when_set(): void
    {
        Page::create([
            'slug' => 'resenas',
            'title_es' => 'Reseñas',
            'meta_description_es' => 'META DESCRIPTION RESENAS CUSTOM',
            'schema_jsonld_es' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'MARCA-JSONLD-RESENAS',
            ]),
        ]);

        $html = $this->get('/es/resenas')->assertOk()->getContent();

        $this->assertSame('META DESCRIPTION RESENAS CUSTOM', $this->metaDescription($html));

        $found = collect($this->jsonLdBlocks($html))
            ->contains(fn ($b) => ($b['name'] ?? null) === 'MARCA-JSONLD-RESENAS');
        $this->assertTrue($found, 'El JSON-LD manual de /resenas no se inyectó en el HTML.');
    }

    public function test_reviews_page_falls_back_to_default_text_without_a_page_row(): void
    {
        $html = $this->get('/pt/avaliacoes')->assertOk()->getContent();

        $this->assertStringContainsString('Avaliações de clientes', $this->title($html));
    }

    // ── /terminos y /privacidad (pages.terms / pages.privacy) ───────────

    public function test_terms_page_uses_cms_meta_title_when_set(): void
    {
        Page::create([
            'slug' => 'terminos',
            'title_es' => 'Términos y Condiciones',
            'meta_title_es' => 'META TITLE TERMINOS CUSTOM',
        ]);

        $html = $this->get('/es/terminos')->assertOk()->getContent();

        $this->assertStringContainsString('META TITLE TERMINOS CUSTOM', $this->title($html));
    }

    public function test_terms_page_falls_back_to_default_text_without_a_page_row(): void
    {
        $html = $this->get('/en/terms')->assertOk()->getContent();

        $this->assertStringContainsString('Terms and Conditions', $this->title($html));
    }

    public function test_privacy_page_uses_cms_meta_description_and_jsonld_when_set(): void
    {
        Page::create([
            'slug' => 'privacidad',
            'title_es' => 'Política de Privacidad',
            'meta_description_es' => 'META DESCRIPTION PRIVACIDAD CUSTOM',
            'schema_jsonld_es' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'MARCA-JSONLD-PRIVACIDAD',
            ]),
        ]);

        $html = $this->get('/es/privacidad')->assertOk()->getContent();

        $this->assertSame('META DESCRIPTION PRIVACIDAD CUSTOM', $this->metaDescription($html));

        $found = collect($this->jsonLdBlocks($html))
            ->contains(fn ($b) => ($b['name'] ?? null) === 'MARCA-JSONLD-PRIVACIDAD');
        $this->assertTrue($found, 'El JSON-LD manual de /privacidad no se inyectó en el HTML.');
    }

    public function test_privacy_page_falls_back_to_default_text_without_a_page_row(): void
    {
        $html = $this->get('/en/privacy')->assertOk()->getContent();

        $this->assertStringContainsString('Privacy Policy', $this->title($html));
    }

    // ── Exclusividad: el JSON-LD manual es el ÚNICO bloque, ya no convive
    //    con ningún Organization/WebSite automático ──────────────────────

    /**
     * Lote B (2026-08-31) apagó el JSON-LD automático de todo el sitio
     * (components/jsonld.blade.php, borrado). Este test protegía lo
     * contrario hasta ayer: que el manual se SUMABA al Organization/WebSite
     * global. Ese comportamiento ya no existe y afirmarlo sería falso —
     * invertido: ahora un JSON-LD manual relleno debe ser el ÚNICO bloque
     * que la página emite, sin ningún WebSite/Organization/LocalBusiness
     * automático de acompañamiento.
     */
    public function test_custom_jsonld_is_the_only_block_emitted_with_no_automatic_organization_or_website(): void
    {
        Page::create([
            'slug' => 'nosotros',
            'title_es' => 'Nosotros',
            'schema_jsonld_es' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'MARCA-JSONLD-ADITIVO',
            ]),
        ]);

        $html = $this->get('/es/nosotros')->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $types = collect($blocks)->flatMap(fn ($b) => (array) ($b['@type'] ?? []))->all();

        $this->assertNotContains('WebSite', $types, 'Reapareció el WebSite automático: debería estar apagado (Lote B).');
        $this->assertFalse(
            collect($types)->contains(fn ($t) => in_array($t, ['TravelAgency', 'LocalBusiness'], true)),
            'Reapareció el Organization/LocalBusiness automático: debería estar apagado (Lote B).'
        );

        $this->assertCount(1, $blocks, 'Con el manual relleno solo debe emitirse ESE bloque, ninguno automático de más.');
        $found = collect($blocks)->contains(fn ($b) => ($b['name'] ?? null) === 'MARCA-JSONLD-ADITIVO');
        $this->assertTrue($found);
    }
}
