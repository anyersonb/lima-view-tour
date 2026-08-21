<?php

namespace Tests\Feature\Seo;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogLocalizedSeoTest extends TestCase
{
    use RefreshDatabase;

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

    /** @test */
    public function it_resolves_a_post_by_its_translated_english_slug(): void
    {
        $this->createPost([
            'slug' => 'guia-machu-picchu',
            'slug_en' => 'machu-picchu-guide',
        ]);

        $response = $this->get('/en/blog/machu-picchu-guide');

        $response->assertOk();
        $response->assertSee('Machu Picchu Guide');
    }

    /** @test */
    public function it_falls_back_to_the_spanish_slug_when_the_translated_slug_is_empty(): void
    {
        $this->createPost([
            'slug' => 'guia-cusco',
            'slug_en' => null,
        ]);

        $response = $this->get('/en/blog/guia-cusco');

        $response->assertOk();
    }

    /** @test */
    public function it_redirects_301_from_the_spanish_slug_to_the_translated_slug_under_a_non_spanish_locale(): void
    {
        $this->createPost([
            'slug' => 'guia-machu-picchu',
            'slug_en' => 'machu-picchu-guide',
        ]);

        $response = $this->get('/en/blog/guia-machu-picchu');

        $response->assertRedirect('/en/blog/machu-picchu-guide');
        $response->assertStatus(301);
    }

    /** @test */
    public function it_returns_404_for_a_slug_that_does_not_exist_in_any_locale(): void
    {
        $response = $this->get('/en/blog/no-existe-este-post');

        $response->assertNotFound();
    }

    /** @test */
    public function it_emits_the_three_real_localized_urls_in_hreflang(): void
    {
        $this->createPost([
            'slug' => 'guia-machu-picchu',
            'slug_en' => 'machu-picchu-guide',
            'slug_pt' => 'guia-machu-picchu-pt',
        ]);

        $response = $this->get('/es/blog/guia-machu-picchu');

        $response->assertOk();
        $response->assertSee('http://lima-tour.test/es/blog/guia-machu-picchu', false);
        $response->assertSee('http://lima-tour.test/en/blog/machu-picchu-guide', false);
        $response->assertSee('http://lima-tour.test/pt/blog/guia-machu-picchu-pt', false);
    }

    /** @test */
    public function custom_jsonld_replaces_the_auto_generated_blogposting_schema_when_present(): void
    {
        $customJsonLd = json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => 'JSON-LD manual del blog']);

        $this->createPost([
            'slug' => 'guia-machu-picchu',
            'schema_jsonld_es' => $customJsonLd,
        ]);

        $response = $this->get('/es/blog/guia-machu-picchu');

        $response->assertOk();
        $response->assertSee('JSON-LD manual del blog', false);
        $response->assertDontSee('"BlogPosting"', false);
    }
}
