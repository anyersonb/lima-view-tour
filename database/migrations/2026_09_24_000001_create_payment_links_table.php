<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();

            // Mismo patrón que tours/bookings/blocked_dates: nullable + nullOnDelete,
            // así borrar un tour nunca revienta un link ya creado (queda huérfano
            // pero legible, igual que una reserva histórica).
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();

            // Aleatorio, ≥32 chars, generado en PaymentLink::booted() con
            // Str::random() (random_bytes bajo el capó) — no es un slug ni un
            // incremental, para que no sea adivinable.
            $table->string('code', 64)->unique();

            $table->decimal('amount', 10, 2);
            $table->unsignedInteger('adults')->default(1);
            $table->unsignedInteger('children')->default(0);
            $table->date('travel_date')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            $table->text('note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('single_use')->default(true);

            // pending|paid|expired|cancelled
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();

            $table->string('paypal_order_id')->nullable();
            $table->string('paypal_capture_id')->nullable();

            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('paypal_order_id');
            $table->index('paypal_capture_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
