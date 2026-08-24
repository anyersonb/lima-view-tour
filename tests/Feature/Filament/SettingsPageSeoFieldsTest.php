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
 * El bloque "Metas por página" (Configuración → SEO) se arma en un bucle sobre
 * PageSeo::PAGES con helperText por closure. Un test sobre PageSeo solo no
 * probaría nada de eso: hay que montar la página real de Filament, llenar el
 * campo y guardarlo, que es donde explotaría un closure mal evaluado o una
 * clave que el save() no persiste.
 */
class SettingsPageSeoFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_la_pagina_de_configuracion_monta_con_el_bloque_de_metas_por_pagina(): void
    {
        $this->actingAs($this->admin());

        $component = Livewire::test(Settings::class)->assertOk();

        foreach (array_keys(PageSeo::PAGES) as $page) {
            foreach (array_keys(PageSeo::LOCALES) as $locale) {
                $component->assertFormFieldExists(PageSeo::settingKey($page, 'title', $locale));
                $component->assertFormFieldExists(PageSeo::settingKey($page, 'description', $locale));
            }
        }
    }

    public function test_guardar_desde_el_admin_persiste_la_meta_y_el_front_la_emite(): void
    {
        $this->actingAs($this->admin());

        $titleKey = PageSeo::settingKey('home', 'title', 'es');
        $descKey = PageSeo::settingKey('home', 'description', 'es');

        Livewire::test(Settings::class)
            ->fillForm([
                $titleKey => 'Home guardada desde el admin',
                $descKey => 'Description guardada desde el admin.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Home guardada desde el admin', Setting::get($titleKey));

        $html = $this->get('/es')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Home guardada desde el admin</title>', $html);
        $this->assertStringContainsString('Description guardada desde el admin.', $html);
    }

    public function test_vaciar_el_campo_desde_el_admin_vuelve_al_texto_por_defecto(): void
    {
        $this->actingAs($this->admin());

        $titleKey = PageSeo::settingKey('home', 'title', 'es');
        Setting::set($titleKey, 'Meta que voy a borrar');

        Livewire::test(Settings::class)
            ->fillForm([$titleKey => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $html = $this->get('/es')->assertOk()->getContent();
        $this->assertStringNotContainsString('Meta que voy a borrar', $html);
        $this->assertStringContainsString('<title>' . __('seo.home_title', [], 'es') . '</title>', $html);
    }
}
