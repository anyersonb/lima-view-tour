<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();          // link público de recuperación
            $table->string('session_id')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Contacto capturado en el checkout (antes de pagar)
            $table->string('email')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('locale', 5)->default('es');

            // Snapshot del carrito
            $table->json('items');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('coupon_code')->nullable();

            // Estado del ciclo de recuperación
            // active | converted | expired
            $table->string('status', 20)->default('active')->index();
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
