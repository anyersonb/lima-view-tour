<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Support\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lote A (2026-08-31): campo nuevo de JSON-LD dentro de "Metas por página" y
 * el nuevo "Schema global del sitio", ambos en Configuración → SEO. Sigue el
 * mismo patrón de tests/Feature/Filament/SettingsPageSeoFieldsTest.php
 * (montar la página real de Filament, no el closure aislado) y el mismo
 * criterio de validación de tests/Feature/Filament/SeoJsonLdFormSaveTest.php.
 */
class SchemaJsonLdSettingsFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    // ── Metas por página → schema ────────────────────────────────────────

    public function test_settings_page_mounts_with_a_schema_field_per_page_and_locale(): void
    {
        $this->actingAs($this->admin());

        $component = Livewire::test(Settings::class)->assertOk();

        foreach (array_keys(PageSeo::PAGES) as $page) {
            foreach (array_keys(PageSeo::LOCALES) as $locale) {
                $component->assertFormFieldExists(PageSeo::settingKey($page, 'schema', $locale));
            }
        }
    }

    public function test_saving_a_valid_page_schema_persists_it(): void
    {
        $this->actingAs($this->admin());

        $key = PageSeo::settingKey('home', 'schema', 'es');

        Livewire::test(Settings::class)
            ->fillForm([$key => json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage'])])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertJson((string) Setting::get($key));
    }

    public function test_saving_broken_page_schema_is_rejected_as_a_form_error(): void
    {
        $this->actingAs($this->admin());

        $key = PageSeo::settingKey('tours', 'schema', 'es');

        Livewire::test(Settings::class)
            ->fillForm([$key => '{"a":'])
            ->call('save')
            ->assertHasFormErrors([$key]);

        $this->assertNull(Setting::get($key), 'Un JSON-LD roto no debe llegar a persistirse.');
    }

    public function test_an_empty_page_schema_is_optional_and_does_not_block_saving(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->fillForm([PageSeo::settingKey('blog', 'schema', 'en') => ''])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    // ── Schema global del sitio ──────────────────────────────────────────

    public function test_settings_page_mounts_with_the_global_schema_fields_for_all_three_locales(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertFormFieldExists('schema_jsonld_global_es')
            ->assertFormFieldExists('schema_jsonld_global_en')
            ->assertFormFieldExists('schema_jsonld_global_pt');
    }

    public function test_global_schema_has_no_prefilled_value_on_mount(): void
    {
        // Requisito explícito del brief: vacío por defecto, sin ningún valor
        // precargado (a diferencia de paypal/recaptcha/google keys, que sí
        // se prellenan desde .env en Settings::mount()).
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertFormSet([
                'schema_jsonld_global_es' => null,
                'schema_jsonld_global_en' => null,
                'schema_jsonld_global_pt' => null,
            ]);
    }

    public function test_saving_a_valid_global_schema_persists_it(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->fillForm(['schema_jsonld_global_es' => json_encode(['@context' => 'https://schema.org', '@type' => 'Organization'])])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertJson((string) Setting::get('schema_jsonld_global_es'));
    }

    public function test_saving_broken_global_schema_is_rejected_as_a_form_error(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->fillForm(['schema_jsonld_global_pt' => '{"a":'])
            ->call('save')
            ->assertHasFormErrors(['schema_jsonld_global_pt']);

        $this->assertNull(Setting::get('schema_jsonld_global_pt'));
    }

    // ── Endurecimiento 2026-08-31: defensa en profundidad + usabilidad ──
    // (el control real de seguridad vive en el render, ver
    // components/schema-raw.blade.php). Esto solo evita el error honesto de
    // pegar el <script> envolvente o un comentario HTML junto con el JSON.

    public function test_saving_a_global_schema_with_a_closing_script_tag_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $payload = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Lima View Tours</script><img src=x onerror=alert(document.domain)><script>',
            'url' => 'https://limaviewtours.com',
        ]);

        Livewire::test(Settings::class)
            ->fillForm(['schema_jsonld_global_es' => $payload])
            ->call('save')
            ->assertHasFormErrors(['schema_jsonld_global_es']);

        $this->assertNull(
            Setting::get('schema_jsonld_global_es'),
            'Un JSON-LD con </script> embebido no debe llegar a persistirse.'
        );
    }

    public function test_saving_a_global_schema_with_an_html_comment_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $payload = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Lima View Tours<!-- inyectado -->',
        ]);

        Livewire::test(Settings::class)
            ->fillForm(['schema_jsonld_global_en' => $payload])
            ->call('save')
            ->assertHasFormErrors(['schema_jsonld_global_en']);

        $this->assertNull(Setting::get('schema_jsonld_global_en'));
    }

    public function test_saving_a_global_schema_wrapped_in_script_tags_is_rejected_with_that_exact_mistake(): void
    {
        // El error honesto que se documenta en el mensaje de validación:
        // pegar el <script type="application/ld+json">...</script> completo
        // (como lo entrega cualquier generador de schema) en vez de solo el
        // objeto JSON.
        $this->actingAs($this->admin());

        $wrapped = '<script type="application/ld+json">'
            .json_encode(['@context' => 'https://schema.org', '@type' => 'Organization'])
            .'</script>';

        Livewire::test(Settings::class)
            ->fillForm(['schema_jsonld_global_pt' => $wrapped])
            ->call('save')
            ->assertHasFormErrors(['schema_jsonld_global_pt']);
    }

    public function test_saving_a_normal_global_schema_without_forbidden_markers_is_still_accepted(): void
    {
        // Control negativo del endurecimiento: un JSON benigno normal sigue
        // guardando sin problema (no se volvió sobre-estricto).
        $this->actingAs($this->admin());

        $payload = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Lima View Tours',
            'url' => 'https://limaviewtours.com',
        ]);

        Livewire::test(Settings::class)
            ->fillForm(['schema_jsonld_global_es' => $payload])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertJson((string) Setting::get('schema_jsonld_global_es'));
    }
}
