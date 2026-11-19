<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro de Reclamaciones virtual (INDECOPI, D.S. 011-2011-PCM y modificatorias).
 * Registro legal: el panel no permite borrar filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            // Correlativo R-AAAA-000001: se asigna justo después de crear la fila.
            $table->string('code', 20)->nullable()->unique();
            $table->string('locale', 5)->default('es');

            // Consumidor
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('doc_type', 12);          // dni | ce | passport
            $table->string('doc_number', 20);
            $table->string('address', 255);
            $table->string('phone', 30);
            $table->string('email', 200);
            $table->boolean('is_minor')->default(false);
            $table->string('guardian_name', 255)->nullable();

            // Bien contratado
            $table->string('good_type', 10);         // product | service
            $table->string('booking_reference', 30)->nullable();
            $table->decimal('claimed_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('PEN');
            $table->text('good_description');

            // Reclamo / queja
            $table->string('type', 10);              // claim | complaint
            $table->text('detail');
            $table->text('consumer_request');
            $table->boolean('accepted_declaration')->default(false);

            // Respuesta del proveedor
            $table->string('status', 15)->default('pending'); // pending | in_progress | answered
            $table->date('response_due_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('type');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
