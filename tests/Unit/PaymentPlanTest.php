<?php

namespace Tests\Unit;

use App\Support\PaymentPlan;
use PHPUnit\Framework\TestCase;

/** Cálculo de modos de pago (100% / 50% / después): centavos enteros, suma exacta. */
class PaymentPlanTest extends TestCase
{
    public function test_full_charges_the_whole_total(): void
    {
        $p = PaymentPlan::split(90.00, PaymentPlan::FULL);

        $this->assertSame(9000, $p['due_cents']);
        $this->assertSame(0, $p['balance_cents']);
        $this->assertSame(90.0, $p['due']);
    }

    public function test_deposit_charges_exactly_half(): void
    {
        $p = PaymentPlan::split(90.00, PaymentPlan::DEPOSIT);

        $this->assertSame(4500, $p['due_cents']);
        $this->assertSame(4500, $p['balance_cents']);
        $this->assertSame(45.0, $p['balance']);
    }

    public function test_deposit_odd_cent_rounds_up_and_always_adds_up(): void
    {
        // 35.01 => 3501 centavos: la mitad se redondea hacia arriba (el cliente
        // nunca paga menos del 50%) y ahora + saldo == total.
        $p = PaymentPlan::split(35.01, PaymentPlan::DEPOSIT);

        $this->assertSame(1751, $p['due_cents']);
        $this->assertSame(1750, $p['balance_cents']);
        $this->assertSame($p['total_cents'], $p['due_cents'] + $p['balance_cents']);
    }

    public function test_later_charges_nothing_and_leaves_everything_as_balance(): void
    {
        $p = PaymentPlan::split(120.50, PaymentPlan::LATER);

        $this->assertSame(0, $p['due_cents']);
        $this->assertSame(12050, $p['balance_cents']);
    }

    public function test_float_noise_does_not_leak_into_cents(): void
    {
        // 0.1 + 0.2 style noise: 33.3 * 3 = 99.89999999999999 en float.
        $p = PaymentPlan::split(33.3 * 3, PaymentPlan::FULL);

        $this->assertSame(9990, $p['due_cents']);
    }

    public function test_amounts_returns_the_three_modes(): void
    {
        $a = PaymentPlan::amounts(60);

        $this->assertSame(['full', 'deposit', 'later'], array_keys($a));
        $this->assertSame(6000, $a['full']['due_cents']);
        $this->assertSame(3000, $a['deposit']['due_cents']);
        $this->assertSame(0, $a['later']['due_cents']);
    }

    public function test_normalize_only_accepts_the_three_modes(): void
    {
        $this->assertSame('deposit', PaymentPlan::normalize(' Deposit '));
        $this->assertNull(PaymentPlan::normalize('half'));
        $this->assertNull(PaymentPlan::normalize(['full']));
        $this->assertNull(PaymentPlan::normalize(null));
    }

    public function test_allocate_splits_paid_amount_across_bookings_exactly(): void
    {
        // 3 tours de valor distinto, total 100.01, adelanto 50.01 (impar).
        $items = [['subtotal' => 33.34], ['subtotal' => 33.34], ['subtotal' => 33.33]];
        $split = PaymentPlan::split(100.01, PaymentPlan::DEPOSIT);

        $rows = PaymentPlan::allocate($items, $split['total_cents'], $split['due_cents']);

        $this->assertCount(3, $rows);
        $this->assertSame($split['total_cents'], array_sum(array_column($rows, 'net_cents')));
        $this->assertSame($split['due_cents'], array_sum(array_column($rows, 'paid_cents')));
        $this->assertSame($split['balance_cents'], array_sum(array_column($rows, 'balance_cents')));

        foreach ($rows as $r) {
            $this->assertGreaterThanOrEqual(0, $r['balance_cents']);
            $this->assertLessThanOrEqual($r['net_cents'], $r['paid_cents']);
        }
    }

    public function test_allocate_with_a_coupon_scales_net_amounts(): void
    {
        // Subtotal 100 con cupón => total 90; adelanto 45.
        $items = [['subtotal' => 60.0], ['subtotal' => 40.0]];
        $split = PaymentPlan::split(90.0, PaymentPlan::DEPOSIT);

        $rows = PaymentPlan::allocate($items, $split['total_cents'], $split['due_cents']);

        $this->assertSame([5400, 3600], array_column($rows, 'net_cents'));
        $this->assertSame([2700, 1800], array_column($rows, 'paid_cents'));
    }

    public function test_allocate_later_pays_nothing(): void
    {
        $rows = PaymentPlan::allocate([['subtotal' => 50.0]], 5000, 0);

        $this->assertSame(0, $rows[0]['paid_cents']);
        $this->assertSame(5000, $rows[0]['balance_cents']);
    }
}
