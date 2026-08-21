<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BlogPostResource\Pages\CreateBlogPost;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TourResource\Pages\CreateTour;
use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test for the CRO-reported blocker: saving ANY record from
 * TourResource, PageResource or BlogPostResource crashed with a
 * BindingResolutionException ("[$attribute] was unresolvable") coming from
 * Filament\Support\Concerns\EvaluatesClosures — because the JSON-LD
 * ->rules([...]) closure had Laravel's validation signature
 * (string $attribute, $value, Closure $fail), which Filament's own
 * closure-evaluator cannot dependency-inject.
 *
 * These tests exercise the REAL Filament/Livewire save path (mount → fill →
 * call('create'/'save')), not the closure in isolation, because a closure
 * unit test can pass while the actual Filament wiring still explodes — which
 * is exactly what happened before this fix (see tests/Unit/Filament/SeoJsonLdRuleTest.php).
 *
 * IMPORTANT: run this test file against the pre-fix version of
 * HasLocalizedSeoFields::seoJsonLdRule() to confirm it fails there (it must
 * throw Filament\Support\Exceptions\Halt or a BindingResolutionException /
 * 500, not a clean assertHasFormErrors()).
 */
class SeoJsonLdFormSaveTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'qa@webtilia.com',
        ]);
    }

    // ── Tour ─────────────────────────────────────────────────────────────

    public function test_tour_is_created_with_valid_jsonld(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateTour::class)
            ->fillForm([
                'title_es' => 'Tour de prueba JSON-LD',
                'slug' => 'tour-de-prueba-jsonld',
                'price' => 100,
                'currency' => 'USD',
                'schema_jsonld_es' => json_encode(['@context' => 'https://schema.org', '@type' => 'Product']),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tour = Tour::where('slug', 'tour-de-prueba-jsonld')->first();
        $this->assertNotNull($tour, 'The tour must have been persisted.');
        $this->assertJson((string) $tour->schema_jsonld_es);
    }

    public function test_tour_is_created_with_empty_jsonld_because_field_is_optional(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateTour::class)
            ->fillForm([
                'title_es' => 'Tour sin JSON-LD',
                'slug' => 'tour-sin-jsonld',
                'price' => 80,
                'currency' => 'USD',
                'schema_jsonld_es' => '',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tours', ['slug' => 'tour-sin-jsonld']);
    }

    public function test_tour_creation_rejects_broken_jsonld_as_a_form_validation_error(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateTour::class)
            ->fillForm([
                'title_es' => 'Tour con JSON-LD roto',
                'slug' => 'tour-jsonld-roto',
                'price' => 90,
                'currency' => 'USD',
                'schema_jsonld_es' => '{"a":',
            ])
            ->call('create')
            ->assertHasFormErrors(['schema_jsonld_es']);

        // Broken JSON-LD must not reach the database as a validation error,
        // not as an uncaught exception / 500.
        $this->assertDatabaseMissing('tours', ['slug' => 'tour-jsonld-roto']);
    }

    public function test_tour_edit_saves_without_touching_the_seo_section(): void
    {
        $tour = Tour::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
            ->fillForm([
                'title_es' => 'Título actualizado sin tocar SEO',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Título actualizado sin tocar SEO', $tour->fresh()->title_es);
    }

    /**
     * Context from the brief: the ES slug field is disabled + not dehydrated
     * on edit (blindaje) so 4 tours whose slug is already > 60 chars don't
     * get truncated/rejected when someone edits an unrelated field. CRO
     * could never exercise a real save because of the #1 crash, so this was
     * left unverified. Confirm the guard survives the fix.
     */
    public function test_long_legacy_slug_survives_an_unrelated_edit(): void
    {
        $longSlug = str_repeat('a', 80);
        $tour = Tour::factory()->create(['slug' => $longSlug]);

        $this->actingAs($this->admin());

        Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
            ->fillForm([
                'title_es' => 'Edición que no toca el slug',
                'schema_jsonld_es' => json_encode(['@context' => 'https://schema.org']),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($longSlug, $tour->fresh()->slug, 'The pre-existing >60-char slug must not be truncated/rejected on an unrelated edit.');
    }

    // ── Page ─────────────────────────────────────────────────────────────

    public function test_page_is_created_with_only_title_and_slug_and_no_jsonld(): void
    {
        // This is the exact CRO repro: "crear una Página nueva con solo
        // título y slug también crashea".
        $this->actingAs($this->admin());

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title_es' => 'Página de prueba',
                'slug' => 'pagina-de-prueba',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pages', ['slug' => 'pagina-de-prueba']);
    }

    public function test_page_creation_rejects_broken_jsonld(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title_es' => 'Página con JSON-LD roto',
                'slug' => 'pagina-jsonld-roto',
                'schema_jsonld_es' => '{"a":',
            ])
            ->call('create')
            ->assertHasFormErrors(['schema_jsonld_es']);

        $this->assertDatabaseMissing('pages', ['slug' => 'pagina-jsonld-roto']);
    }

    public function test_page_edit_saves_without_touching_the_seo_section(): void
    {
        $page = Page::create([
            'slug' => 'pagina-editable',
            'title_es' => 'Página editable',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'title_es' => 'Página editable actualizada',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Página editable actualizada', $page->fresh()->title_es);
    }

    // ── BlogPost ─────────────────────────────────────────────────────────

    public function test_blog_post_is_created_with_valid_jsonld(): void
    {
        $this->actingAs($this->admin());

        // title_en/excerpt_en/body_en (and _pt) are NOT NULL without a default
        // at the DB level (see finding #3, out of scope here) — filled with
        // placeholders so this test isolates the JSON-LD fix and doesn't trip
        // over that unrelated, already-documented issue.
        Livewire::test(CreateBlogPost::class)
            ->fillForm([
                'title_es' => 'Post de prueba',
                'excerpt_es' => 'Extracto de prueba',
                'body_es' => '<p>Cuerpo de prueba</p>',
                'title_en' => 'Test post',
                'excerpt_en' => 'Test excerpt',
                'body_en' => '<p>Test body</p>',
                'title_pt' => 'Post de teste',
                'excerpt_pt' => 'Resumo de teste',
                'body_pt' => '<p>Corpo de teste</p>',
                'slug' => 'post-de-prueba',
                'schema_jsonld_es' => json_encode(['@context' => 'https://schema.org', '@type' => 'BlogPosting']),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('blog_posts', ['slug' => 'post-de-prueba']);
    }

    public function test_blog_post_creation_rejects_broken_jsonld(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateBlogPost::class)
            ->fillForm([
                'title_es' => 'Post con JSON-LD roto',
                'excerpt_es' => 'Extracto',
                'body_es' => '<p>Cuerpo</p>',
                'slug' => 'post-jsonld-roto',
                'schema_jsonld_es' => '{"a":',
            ])
            ->call('create')
            ->assertHasFormErrors(['schema_jsonld_es']);

        $this->assertDatabaseMissing('blog_posts', ['slug' => 'post-jsonld-roto']);
    }
}
