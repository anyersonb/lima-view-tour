<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links de pago del SALDO (2026-11-21). Solo aditiva.
     *
     *   purpose          standalone (link suelto: crea reserva al pagar) |
     *                    balance (cobra el saldo de una reserva existente,
     *                    ligada por booking_id; NUNCA crea reserva)
     *   culqi_charge_id  id del cargo cuando el saldo se paga con tarjeta (Culqi)
     */
    public function up(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
            $table->string('purpose', 12)->default('standalone')->after('code');
            $table->string('culqi_charge_id')->nullable()->after('paypal_capture_id');

            $table->index(['purpose', 'booking_id'], 'payment_links_purpose_booking_index');
            $table->index('culqi_charge_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
            $table->dropIndex('payment_links_purpose_booking_index');
            $table->dropIndex(['culqi_charge_id']);
            $table->dropColumn(['purpose', 'culqi_charge_id']);
        });
    }
};
