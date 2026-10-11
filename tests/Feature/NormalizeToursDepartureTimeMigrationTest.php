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

    public function test_backup_is_written_before_updates_and_failure_aborts_without_touching_data(): void
    {
        Storage::fake('local');
        $id = $this->legacyTour('05:00 AM');

        // Invalid UTF-8 makes json_encode throw => nothing may be updated.
        $bad = $this->legacyTour('5:00 '.chr(0xB1).chr(0x31).' am');

        try {
            $this->migration()->up();
            $this->fail('Migration should abort when the backup cannot be encoded.');
        } catch (\JsonException) {
            // expected
        }

        $this->assertSame('05:00 AM', DB::table('tours')->where('id', $id)->value('departure_time'));
        $this->assertFalse(Storage::disk('local')->exists('migrations/tours_departure_time_backup.json'));
        $this->assertNotNull(DB::table('tours')->where('id', $bad)->value('departure_time'));
    }

    public function test_unwritable_backup_aborts_without_touching_data(): void
    {
        $id = $this->legacyTour('05:00 AM');

        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        try {
            $this->migration()->up();
            $this->fail('Migration should abort when the backup cannot be written.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('05:00 AM', DB::table('tours')->where('id', $id)->value('departure_time'));
    }

    public function test_down_only_restores_rows_that_still_hold_the_migrated_value(): void
    {
        Storage::fake('local');
        $untouched = $this->legacyTour('05:00 AM');
        $edited = $this->legacyTour('5 am');

        $this->migration()->up();

        // An admin sets a new time after the migration.
        DB::table('tours')->where('id', $edited)->update(['departure_time' => '07:30']);

        $this->migration()->down();

        $this->assertSame('05:00 AM', DB::table('tours')->where('id', $untouched)->value('departure_time'));
        $this->assertSame('07:30', DB::table('tours')->where('id', $edited)->value('departure_time'));
    }

    public function test_up_never_echoes_even_when_it_changes_rows(): void
    {
        Storage::fake('local');
        $this->legacyTour('05:00 AM');

        ob_start();
        $this->migration()->up();
        $out = ob_get_clean();

        $this->assertSame('', $out);
    }
}
