<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Checkout nuevo (2026-11-21): modos de pago full | deposit | later.
     *
     * SOLO aditiva: ninguna columna existente cambia. `payment_status` es un
     * string (no ENUM), así que el valor nuevo 'partial' (adelanto cobrado,
     * saldo pendiente) no requiere ALTER.
     *
     *   payment_plan  full | deposit | later (null = reservas históricas)
     *   amount_paid   dinero realmente cobrado hasta hoy (adelanto + saldo)
     *   balance_due   saldo pendiente (0 si no hay)
     *   balance_due_at  límite para cobrar el saldo (= vence el link: tour - 48 h)
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_plan', 10)->nullable()->after('payment_status');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('payment_plan');
            $table->decimal('balance_due', 10, 2)->default(0)->after('amount_paid');
            $table->timestamp('balance_due_at')->nullable()->after('balance_due');
            $table->timestamp('balance_paid_at')->nullable()->after('balance_due_at');
            $table->string('balance_payment_method', 30)->nullable()->after('balance_paid_at');
            $table->string('balance_payment_reference')->nullable()->after('balance_payment_method');
            $table->timestamp('balance_reminder_sent_at')->nullable()->after('balance_payment_reference');

            // Recordatorio de saldo: "partial AND balance_reminder_sent_at IS NULL AND travel_date <= X".
            $table->index(['payment_status', 'balance_reminder_sent_at'], 'bookings_payment_status_balance_reminder_index');
        });

        // Backfill de reservas históricas ya pagadas: lo cobrado es el total.
        // No toca payment_plan (null = "anterior al checkout por modos").
        DB::table('bookings')
            ->where('payment_status', 'paid')
            ->update(['amount_paid' => DB::raw('total_price')]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_payment_status_balance_reminder_index');
            $table->dropColumn([
                'payment_plan',
                'amount_paid',
                'balance_due',
                'balance_due_at',
                'balance_paid_at',
                'balance_payment_method',
                'balance_payment_reference',
                'balance_reminder_sent_at',
            ]);
        });
    }
};
