<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes to support the admin Bookings filters at ~20k+ rows scale.
 * Each index is justified by a specific filter/tab query — see
 * app/Filament/Resources/BookingResource.php for where it is used.
 *
 * NOT indexed (deliberately, to keep write cost down):
 * - tour_id: already indexed by the `constrained()` foreign key.
 * - customer_id: already indexed (see 2026_06_29_000003_...).
 * - status alone: already covered by the original (status, travel_date) index.
 * - reference: already unique-indexed.
 * - customer_phone: rarely the leading search term and a LIKE '%x%' search
 *   can't use a plain index anyway; not worth the write cost at this volume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // "Estado de pago" filter + tabs (Pagadas/Pendientes/Fallidas) +
            // "Pago en riesgo" (payment_status = pending AND travel_date <= X).
            $table->index(['payment_status', 'travel_date'], 'bookings_payment_status_travel_date_index');

            // Pure travel_date range queries with no payment_status predicate
            // (tabs "Salidas de hoy" / "Salidas de esta semana", filtro de
            // fecha de viaje solo). The composite above can't serve this
            // efficiently because payment_status is its leftmost column.
            $table->index('travel_date', 'bookings_travel_date_index');

            // "Fecha de la reserva" range filter (created_at desde/hasta) and
            // the default sort by created_at.
            $table->index('created_at', 'bookings_created_at_index');

            // Exact/prefix lookup by customer email (support search box).
            $table->index('customer_email', 'bookings_customer_email_index');

            // "Sin recordatorio enviado" filter: payment_status = pending AND
            // payment_reminder_sent_at IS NULL.
            $table->index(['payment_status', 'payment_reminder_sent_at'], 'bookings_payment_status_reminder_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_payment_status_travel_date_index');
            $table->dropIndex('bookings_travel_date_index');
            $table->dropIndex('bookings_created_at_index');
            $table->dropIndex('bookings_customer_email_index');
            $table->dropIndex('bookings_payment_status_reminder_index');
        });
    }
};
