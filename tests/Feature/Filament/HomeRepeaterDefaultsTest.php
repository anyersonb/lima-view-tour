<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Support\HomeDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los repeaters del home en Ajustes aparecían VACÍOS mientras la web mostraba
 * los textos por defecto; al añadir 1 elemento la web pasaba de N a 1. Ahora el
 * formulario se precarga con HomeDefaults y guardar sin tocar nada no cambia
 * lo que se ve.
 */
class HomeRepeaterDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private const CHOOSE_LI = '<li class="fh-step">'; // home FINAL 20-nov

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_con_claves_vacias_el_formulario_trae_los_defaults(): void
    {
        $this->actingAs($this->admin());

        $component = Livewire::test(Settings::class)->assertOk();

        $items = array_values($component->get('data.home_choose_items'));
        $defaults = HomeDefaults::chooseItems();

        $this->assertCount(4, $items);
        foreach ($defaults as $i => $default) {
            $this->assertSame($default['title_es'], $items[$i]['title_es']);
            $this->assertSame($default['desc_en'], $items[$i]['desc_en']);
            // Lo puramente visual no ensucia el formulario.
            $this->assertArrayNotHasKey('iconPath', $items[$i]);
        }

        foreach (['home_destinos' => 3, 'home_why_items' => 5, 'home_tour_type_tabs' => 4,
            'home_footer_features' => 4, 'home_exp_tours' => 4, 'home_reco_items' => 4, 'home_faqs' => 6] as $key => $count) {
            $this->assertCount($count, $component->get('data.' . $key), $key);
        }

        $tabs = array_values($component->get('data.home_tour_type_tabs'));
        $this->assertSame('cult', $tabs[0]['id']);
        $this->assertArrayNotHasKey('img', $tabs[0]);
        $exp = array_values($component->get('data.home_exp_tours'));
        $this->assertArrayNotHasKey('img', $exp[0]);
        $this->assertSame('excursion-dia-completo-lima', $exp[0]['slug']);
    }

    public function test_guardar_sin_cambios_deja_la_web_igual_que_los_defaults(): void
    {
        $this->actingAs($this->admin());

        $antes = $this->get('/es')->assertOk()->getContent();

        Livewire::test(Settings::class)->call('save')->assertHasNoFormErrors();

        $html = $this->get('/es')->assertOk()->getContent();

        // "Cómo elegir tu tour" salió del home (21-nov): ahora vive al final de /tours y NO en el home.
        $this->assertSame(0, substr_count($html, self::CHOOSE_LI));
        $tours = $this->get('/es/tours')->assertOk()->getContent();
        $this->assertSame(4, substr_count($tours, self::CHOOSE_LI));
        foreach (HomeDefaults::chooseItems() as $item) {
            $this->assertStringContainsString(e($item['title_es']), $tours);
        }
        foreach (HomeDefaults::destinos() as $item) {
            $this->assertStringContainsString(e($item['title_es']), $html);
        }
        // Lo visual heredado por posición sigue ahí.

        // Mismo HTML que antes de guardar (normalizando tokens y espacios).
        $norm = static fn (string $h) => preg_replace('/\s+/', ' ', preg_replace(
            ['/(csrf-token" content=")[^"]+/', '/name="_token" value="[^"]+"/', '/nonce="[^"]+"/', '/wire:id="[^"]+"/'],
            ['$1X', 'name="_token" value="X"', 'nonce="X"', 'wire:id="X"'],
            $h
        ));
        // Solo el <body>: el <head> cambia por otros ajustes (nombre del sitio,
        // geo...) que el save() del admin persiste vacíos; no tiene que ver con
        // los repeaters.
        $body = static fn (string $h) => substr($h, (int) strpos($h, '<body'));
        $this->assertSame($body($norm($antes)), $body($norm($html)));
    }

    public function test_editar_el_item_1_no_borra_los_otros(): void
    {
        $this->actingAs($this->admin());

        $component = Livewire::test(Settings::class);
        $firstUuid = array_key_first($component->get('data.home_choose_items'));

        $component->set('data.home_choose_items.' . $firstUuid . '.title_es', 'Consejo editado por el cliente')
            ->call('save')
            ->assertHasNoFormErrors();

        $html = $this->get('/es/tours')->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, self::CHOOSE_LI));
        $this->assertStringContainsString('Consejo editado por el cliente', $html);
        $this->assertStringNotContainsString(e(HomeDefaults::chooseItems()[0]['title_es']), $html);
        foreach (array_slice(HomeDefaults::chooseItems(), 1) as $item) {
            $this->assertStringContainsString(e($item['title_es']), $html);
        }
    }

    public function test_en_ingles_con_defaults_muestra_los_textos_en(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)->call('save')->assertHasNoFormErrors();

        $html = $this->get('/en/tours')->assertOk()->getContent(); // "Cómo elegir" vive al final de /tours desde el 21-nov

        foreach (HomeDefaults::chooseItems() as $item) {
            $this->assertStringContainsString(e($item['title_en']), $html);
            $this->assertStringContainsString(e($item['desc_en']), $html);
        }
        foreach (HomeDefaults::destinos() as $item) {
            $this->assertStringContainsString(e($item['title_en']), $this->get('/en')->getContent());
        }
        $this->assertStringNotContainsString(e(HomeDefaults::chooseItems()[0]['title_es']), $html);
    }

    public function test_una_clave_guardada_con_items_no_se_pisa_con_los_defaults(): void
    {
        $this->actingAs($this->admin());

        Setting::set('home_choose_items', json_encode([
            ['title_es' => 'Solo uno', 'title_en' => 'Only one', 'title_pt' => 'So um'],
        ]));

        $component = Livewire::test(Settings::class);

        $this->assertCount(1, $component->get('data.home_choose_items'));
    }
}
