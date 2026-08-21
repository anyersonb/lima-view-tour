<?php

namespace Tests\Unit\Models;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * title_en/excerpt_en/body_en (and _pt) are NOT NULL columns with no DB
 * default (see 2026_06_29_000010_create_blog_posts_table.php), so a
 * Spanish-only post stores '' there, never NULL. `??` only falls back on
 * NULL, not on '', so it silently skipped the Spanish fallback and a
 * solo-ES post rendered blank (or the generic site title, for
 * meta title/description) under /en or /pt. Fixed by switching to `?:`.
 */
class BlogPostLocalizedFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function spanishOnlyPost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'slug' => 'post-solo-es',
            'title_es' => 'Título en español',
            'excerpt_es' => 'Extracto en español.',
            'body_es' => '<p>Cuerpo en español.</p>',
            // Simulates what actually lands in the DB when the EN/PT tabs
            // are left empty in the Filament form: '' (NOT NULL, no default),
            // not null.
            'title_en' => '',
            'excerpt_en' => '',
            'body_en' => '',
            'title_pt' => '',
            'excerpt_pt' => '',
            'body_pt' => '',
        ], $overrides));
    }

    public function test_title_falls_back_to_spanish_when_the_english_column_is_an_empty_string(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        $this->assertSame('Título en español', $post->title);
    }

    public function test_excerpt_falls_back_to_spanish_when_the_english_column_is_an_empty_string(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        $this->assertSame('Extracto en español.', $post->excerpt);
    }

    public function test_body_falls_back_to_spanish_when_the_english_column_is_an_empty_string(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        $this->assertSame('<p>Cuerpo en español.</p>', $post->body);
    }

    public function test_meta_title_falls_back_to_the_localized_title_not_the_generic_site_title(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        $this->assertSame('Título en español', $post->metaTitle);
    }

    public function test_meta_description_falls_back_to_the_localized_excerpt(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        $this->assertSame('Extracto en español.', $post->metaDescription);
    }
}
