<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_dates', function (Blueprint $table) {
            $table->id();

            // Fecha específica (nullable — se usa cuando weekday es null)
            $table->date('date')->nullable()->index();

            // Día de la semana recurrente: 0=domingo … 6=sábado (nullable — se usa cuando date es null)
            $table->unsignedTinyInteger('weekday')->nullable()->index();

            // null = bloqueo global para todos los tours
            $table->foreignId('tour_id')
                  ->nullable()
                  ->constrained('tours')
                  ->nullOnDelete();

            $table->string('reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_dates');
    }
};
