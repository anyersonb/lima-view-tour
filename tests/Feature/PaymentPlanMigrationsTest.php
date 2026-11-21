<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migraciones ADITIVAS del checkout por modos de pago (2026-11-21): up() y
 * down() funcionales, backfill de reservas históricas pagadas y valores por
 * defecto que no alteran los datos existentes.
 */
class PaymentPlanMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private const BOOKING_COLUMNS = [
        'payment_plan', 'amount_paid', 'balance_due', 'balance_due_at', 'balance_paid_at',
        'balance_payment_method', 'balance_payment_reference', 'balance_reminder_sent_at',
    ];

    private function load(string $file): Migration
    {
        $path = database_path('migrations/'.$file);
        $this->assertFileExists($path);

        return require $path;
    }

    public function test_bookings_migration_down_removes_every_column_and_up_restores_them(): void
    {
        $m = $this->load('2026_11_21_100000_add_payment_plan_columns_to_bookings_table.php');

        foreach (self::BOOKING_COLUMNS as $col) {
            $this->assertTrue(Schema::hasColumn('bookings', $col), "falta {$col} tras migrate");
        }

        $m->down();

        foreach (self::BOOKING_COLUMNS as $col) {
            $this->assertFalse(Schema::hasColumn('bookings', $col), "down() no quitó {$col}");
        }
        // Lo preexistente sigue intacto.
        $this->assertTrue(Schema::hasColumn('bookings', 'payment_status'));
        $this->assertTrue(Schema::hasColumn('bookings', 'payment_reminder_sent_at'));

        $m->up();

        foreach (self::BOOKING_COLUMNS as $col) {
            $this->assertTrue(Schema::hasColumn('bookings', $col), "up() no recreó {$col}");
        }
    }

    public function test_backfill_sets_amount_paid_only_on_already_paid_historical_bookings(): void
    {
        $m = $this->load('2026_11_21_100000_add_payment_plan_columns_to_bookings_table.php');
        $m->down(); // estado de producción ANTES del lote

        $base = [
            'tour_title_snapshot' => 'City Tour', 'customer_name' => 'X', 'customer_email' => 'x@example.com',
            'customer_phone' => '999999999', 'travel_date' => '2026-12-20', 'adults' => 1, 'children' => 0,
            'unit_price' => 80, 'total_price' => 80, 'currency' => 'USD', 'status' => 'confirmed',
            'locale' => 'es', 'created_at' => now(), 'updated_at' => now(),
        ];
        $paid = DB::table('bookings')->insertGetId($base + ['reference' => 'LVT-OLDPAID1', 'payment_status' => 'paid']);
        $pending = DB::table('bookings')->insertGetId($base + ['reference' => 'LVT-OLDPEND1', 'payment_status' => 'pending']);

        $m->up();

        $this->assertEquals(80.00, (float) DB::table('bookings')->where('id', $paid)->value('amount_paid'));
        $this->assertEquals(0.00, (float) DB::table('bookings')->where('id', $paid)->value('balance_due'));
        $this->assertEquals(0.00, (float) DB::table('bookings')->where('id', $pending)->value('amount_paid'));
        // Las históricas quedan sin plan (null = anterior al checkout por modos).
        $this->assertNull(DB::table('bookings')->where('id', $paid)->value('payment_plan'));
        // Y su estado no cambió.
        $this->assertSame('paid', DB::table('bookings')->where('id', $paid)->value('payment_status'));
    }

    public function test_payment_links_migration_down_and_up_and_default_purpose(): void
    {
        $m = $this->load('2026_11_21_100100_add_purpose_and_culqi_to_payment_links_table.php');

        $this->assertTrue(Schema::hasColumn('payment_links', 'purpose'));
        $this->assertTrue(Schema::hasColumn('payment_links', 'culqi_charge_id'));

        $m->down();

        $this->assertFalse(Schema::hasColumn('payment_links', 'purpose'));
        $this->assertFalse(Schema::hasColumn('payment_links', 'culqi_charge_id'));
        $this->assertTrue(Schema::hasColumn('payment_links', 'booking_id'));

        // Un link existente antes del lote…
        $id = DB::table('payment_links')->insertGetId([
            'code' => str_repeat('a', 40), 'amount' => 50, 'adults' => 1, 'children' => 0,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $m->up();

        // …queda como 'standalone' (comportamiento de siempre).
        $this->assertSame('standalone', DB::table('payment_links')->where('id', $id)->value('purpose'));
    }
}
