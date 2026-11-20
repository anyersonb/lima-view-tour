<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Support\HomeSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ajustes → Home / Datos legales: tour estrella, comparador, fuente de la
 * valoración y resumen de cancelación. Se monta la página real de Filament,
 * se guarda y se lee por el mismo camino que usa el front.
 */
class SettingsHomeRedesignFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_los_campos_nuevos_existen_en_el_formulario(): void
    {
        $this->actingAs($this->admin());

        $c = Livewire::test(Settings::class)->assertOk();

        foreach ([
            'home_star_tour_id', 'home_star_title_es', 'home_star_lead_en', 'home_star_milestones_pt',
            'home_compare_tour_a', 'home_compare_tour_b', 'home_compare_intro_es',
            'stats_rating_source_es', 'stats_rating_source_en', 'stats_rating_source_pt',
            'legal_cancellation_summary_es', 'legal_cancellation_summary_en', 'legal_cancellation_summary_pt', 'home_score_tripadvisor', 'home_score_viator', 'home_score_getyourguide', 'home_cta_eyebrow_es', 'home_cta_title_en', 'home_cta_sub_pt',
        ] as $field) {
            $c->assertFormFieldExists($field);
        }
    }

    public function test_guardar_tour_estrella_hitos_comparador_y_fuente_se_refleja_en_los_datos_del_front(): void
    {
        $this->actingAs($this->admin());
        app()->setLocale('es');

        $a = Tour::factory()->create(['title_es' => 'Tour A']);
        $b = Tour::factory()->create(['title_es' => 'Tour B']);

        Livewire::test(Settings::class)
            ->fillForm([
                'home_star_tour_id' => $a->id,
                'home_star_title_es' => 'Un día entre desierto y mar',
                'home_star_milestones_es' => [['text' => 'Hito uno'], ['text' => 'Hito dos']],
                'home_compare_tour_a' => $a->id,
                'home_compare_tour_b' => $b->id,
                'stats_rating' => '4.9',
                'stats_rating_source_es' => 'Tripadvisor',
                'legal_cancellation_summary_es' => 'Cancela gratis hasta 24 h antes.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $star = HomeSections::star('es');
        $this->assertSame($a->id, $star['tour']->id);
        $this->assertSame('Un día entre desierto y mar', $star['title']);
        $this->assertSame(['Hito uno', 'Hito dos'], $star['milestones']);

        $this->assertSame([$a->id, $b->id], array_column(HomeSections::compare()['tours'], 'id'));
        $this->assertSame('Tripadvisor', HomeSections::stats('es')['rating']['source']);
        $this->assertSame('4.9', HomeSections::stats('es')['rating']['value']);
        $this->assertSame('Cancela gratis hasta 24 h antes.', \App\Support\CheckoutInfo::build(collect(), 'es')['cancellation_summary']);
    }

    public function test_el_comparador_rechaza_el_mismo_tour_dos_veces(): void
    {
        $this->actingAs($this->admin());
        $a = Tour::factory()->create();

        Livewire::test(Settings::class)
            ->fillForm(['home_compare_tour_a' => $a->id, 'home_compare_tour_b' => $a->id])
            ->call('save')
            ->assertHasFormErrors(['home_compare_tour_a']);
    }

    public function test_dejar_vacio_oculta_las_secciones(): void
    {
        $this->actingAs($this->admin());
        $a = Tour::factory()->create();
        Setting::set('home_star_tour_id', (string) $a->id);

        Livewire::test(Settings::class)
            ->fillForm(['home_star_tour_id' => null, 'home_compare_tour_a' => null, 'home_compare_tour_b' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(HomeSections::star());
        $this->assertNull(HomeSections::compare());
    }

    public function test_cta_y_puntuaciones_se_guardan_y_el_titulo_rechaza_codigo_ajeno(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->fillForm([
                'home_score_viator' => '4.8',
                'home_cta_title_es' => 'El Perú <span class="lvt-accent">te espera</span>',
                'home_cta_sub_es' => 'Reserva ya',
            ])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('4.8', Setting::get('home_score_viator'));
        $this->assertSame('Reserva ya', Setting::get('home_cta_sub_es'));

        Livewire::test(Settings::class)
            ->fillForm(['home_cta_title_es' => 'Hola <script>alert(1)</script>'])
            ->call('save')
            ->assertHasFormErrors(['home_cta_title_es']);
    }
}
