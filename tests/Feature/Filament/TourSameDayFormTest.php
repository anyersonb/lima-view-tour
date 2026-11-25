<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin (2026-11-24): toggle "mismo día", margen por tour, varios horarios y el
 * interruptor "Mostrar PayPal en el checkout". Se maneja el formulario real
 * de Filament (Livewire).
 */
class TourSameDayFormTest extends TestCase
{
    use RefreshDatabase;

    private function tour(): Tour
    {
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));

        return Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null]);
    }

    /** @test */
    public function the_toggle_the_margin_and_the_slots_are_saved_normalized(): void
    {
        $tour = $this->tour();

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm([
                'allows_same_day' => true,
                'same_day_min_hours' => 3,
                'departure_times' => ['14:30', '09:00', '09:00'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tour->refresh();
        $this->assertTrue($tour->allows_same_day);
        $this->assertSame(3, (int) $tour->same_day_min_hours);
        $this->assertSame(['09:00', '14:30'], $tour->departure_times);
    }

    /** @test */
    public function an_unreadable_slot_is_rejected_in_the_form(): void
    {
        $tour = $this->tour();

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['departure_times' => ['mañana temprano']])
            ->call('save')
            ->assertHasFormErrors();
    }

    /** @test */
    public function the_paypal_switch_is_off_by_default_and_saves_without_touching_credentials(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));
        Setting::set('paypal_client_id', 'cid-live');
        Setting::set('paypal_mode', 'live');
        config(['services.paypal.visible_default' => false]);

        $page = Livewire::test(Settings::class)->assertFormSet(['paypal_visible' => false]);
        $page->fillForm(['paypal_visible' => true])->call('save');

        $this->assertTrue((bool) Setting::get('paypal_visible'));
        $this->assertSame('cid-live', Setting::get('paypal_client_id'));
        $this->assertSame('live', Setting::get('paypal_mode'));
        $this->assertTrue(\App\Services\PayPalService::availableForCustomers());
    }
}
