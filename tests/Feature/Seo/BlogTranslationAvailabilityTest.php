<?php

namespace Tests\Feature\Seo;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cambio de requisito (2026-09-24, mismo día del hotfix del 500): EN/PT
 * siguen siendo opcionales, pero si la traducción está vacía la nota YA NO
 * se muestra en ese idioma — se retira el fallback a español que existía
 * desde el 19/08. "Existe" en un idioma cuando title_{locale} Y
 * body_{locale} tienen contenido real (BlogPost::isAvailableIn()).
 *
 * Cubre, en orden de prioridad (el usuario marcó 2/3/4/5 como urgentes):
 *  1. Listado /{locale}/blog (posts + categorías).
 *  2. Ficha /{locale}/blog/{slug} de una nota sin traducir → 404 sin redirect.
 *  3. Sitemap: excluye las URLs de locales sin traducción.
 *  4. hreflang: solo idiomas disponibles, x-default → es.
 *  5. Notas relacionadas.
 *  6. Selector de idioma: no enlaza a un 404 (decisión: lleva al listado del
 *     blog de ese idioma — ver components/lang-switcher.blade.php).
 *
 * tests/Unit/Models/BlogPostLocalizedFallbackTest.php cubre el detalle fino
 * de los accessors (title/excerpt/body/metaTitle/metaDescription) en sí.
 */
class BlogTranslationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function spanishOnlyPost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'slug' => 'guia-arequipa',
            'title_es' => 'Guía de Arequipa',
            'excerpt_es' => 'Extracto en español sobre Arequipa.',
            'body_es' => '<p>Cuerpo en español sobre Arequipa.</p>',
            'title_en' => null,
            'excerpt_en' => null,
            'body_en' => null,
            'title_pt' => null,
            'excerpt_pt' => null,
            'body_pt' => null,
            'is_published' => true,
        ], $overrides));
    }

    private function fullyTranslatedPost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'slug' => 'guia-cusco',
            'title_es' => 'Guía de Cusco',
            'excerpt_es' => 'Extracto ES sobre Cusco.',
            'body_es' => '<p>Cuerpo ES sobre Cusco.</p>',
            'title_en' => 'Cusco Guide',
            'excerpt_en' => 'EN excerpt about Cusco.',
            'body_en' => '<p>EN body about Cusco.</p>',
            'title_pt' => 'Guia de Cusco',
            'excerpt_pt' => 'PT excerpt sobre Cusco.',
            'body_pt' => '<p>PT body sobre Cusco.</p>',
            'is_published' => true,
        ], $overrides));
    }

    // ── 2. Ficha: 404 sin redirect, sin contenido español ──────────────────

    public function test_show_returns_404_for_a_post_without_english_translation(): void
    {
        $this->spanishOnlyPost(['slug' => 'guia-arequipa-404']);

        $response = $this->get('/en/blog/guia-arequipa-404');

        $response->assertNotFound();
    }

    public function test_show_returns_404_for_a_post_without_portuguese_translation(): void
    {
        $this->spanishOnlyPost(['slug' => 'guia-arequipa-404-pt']);

        $response = $this->get('/pt/blog/guia-arequipa-404-pt');

        $response->assertNotFound();
    }

    public function test_show_does_not_render_spanish_content_under_the_english_url(): void
    {
        $this->spanishOnlyPost([
            'slug' => 'guia-arequipa-sin-fuga',
            'title_es' => 'TITULO-ES-QUE-NO-DEBE-VERSE-EN-INGLES',
        ]);

        $response = $this->get('/en/blog/guia-arequipa-sin-fuga');

        $response->assertNotFound();
        $response->assertDontSee('TITULO-ES-QUE-NO-DEBE-VERSE-EN-INGLES');
    }

    public function test_show_of_an_untranslated_post_is_a_404_not_a_redirect(): void
    {
        $this->spanishOnlyPost(['slug' => 'guia-arequipa-sin-redirect']);

        $response = $this->get('/en/blog/guia-arequipa-sin-redirect');

        $this->assertFalse($response->isRedirect(), 'La nota sin traducir redirigió en vez de dar 404.');
        $response->assertStatus(404);
    }

    public function test_show_works_normally_for_a_fully_translated_post(): void
    {
        $this->fullyTranslatedPost(['slug' => 'guia-cusco-ok']);

        $response = $this->get('/en/blog/guia-cusco-ok');

        $response->assertOk();
        $response->assertSee('Cusco Guide');
    }

    // ── 1. Listado: posts y categorías ──────────────────────────────────────

    public function test_index_excludes_a_post_without_english_translation(): void
    {
        $this->spanishOnlyPost([
            'slug' => 'solo-es-index',
            'title_es' => 'MARCADOR-SOLO-ES-INDEX',
        ]);
        $this->fullyTranslatedPost(['slug' => 'completo-index']);

        $response = $this->get('/en/blog');

        $response->assertOk();
        $response->assertDontSee('MARCADOR-SOLO-ES-INDEX');
        $response->assertSee('Cusco Guide');
    }

    public function test_index_shows_the_untranslated_post_under_the_spanish_locale(): void
    {
        $this->spanishOnlyPost([
            'slug' => 'solo-es-index-es',
            'title_es' => 'MARCADOR-SOLO-ES-VISIBLE-EN-ES',
        ]);

        $response = $this->get('/es/blog');

        $response->assertOk();
        $response->assertSee('MARCADOR-SOLO-ES-VISIBLE-EN-ES');
    }

    public function test_index_category_filter_excludes_the_category_of_an_untranslated_post(): void
    {
        $this->spanishOnlyPost(['slug' => 'solo-es-cat', 'category' => 'CategoriaSoloES']);
        $this->fullyTranslatedPost(['slug' => 'completo-cat', 'category' => 'CategoriaTraducida']);

        $response = $this->get('/en/blog');

        $response->assertOk();
        $response->assertDontSee('CategoriaSoloES');
        $response->assertSee('CategoriaTraducida');
    }

    // ── 5. Notas relacionadas ────────────────────────────────────────────────

    public function test_related_posts_exclude_an_untranslated_post(): void
    {
        $main = $this->fullyTranslatedPost(['slug' => 'principal-relacionadas', 'category' => 'Peru']);
        $this->spanishOnlyPost([
            'slug' => 'relacionada-sin-en',
            'category' => 'Peru',
            'title_es' => 'MARCADOR-RELACIONADA-SOLO-ES',
        ]);
        $this->fullyTranslatedPost([
            'slug' => 'relacionada-con-en',
            'category' => 'Peru',
            'title_en' => 'Related EN Title',
        ]);

        $response = $this->get('/en/blog/' . $main->slug);

        $response->assertOk();
        $response->assertDontSee('MARCADOR-RELACIONADA-SOLO-ES');
        $response->assertSee('Related EN Title');
    }

    // ── 4. hreflang: solo disponibles, x-default → es ────────────────────────

    /**
     * Ojo de medición: el selector de idioma del header (x-lang-switcher)
     * también imprime `hreflang="en"`/`hreflang="pt"` en sus <a> — SIEMPRE,
     * para los 3 idiomas, sin importar disponibilidad (ver punto 6: ese link
     * debe existir igual, solo cambia su destino). Buscar el substring corto
     * `hreflang="en"` a secas matchea esas anclas también y da un falso
     * verde/rojo según el caso. Se ancla al <link> real del <head>
     * (`<link rel="alternate" hreflang="...`), que es el único que SEO mira.
     */
    public function test_hreflang_only_lists_locales_where_the_post_is_available(): void
    {
        $this->spanishOnlyPost(['slug' => 'guia-arequipa-hreflang']);

        $response = $this->get('/es/blog/guia-arequipa-hreflang');

        $response->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="es"', false);
        $response->assertDontSee('<link rel="alternate" hreflang="en"', false);
        $response->assertDontSee('<link rel="alternate" hreflang="pt"', false);
        $response->assertSee('<link rel="alternate" hreflang="x-default"', false);
    }

    public function test_hreflang_lists_all_three_locales_for_a_fully_translated_post(): void
    {
        $this->fullyTranslatedPost(['slug' => 'guia-cusco-hreflang']);

        $response = $this->get('/es/blog/guia-cusco-hreflang');

        $response->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="es"', false);
        $response->assertSee('<link rel="alternate" hreflang="en"', false);
        $response->assertSee('<link rel="alternate" hreflang="pt"', false);
    }

    public function test_hreflang_x_default_points_to_the_spanish_url(): void
    {
        $this->spanishOnlyPost(['slug' => 'guia-arequipa-xdefault']);

        $response = $this->get('/es/blog/guia-arequipa-xdefault');

        $response->assertOk();
        $response->assertSee(
            'hreflang="x-default" href="http://lima-tour.test/es/blog/guia-arequipa-xdefault"',
            false
        );
    }

    // ── 6. Selector de idioma ────────────────────────────────────────────────

    public function test_language_switcher_sends_an_untranslated_locale_to_that_locales_blog_index(): void
    {
        $this->spanishOnlyPost(['slug' => 'guia-arequipa-switcher']);

        $response = $this->get('/es/blog/guia-arequipa-switcher');

        $response->assertOk();
        $response->assertSee('href="http://lima-tour.test/en/blog"', false);
        $response->assertDontSee('href="http://lima-tour.test/en/blog/guia-arequipa-switcher"', false);
    }

    public function test_language_switcher_sends_a_translated_locale_to_its_own_translated_url(): void
    {
        $this->fullyTranslatedPost(['slug' => 'guia-cusco-switcher', 'slug_en' => 'cusco-guide-switcher']);

        $response = $this->get('/es/blog/guia-cusco-switcher');

        $response->assertOk();
        $response->assertSee('href="http://lima-tour.test/en/blog/cusco-guide-switcher"', false);
    }

    // ── 3. Sitemap ────────────────────────────────────────────────────────────

    public function test_sitemap_excludes_the_english_and_portuguese_urls_for_an_untranslated_post(): void
    {
        $this->spanishOnlyPost(['slug' => 'guia-arequipa-sitemap']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('/es/blog/guia-arequipa-sitemap', false);
        $response->assertDontSee('/en/blog/guia-arequipa-sitemap', false);
        $response->assertDontSee('/pt/blog/guia-arequipa-sitemap', false);
    }

    public function test_sitemap_includes_all_three_locales_for_a_fully_translated_post(): void
    {
        $this->fullyTranslatedPost(['slug' => 'guia-cusco-sitemap']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('/es/blog/guia-cusco-sitemap', false);
        $response->assertSee('/en/blog/guia-cusco-sitemap', false);
        $response->assertSee('/pt/blog/guia-cusco-sitemap', false);
    }

    // ── 7. Slugs: título vacío en EN/PT no debe colisionar ───────────────────

    public function test_two_posts_with_blank_english_title_both_save_without_a_unique_slug_en_collision(): void
    {
        $first = $this->spanishOnlyPost(['slug' => 'primero-sin-en']);
        $second = $this->spanishOnlyPost(['slug' => 'segundo-sin-en']);

        $this->assertNull($first->slug_en);
        $this->assertNull($second->slug_en);
    }
}
