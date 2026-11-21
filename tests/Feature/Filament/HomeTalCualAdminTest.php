<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Support\HomeFinalSections;
use App\Support\HomeSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin del home TAL CUAL (21-nov): campos de tarjeta en el Tour, tour estrella
 * completo y ventanas de la banda de confianza en Ajustes -> Home.
 */
class HomeTalCualAdminTest extends TestCase
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
        foreach (['es', 'en', 'pt'] as $l) {
            foreach (['badge', 'headline', 'subtitle', 'question', 'chips'] as $f) {
                $c->assertFormFieldExists("home_star_{$f}_{$l}");
            }
            foreach (['cancel', 'years', 'travelers', 'rating'] as $k) {
                $c->assertFormFieldExists("home_trust_detail_{$k}_{$l}");
            }
        }
    }

    public function test_guardar_estrella_hitos_con_detalle_y_ventanas_se_refleja_en_el_front(): void
    {
        $this->actingAs($this->admin());
        $lima = Region::create(['name_es' => 'Lima', 'slug' => 'lima', 'is_active' => true, 'order' => 1]);
        $tour = Tour::factory()->create(['is_published' => true, 'region_id' => $lima->id]);

        Livewire::test(Settings::class)
            ->fillForm([
                'home_star_tour_id' => $tour->id,
                'home_star_badge_es' => 'EL TOUR MÁS VENDIDO EN LIMA',
                'home_star_headline_es' => 'Aventura en un solo día.',
                'home_star_chips_es' => [['text' => 'BUGGY'], ['text' => 'OASIS']],
                'home_star_milestones_es' => [['text' => 'Buggies', 'detail' => 'Adrenalina en dunas']],
                'stats_years' => '+10',
                'home_trust_detail_years_es' => 'Más de diez años guiando viajeros.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $s = HomeSections::star('es');
        $this->assertSame('EL TOUR MÁS VENDIDO EN LIMA', $s['badge']);
        $this->assertSame('Aventura en un solo día.', $s['headline']);
        $this->assertSame(['BUGGY', 'OASIS'], $s['chips']);
        $this->assertSame([['title' => 'Buggies', 'detail' => 'Adrenalina en dunas']], $s['milestone_items']);

        $years = collect(HomeFinalSections::trust('es')['items'])->firstWhere('key', 'years');
        $this->assertSame('Más de diez años guiando viajeros.', $years['detail']);
    }

    public function test_hitos_cargados_antes_solo_con_texto_se_conservan_al_abrir_y_guardar(): void
    {
        $this->actingAs($this->admin());
        Setting::set('home_star_milestones_es', json_encode([['text' => 'Paseo en lancha']]));

        Livewire::test(Settings::class)
            ->assertFormSet(['home_star_milestones_es' => fn (array $v) => array_values($v)[0]['text'] === 'Paseo en lancha'])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = json_decode((string) Setting::where('key', 'home_star_milestones_es')->value('value'), true);
        $this->assertSame('Paseo en lancha', $stored[0]['text']);
    }

    public function test_tour_guarda_titulo_corto_ubicacion_y_chips_de_tarjeta(): void
    {
        $this->actingAs($this->admin());
        $tour = Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'rating' => 0]);

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm([
                'card_title_es' => 'Pachacámac + museo',
                'card_location_es' => 'PACHACÁMAC',
                'card_chips_es' => [['text' => '4 horas', 'accent' => false], ['text' => 'Entradas', 'accent' => true]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tour->refresh();
        $this->assertSame('Pachacámac + museo', $tour->cardTitle('es'));
        $this->assertSame('PACHACÁMAC', $tour->cardLocation('es'));
        $this->assertSame([['text' => '4 horas', 'accent' => false], ['text' => 'Entradas', 'accent' => true]], $tour->cardChips('es'));
    }

    public function test_tour_rechaza_titulo_corto_demasiado_largo(): void
    {
        $this->actingAs($this->admin());
        $tour = Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'rating' => 0]);

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['card_title_es' => str_repeat('x', 91)])
            ->call('save')
            ->assertHasFormErrors(['card_title_es']);
    }
}
