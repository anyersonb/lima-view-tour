<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Marca cuándo se envió el recordatorio de pago (reservas "pagar luego").
            // NULL = aún no se ha recordado. Evita reenvíos duplicados.
            $table->timestamp('payment_reminder_sent_at')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('payment_reminder_sent_at');
        });
    }
};
