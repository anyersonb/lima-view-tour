<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The manual tours.rating is internal now: 0 / empty must not block saving (e.g. marking "Más vendido"). */
class TourRatingOptionalFormTest extends TestCase
{
    use RefreshDatabase;

    private function tour(): Tour
    {
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));

        return Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'rating' => 0]);
    }

    /** @dataProvider emptyRatings */
    public function test_saving_with_no_rating_and_most_sold_on_works($rating): void
    {
        $tour = $this->tour();

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['rating' => $rating, 'show_most_sold' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $tour->refresh();
        $this->assertTrue($tour->show_most_sold);
        $this->assertSame(0.0, (float) $tour->rating);
    }

    public static function emptyRatings(): array
    {
        return ['zero' => [0], 'null' => [null], 'zero string' => ['0.0']];
    }

    public function test_out_of_range_rating_is_still_rejected(): void
    {
        $tour = $this->tour();

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->fillForm(['rating' => 6])
            ->call('save')
            ->assertHasFormErrors(['rating']);
    }
}
