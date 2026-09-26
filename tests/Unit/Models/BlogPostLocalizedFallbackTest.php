<?php

namespace Tests\Unit\Models;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historia de este archivo (importante para no reintroducir el bug):
 *
 *  - 19/08/2026: title_en/excerpt_en/body_en (y _pt) eran NOT NULL sin
 *    default; un post solo-ES guardaba '' ahí, nunca NULL. `??` no cae en
 *    '', así que el fallback a español no se disparaba. Se cambió a `?:`.
 *  - 24/09/2026 (hotfix 500, primera pasada): esas columnas pasaron a ser
 *    NULLABLE, así que un post solo-ES guarda NULL de verdad. `?:` sigue
 *    tratando NULL igual que ''.
 *  - 24/09/2026 (cambio de requisito, mismo día): el cliente pidió que EN/PT
 *    dejen de heredar contenido en español. Si la traducción está vacía, la
 *    nota NO se muestra en ese idioma (404 — ver BlogController::show() y
 *    tests/Feature/Seo/BlogTranslationAvailabilityTest.php). Los accessors
 *    getTitleAttribute()/getExcerptAttribute()/getBodyAttribute() YA NO
 *    hacen `?: $this->{campo}_es` — devuelven el valor crudo de la columna
 *    de ESE locale, vacío si no hay traducción.
 *
 * Este archivo testea el estado ACTUAL (sin fallback a español). Los tests
 * de 404, listado, categorías, relacionadas, sitemap y hreflang viven en
 * tests/Feature/Seo/BlogTranslationAvailabilityTest.php — este archivo se
 * queda con el detalle fino de los accessors en sí mismos.
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
            // Esto es lo que realmente guarda Filament hoy cuando las
            // pestañas English/Português quedan vacías: NULL (columnas
            // nullable desde 2026_09_24_000000_make_blog_posts_i18n_columns_nullable.php).
            'title_en' => null,
            'excerpt_en' => null,
            'body_en' => null,
            'title_pt' => null,
            'excerpt_pt' => null,
            'body_pt' => null,
        ], $overrides));
    }

    public function test_title_does_not_fall_back_to_spanish_when_the_english_column_is_null(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        $this->assertSame('', $post->title);
        $this->assertNotSame('Título en español', $post->title);
    }

    public function test_excerpt_does_not_fall_back_to_spanish_when_the_english_column_is_null(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        $this->assertSame('', $post->excerpt);
        $this->assertNotSame('Extracto en español.', $post->excerpt);
    }

    public function test_body_does_not_fall_back_to_spanish_when_the_english_column_is_null(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        $this->assertSame('', $post->body);
        $this->assertNotSame('<p>Cuerpo en español.</p>', $post->body);
    }

    public function test_meta_title_does_not_fall_back_to_spanish_when_empty(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('en');

        // meta_title cae al título LOCALIZADO (getTitleAttribute()), que ya
        // no es español: el resultado final es '', no el título en español.
        $this->assertSame('', $post->metaTitle);
    }

    public function test_meta_description_does_not_fall_back_to_spanish_when_empty(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('pt');

        $this->assertSame('', $post->metaDescription);
    }

    public function test_the_same_accessors_still_work_normally_in_spanish(): void
    {
        $post = $this->spanishOnlyPost();

        app()->setLocale('es');

        $this->assertSame('Título en español', $post->title);
        $this->assertSame('Extracto en español.', $post->excerpt);
        $this->assertSame('<p>Cuerpo en español.</p>', $post->body);
    }

    public function test_accessors_return_the_real_translation_when_it_exists(): void
    {
        $post = $this->spanishOnlyPost([
            'title_en' => 'English Title',
            'excerpt_en' => 'English excerpt.',
            'body_en' => '<p>English body.</p>',
        ]);

        app()->setLocale('en');

        $this->assertSame('English Title', $post->title);
        $this->assertSame('English excerpt.', $post->excerpt);
        $this->assertSame('<p>English body.</p>', $post->body);
    }

    public function test_is_available_in_is_true_only_for_locales_with_both_title_and_body(): void
    {
        $post = $this->spanishOnlyPost();

        $this->assertTrue($post->isAvailableIn('es'));
        $this->assertFalse($post->isAvailableIn('en'));
        $this->assertFalse($post->isAvailableIn('pt'));
    }

    public function test_is_available_in_returns_false_for_an_unsupported_locale(): void
    {
        $post = $this->spanishOnlyPost();

        $this->assertFalse($post->isAvailableIn('fr'));
    }

    public function test_is_available_in_is_false_when_the_translation_is_only_whitespace(): void
    {
        $post = $this->spanishOnlyPost([
            'title_en' => '   ',
            'body_en' => "\n\t ",
        ]);

        $this->assertFalse($post->isAvailableIn('en'));
    }

    public function test_is_available_in_is_true_when_title_and_body_are_both_filled(): void
    {
        $post = $this->spanishOnlyPost([
            'title_en' => 'English Title',
            'body_en' => '<p>English body.</p>',
            // excerpt_en se deja vacío a propósito: no es parte del criterio.
        ]);

        $this->assertTrue($post->isAvailableIn('en'));
    }
}
