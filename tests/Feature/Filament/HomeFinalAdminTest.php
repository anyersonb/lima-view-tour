<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Filament\Resources\TestimonialResource\Pages\CreateTestimonial;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Models\User;
use App\Support\HomeFinalSections;
use App\Support\HomeSearchChips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin del home FINAL: chips del buscador, cancelación de la banda de
 * confianza (Ajustes -> Home) y fotos (máx. 4) en el testimonio.
 */
class HomeFinalAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_campos_nuevos_existen_en_ajustes(): void
    {
        $this->actingAs($this->admin());

        $c = Livewire::test(Settings::class)->assertOk();
        foreach ([
            'home_search_chips',
            'home_trust_cancel_title_es', 'home_trust_cancel_title_en', 'home_trust_cancel_title_pt',
            'home_trust_cancel_text_es', 'home_trust_cancel_text_en', 'home_trust_cancel_text_pt',
        ] as $field) {
            $c->assertFormFieldExists($field);
        }
    }

    public function test_guardar_chips_y_cancelacion_se_refleja_en_los_datos_del_front(): void
    {
        $this->actingAs($this->admin());
        app()->setLocale('es');
        Category::create(['name_es' => 'Arqueología', 'slug' => 'arqueologia', 'is_active' => true, 'order' => 1]);
        Tour::factory()->create(['is_published' => true, 'category_id' => Category::first()->id, 'duration' => 'Full Day']);

        Livewire::test(Settings::class)
            ->fillForm([
                'home_search_chips' => [
                    ['label_es' => 'Arqueología', 'label_en' => 'Archaeology', 'label_pt' => '', 'type' => 'categoria', 'value' => 'arqueologia'],
                ],
                'home_trust_cancel_title_es' => 'Cancelación gratuita',
                'home_trust_cancel_text_es' => 'Hasta 24 h antes',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $chips = HomeSearchChips::all('es');
        $this->assertSame(['Arqueología'], array_column($chips, 'label'));
        $this->assertSame('categoria:arqueologia', $chips[0]['key']);
        $this->assertSame(['Archaeology'], array_column(HomeSearchChips::all('en'), 'label'));

        $c = HomeFinalSections::cancellation('es');
        $this->assertSame('Cancelación gratuita', $c['title']);
        $this->assertSame('Hasta 24 h antes', $c['text']);
    }

    public function test_vaciar_los_chips_vuelve_a_los_automaticos_y_vaciar_cancelacion_oculta(): void
    {
        $this->actingAs($this->admin());
        Tour::factory()->create(['is_published' => true, 'duration' => 'Full Day']);
        Setting::set('home_search_chips', json_encode([['label_es' => 'X', 'type' => 'duracion', 'value' => '1-dia']]));
        Setting::set('home_trust_cancel_title_es', 'Algo');

        Livewire::test(Settings::class)
            ->fillForm(['home_search_chips' => [], 'home_trust_cancel_title_es' => '', 'home_trust_cancel_text_es' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertContains('duracion:1-dia', array_column(HomeSearchChips::all('es'), 'key'));
        $this->assertSame('Día completo', HomeSearchChips::all('es')[0]['label']);
        $this->assertNull(HomeFinalSections::cancellation('es'));
    }

    public function test_testimonio_acepta_hasta_4_fotos_y_rechaza_5(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $data = [
            'name' => 'Juan', 'quote_es' => 'Excelente experiencia, lo recomiendo.', 'rating' => 5,
            'source' => 'Viator', 'status' => 'approved', 'is_featured' => true, 'is_active' => true, 'order' => 0,
        ];

        $cuatro = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg", 400, 300), range(1, 4));
        Livewire::test(CreateTestimonial::class)
            ->fillForm($data + ['photos' => $cuatro])
            ->call('create')
            ->assertHasNoFormErrors();

        $t = Testimonial::firstOrFail();
        $this->assertCount(4, $t->photos);
        $this->assertSame('viator', HomeFinalSections::platformKey($t->source));

        $cinco = array_map(fn ($i) => UploadedFile::fake()->image("g{$i}.jpg", 400, 300), range(1, 5));
        Livewire::test(CreateTestimonial::class)
            ->fillForm($data + ['photos' => $cinco])
            ->call('create')
            ->assertHasFormErrors(['photos']);

        $this->assertSame(1, Testimonial::count());
    }
}
