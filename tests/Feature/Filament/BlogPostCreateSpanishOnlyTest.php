<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BlogPostResource\Pages\CreateBlogPost;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hotfix (2026-09-24): /admin/blog-posts/create daba 500
 * ("SQLSTATE[23000]: ... Column 'title_en' cannot be null") al guardar un
 * artículo llenando solo la pestaña Español. title_en/excerpt_en/body_en (y
 * _pt) eran NOT NULL sin default (2026_06_29_000010_create_blog_posts_table.php)
 * mientras que las pestañas English/Português son opcionales en
 * BlogPostResource.php (l.84-93 y 124-133): Filament manda NULL para esos
 * seis campos y MySQL rechazaba el INSERT.
 *
 * Este test pasa por el camino real del usuario (Livewire::test sobre la
 * página de creación, no BlogPost::create() directo) para que quede blindado
 * si algún día alguien vuelve a poner esas columnas en NOT NULL sin ajustar
 * el formulario. Sin la migración
 * 2026_09_24_000000_make_blog_posts_i18n_columns_nullable.php este test
 * revienta con QueryException, igual que el 500 original.
 */
class BlogPostCreateSpanishOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_creating_a_blog_post_with_only_the_spanish_tab_filled_does_not_500(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateBlogPost::class)
            ->fillForm([
                'title_es' => 'Guía rápida de Lima',
                'excerpt_es' => 'Extracto en español para la guía de Lima.',
                'body_es' => '<p>Cuerpo del artículo, solo en español.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = BlogPost::where('title_es', 'Guía rápida de Lima')->first();

        $this->assertNotNull($post, 'El artículo no se persistió: el 500 original sigue vivo.');
        $this->assertNull($post->title_en);
        $this->assertNull($post->excerpt_en);
        $this->assertNull($post->body_en);
        $this->assertNull($post->title_pt);
        $this->assertNull($post->excerpt_pt);
        $this->assertNull($post->body_pt);
        // Punto 7 del cambio de requisito: slug_en/slug_pt no deben quedar
        // en '' cuando title_en/title_pt están vacíos — slugSanitize() (ver
        // HasEditableSlugField) ya los sanea a NULL, no a cadena vacía, así
        // que dos posts solo-ES nunca chocan en el índice UNIQUE de esas
        // columnas (NULL != NULL para MySQL/SQLite).
        $this->assertNull($post->slug_en);
        $this->assertNull($post->slug_pt);
    }

    /**
     * Punto 7: dos notas solo-ES seguidas, creadas por el camino real del
     * panel, no deben chocar por un slug_en/slug_pt "vacío" compartido. Si
     * slugSanitize() alguna vez dejara de convertir '' en NULL, este test lo
     * atraparía con un QueryException por violar el índice UNIQUE de
     * slug_en/slug_pt.
     */
    public function test_two_spanish_only_posts_created_back_to_back_do_not_collide_on_slug_en_or_slug_pt(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateBlogPost::class)
            ->fillForm([
                'title_es' => 'Primera nota solo español',
                'excerpt_es' => 'Extracto uno.',
                'body_es' => '<p>Cuerpo uno.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateBlogPost::class)
            ->fillForm([
                'title_es' => 'Segunda nota solo español',
                'excerpt_es' => 'Extracto dos.',
                'body_es' => '<p>Cuerpo dos.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, BlogPost::count());
    }

    /**
     * Cambio de requisito (2026-09-24, mismo día): esto probaba lo contrario
     * hasta hace un rato — un post solo-ES debía mostrar el fallback en
     * español bajo /en y /pt. El cliente pidió revertir esa regla: EN/PT
     * siguen siendo opcionales, pero si la traducción está vacía la nota NO
     * se muestra en ese idioma. Ver BlogPost::isAvailableIn() y
     * tests/Feature/Seo/BlogTranslationAvailabilityTest.php para la cobertura
     * completa (listado, sitemap, hreflang, selector de idioma).
     */
    public function test_a_spanish_only_post_created_from_the_panel_404s_under_en_and_pt_public_routes(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateBlogPost::class)
            ->fillForm([
                'title_es' => 'Guía rápida de Cusco',
                'excerpt_es' => 'Extracto en español para la guía de Cusco.',
                'body_es' => '<p>Cuerpo del artículo, solo en español.</p>',
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = BlogPost::where('title_es', 'Guía rápida de Cusco')->first();
        $this->assertNotNull($post);

        $es = $this->get('/es/blog/' . $post->slug);
        $es->assertOk();
        $es->assertSee('Guía rápida de Cusco');

        $en = $this->get('/en/blog/' . $post->slug);
        $en->assertNotFound();

        $pt = $this->get('/pt/blog/' . $post->slug);
        $pt->assertNotFound();
    }
}
