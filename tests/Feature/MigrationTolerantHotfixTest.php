<?php

namespace Tests\Feature;

use App\Filament\Pages\Maintenance;
use App\Models\Tour;
use App\Models\User;
use App\Services\BookingCreationService;
use App\Services\CartService;
use App\Support\TourFlags;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Production migrates from a button and may run the new code BEFORE the
 * 2026_10_11 migrations. The site must work both ways, and the button / the
 * backup must tell the truth.
 */
class MigrationTolerantHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TourFlags::flush();
    }

    protected function tearDown(): void
    {
        TourFlags::flush();
        parent::tearDown();
    }

    private function dropFlagColumns(): void
    {
        Schema::table('tours', fn ($t) => $t->dropColumn(['pickup_included', 'free_cancellation']));
        TourFlags::flush();
        $this->assertFalse(Schema::hasColumn('tours', 'pickup_included'));
        $this->assertFalse(Schema::hasColumn('tours', 'free_cancellation'));
    }

    private function migration(): object
    {
        return require base_path('database/migrations/2026_10_11_000001_normalize_tours_departure_time.php');
    }

    // (a) tours table WITHOUT the two columns

    public function test_cart_listing_and_detail_respond_200_without_the_columns(): void
    {
        $tour = Tour::factory()->create(['is_published' => true, 'price' => 100]);
        $this->dropFlagColumns();
        $tour = Tour::findOrFail($tour->id);

        app(CartService::class)->add($tour, 2, 0, now()->addDays(10)->format('Y-m-d'));

        // Cart/checkout form: both promises default to on => pickup is asked.
        $html = $this->get(route('cart.index', ['locale' => 'es']))->assertOk()->getContent();
        $this->assertStringContainsString('id="pickup_point"', $html);

        $this->get(route('tours.index', ['locale' => 'es']))->assertOk();
        $this->get(route('tours.show', ['locale' => 'es', 'slug' => $tour->slug]))->assertOk()
            ->assertSee('Recojo incluido')
            ->assertSee('Cancelación gratuita');
    }

    public function test_booking_creation_works_without_the_columns(): void
    {
        Mail::fake();
        $tour = Tour::factory()->create(['is_published' => true, 'price' => 100]);
        $this->dropFlagColumns();

        $bookings = app(BookingCreationService::class)->createBookings(
            collect([[
                'tour_id' => $tour->id, 'title_snapshot' => 'T', 'travel_date' => now()->addDays(10)->format('Y-m-d'),
                'adults' => 1, 'children' => 0, 'unit_price' => 100, 'subtotal' => 100,
            ]]),
            ['customer_name' => 'Juan', 'customer_email' => 'j@example.com', 'customer_phone' => '1', 'pickup_point' => 'Hotel X'],
            'pay_later', null, false, 'es',
        );

        $this->assertCount(1, $bookings);
        $this->assertSame('Hotel X', $bookings->first()->pickup_point);
    }

    public function test_flags_helper_reflects_schema(): void
    {
        $this->assertSame(['pickup_included', 'free_cancellation'], TourFlags::columns());
        $this->dropFlagColumns();
        $this->assertSame([], TourFlags::columns());
    }

    // (c) Maintenance: migrate failing => danger + log

    private const GENERIC = 'No se pudo completar la acción. El detalle completo quedó en el registro del servidor (storage/logs).';

    /** Maintenance is restricted to config('admin.maintenance_emails'). */
    private function maintenanceUser(): User
    {
        config(['admin.maintenance_emails' => ['qa@webtilia.com']]);

        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_maintenance_run_migrations_reports_failure_when_exit_code_is_not_zero(): void
    {
        $this->actingAs($this->maintenanceUser());
        Log::spy();
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(1);
        Artisan::shouldReceive('output')->andReturn('SQLSTATE boom');

        Livewire::test(Maintenance::class)
            ->callAction('run_migrations')
            ->assertNotified(
                Notification::make()->title('Las migraciones fallaron')->body(self::GENERIC.' Resumen: SQLSTATE boom')->danger()->persistent()
            );

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($m, $ctx) => $ctx['exit'] === 1 && $ctx['output'] === 'SQLSTATE boom')
            ->once();
    }

    public function test_maintenance_run_migrations_reports_exception_as_danger(): void
    {
        $this->actingAs($this->maintenanceUser());
        Log::spy();
        Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('disk gone'));

        Livewire::test(Maintenance::class)
            ->callAction('run_migrations')
            ->assertNotified(
                Notification::make()->title('Las migraciones fallaron')->body(self::GENERIC.' Resumen: disk gone')->danger()->persistent()
            );

        Log::shouldHaveReceived('error')->once();
    }

    public function test_maintenance_run_migrations_success_is_green(): void
    {
        $this->actingAs($this->maintenanceUser());
        Artisan::shouldReceive('call')->once()->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('Nothing to migrate.');

        Livewire::test(Maintenance::class)
            ->callAction('run_migrations')
            ->assertNotified(
                Notification::make()->title('Migraciones ejecutadas')->body('Nothing to migrate.')->success()
            );
    }

    // (d) migration 000001 with a failing disk

    public function test_departure_time_migration_logs_backup_and_normalizes_when_disk_fails(): void
    {
        $tour = Tour::factory()->create();
        DB::table('tours')->where('id', $tour->id)->update(['departure_time' => '5 am']);

        $disk = Mockery::mock();
        $disk->shouldReceive('makeDirectory')->andReturn(false);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        Log::spy();

        $this->migration()->up();

        $this->assertSame('05:00', DB::table('tours')->where('id', $tour->id)->value('departure_time'));
        Log::shouldHaveReceived('critical')
            ->withArgs(fn ($m) => str_contains($m, 'restore from this log') && str_contains($m, '5 am') && str_contains($m, '05:00'))
            ->once();
    }

    public function test_departure_time_migration_aborts_without_touching_data_if_log_also_fails(): void
    {
        $tour = Tour::factory()->create();
        DB::table('tours')->where('id', $tour->id)->update(['departure_time' => '5 am']);

        $disk = Mockery::mock();
        $disk->shouldReceive('makeDirectory')->andReturn(false);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('critical')->andThrow(new RuntimeException('log dead'));

        try {
            $this->migration()->up();
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('migration aborted', $e->getMessage());
        }

        $this->assertSame('5 am', DB::table('tours')->where('id', $tour->id)->value('departure_time'));
    }

    public function test_departure_time_summary_is_error_level_only_when_tours_end_up_null(): void
    {
        Storage::fake('local');
        Log::spy();
        $bad = Tour::factory()->create();
        DB::table('tours')->where('id', $bad->id)->update(['departure_time' => 'Todo el día']);

        $this->migration()->up();

        Log::shouldHaveReceived('log')
            ->withArgs(fn ($level, $m, $ctx) => $level === 'error' && $ctx['null_tour_ids'] === [$bad->id])
            ->once();

        Log::spy();
        $ok = Tour::factory()->create();
        DB::table('tours')->where('id', $ok->id)->update(['departure_time' => '5 am']);
        DB::table('tours')->where('id', $bad->id)->update(['departure_time' => '07:00']);

        $this->migration()->up();

        Log::shouldHaveReceived('log')->withArgs(fn ($level) => $level === 'info')->once();
    }

    public function test_departure_time_migration_writes_to_disk_when_it_works(): void
    {
        Storage::fake('local');
        $tour = Tour::factory()->create();
        DB::table('tours')->where('id', $tour->id)->update(['departure_time' => '5 am']);

        $this->migration()->up();

        Storage::disk('local')->assertExists('migrations/tours_departure_time_backup.json');
        $this->assertSame('05:00', DB::table('tours')->where('id', $tour->id)->value('departure_time'));
    }
}
