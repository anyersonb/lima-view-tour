<?php

namespace Tests\Unit\Models;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page has no generic public "/{locale}/pagina/{slug}" route today (/contacto,
 * /nosotros, /terminos, /privacidad are fixed paths that only use `slug` as
 * an internal lookup key, not as a URL segment) — so these are model-level
 * tests of the same resolveForLocale()/slugFor()/metaTitle() contract used by
 * Tour and BlogPost, ready for whenever a generic page route exists.
 */
class PageLocalizedSeoTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'slug' => 'pagina-de-prueba',
            'title_es' => 'Página de prueba',
            'is_published' => true,
        ], $overrides));
    }

    /** @test */
    public function slug_for_falls_back_to_spanish_when_the_translated_slug_is_empty(): void
    {
        $page = $this->page(['slug_en' => null]);

        $this->assertSame('pagina-de-prueba', $page->slugFor('en'));
    }

    /** @test */
    public function slug_for_returns_the_translated_slug_when_present(): void
    {
        $page = $this->page(['slug_en' => 'test-page']);

        $this->assertSame('test-page', $page->slugFor('en'));
    }

    /** @test */
    public function resolve_for_locale_flags_a_redirect_when_the_spanish_slug_is_requested_but_a_translation_exists(): void
    {
        $this->page(['slug' => 'pagina-de-prueba', 'slug_en' => 'test-page']);

        $resolution = Page::resolveForLocale('en', 'pagina-de-prueba');

        $this->assertNotNull($resolution['model']);
        $this->assertSame('test-page', $resolution['redirect_slug']);
    }

    /** @test */
    public function resolve_for_locale_resolves_directly_without_redirect_when_the_translated_slug_is_requested(): void
    {
        $this->page(['slug' => 'pagina-de-prueba', 'slug_en' => 'test-page']);

        $resolution = Page::resolveForLocale('en', 'test-page');

        $this->assertNotNull($resolution['model']);
        $this->assertNull($resolution['redirect_slug']);
    }

    /** @test */
    public function meta_title_falls_back_to_the_legacy_global_seo_title_when_no_per_locale_value_exists(): void
    {
        $page = $this->page([
            'seo_title' => 'Título SEO legado',
            'meta_title_es' => null,
        ]);

        $this->assertSame('Título SEO legado', $page->metaTitle);
    }

    /** @test */
    public function meta_title_prefers_the_new_per_locale_field_over_the_legacy_column(): void
    {
        $page = $this->page([
            'seo_title' => 'Título SEO legado',
            'meta_title_es' => 'Meta título nuevo por idioma',
        ]);

        $this->assertSame('Meta título nuevo por idioma', $page->metaTitle);
    }
}
