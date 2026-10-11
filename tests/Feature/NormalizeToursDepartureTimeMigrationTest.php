<?php

namespace Tests\Feature;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NormalizeToursDepartureTimeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_11_000001_normalize_tours_departure_time.php');
    }

    /** Insert below the model mutator, like the legacy data in production. */
    private function legacyTour(string $raw): int
    {
        $id = Tour::factory()->create()->id;
        DB::table('tours')->where('id', $id)->update(['departure_time' => $raw]);

        return $id;
    }

    public function test_up_normalizes_varied_formats_nulls_the_unreadable_and_down_restores_them(): void
    {
        Storage::fake('local');

        $ids = [
            'am' => $this->legacyTour('05:00 AM'),
            'lower' => $this->legacyTour('5:00 am'),
            'plain' => $this->legacyTour('05:00'),
            'short' => $this->legacyTour('5 AM'),
            'pm' => $this->legacyTour('2:30 PM'),
            'range' => $this->legacyTour('05:00 - 06:00'),
            'text' => $this->legacyTour('Todo el día'),
        ];
        $none = Tour::factory()->create(['departure_time' => null])->id;

        ob_start();
        $this->migration()->up();
        ob_end_clean();

        $value = fn (int $id) => DB::table('tours')->where('id', $id)->value('departure_time');

        $this->assertSame('05:00', $value($ids['am']));
        $this->assertSame('05:00', $value($ids['lower']));
        $this->assertSame('05:00', $value($ids['plain']));
        $this->assertSame('05:00', $value($ids['short']));
        $this->assertSame('14:30', $value($ids['pm']));
        $this->assertNull($value($ids['range']));
        $this->assertNull($value($ids['text']));
        $this->assertNull($value($none));

        // Idempotent: a second run changes nothing and keeps the first backup.
        ob_start();
        $this->migration()->up();
        ob_end_clean();
        $this->assertSame('05:00', $value($ids['am']));

        $this->migration()->down();

        $this->assertSame('05:00 AM', $value($ids['am']));
        $this->assertSame('Todo el día', $value($ids['text']));
        $this->assertSame('05:00 - 06:00', $value($ids['range']));
        // Untouched values stay as they were.
        $this->assertSame('05:00', $value($ids['plain']));
    }

    public function test_up_on_an_empty_table_is_silent_and_safe(): void
    {
        Storage::fake('local');

        ob_start();
        $this->migration()->up();
        $out = ob_get_clean();

        $this->assertSame('', $out);
    }
}
