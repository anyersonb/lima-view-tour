<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TourResource\Pages\CreateTour;
use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * QA 2026-10-11 (Q2 parte B): the "Hora salida" TimePicker showed the loaded
 * value but saving failed with "must match the format H:i". These tests drive
 * the real Filament form (Livewire), not just the model mutator.
 */
class TourDepartureTimeFormTest extends TestCase
{
    use RefreshDatabase;

    private function editTour(?string $time): \Testing\Dummy|Tour
    {
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));

        $tour = Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null]);
        $tour->forceFill(['departure_time' => $time])->saveQuietly();

        return $tour;
    }

    /** @test */
    public function saving_an_untouched_departure_time_works(): void
    {
        $tour = $this->editTour('13:18');

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->assertFormSet(fn (array $state) => str_starts_with((string) $state['departure_time'], '13:18'))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('13:18', $tour->refresh()->departure_time);
    }

    /** @test */
    public function a_new_departure_time_is_saved_as_hh_mm(): void
    {
        $tour = $this->editTour('13:18');

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['departure_time' => '07:45'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('07:45', $tour->refresh()->departure_time);
    }

    /** @test */
    public function the_picker_datetime_state_is_accepted(): void
    {
        $tour = $this->editTour('13:18');

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['departure_time' => '2026-10-11 07:45:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('07:45', $tour->refresh()->departure_time);
    }

    /** @test */
    public function clearing_the_departure_time_stores_null(): void
    {
        $tour = $this->editTour('13:18');

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['departure_time' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($tour->refresh()->departure_time);
    }

    /** @test */
    public function garbage_is_rejected_by_validation(): void
    {
        $tour = $this->editTour('13:18');

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['departure_time' => 'Todo el día'])
            ->call('save')
            ->assertHasFormErrors(['departure_time']);

        $this->assertSame('13:18', $tour->refresh()->departure_time);
    }

    /** @test */
    public function a_forged_array_departure_time_is_a_validation_error_not_a_500(): void
    {
        $tour = $this->editTour('13:18');

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['departure_time' => ['x' => '07:45']])
            ->call('save')
            ->assertHasFormErrors(['departure_time']);

        $this->assertSame('13:18', $tour->refresh()->departure_time);
    }

    /** @test */
    public function a_forged_int_departure_time_is_a_validation_error_not_a_500(): void
    {
        $tour = $this->editTour('13:18');

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['departure_time' => 745])
            ->call('save')
            ->assertHasFormErrors(['departure_time']);

        $this->assertSame('13:18', $tour->refresh()->departure_time);
    }

    /** @test */
    public function a_tour_can_be_created_with_a_departure_time(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));

        Livewire::test(CreateTour::class)
            ->fillForm(['title_es' => 'Tour con hora', 'slug' => 'tour-con-hora', 'price' => 100, 'departure_time' => '06:30'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('06:30', Tour::where('title_es', 'Tour con hora')->firstOrFail()->departure_time);
    }
}
