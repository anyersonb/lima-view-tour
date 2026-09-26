<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A-1 (seguridad): separa el prefill del admin (`customer_*`, nunca
     * tocado por el flujo de pago) de los datos REALES de quien pagó
     * (`buyer_*`, escritos SOLO por PaymentLinkController::captureOrder() y
     * el webhook de reconciliación). Antes de este cambio, capturar un pago
     * sobrescribía `customer_*` con los datos del comprador real — en un
     * link `single_use=false` eso dejaba la PII de un comprador precargada
     * para el SIGUIENTE visitante del mismo enlace (ver docs/payment-links/
     * SECURITY.md, hallazgo A-1).
     */
    public function up(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
            $table->string('buyer_name')->nullable()->after('customer_phone');
            $table->string('buyer_email')->nullable()->after('buyer_name');
            $table->string('buyer_phone')->nullable()->after('buyer_email');
        });
    }

    public function down(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
            $table->dropColumn(['buyer_name', 'buyer_email', 'buyer_phone']);
        });
    }
};
