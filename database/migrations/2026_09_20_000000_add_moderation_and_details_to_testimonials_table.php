<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('title_es')->nullable()->after('name');
            $table->string('title_en')->nullable()->after('title_es');
            $table->string('title_pt')->nullable()->after('title_en');
            $table->string('traveler_type')->nullable()->after('rating');
            $table->json('photos')->nullable()->after('avatar');
            $table->unsignedInteger('helpful_count')->default(0)->after('photos');
            // Default 'pending': el formulario público (TourController::storeReview,
            // ReviewController::store) ya inserta con is_active=false, pero sin un
            // status distinguible no se puede separar "sin revisar" de "ocultada a
            // propósito" en el panel. Cualquier INSERT que no especifique status
            // (futuros seeders, imports) cae del lado seguro: pendiente de moderar.
            $table->string('status')->default('pending')->after('is_active');
            $table->date('review_date')->nullable()->after('status');
            // Nunca se expone al front — solo para contactar al autor de la reseña.
            $table->string('submitter_email')->nullable()->after('review_date');
            $table->string('locale', 5)->nullable()->after('submitter_email');

            $table->index(['tour_id', 'status', 'is_active'], 'testimonials_tour_status_active_index');
        });

        // Backfill explícito, SOLO para lo que ya estaba visible en el sitio
        // antes de esta migración (`is_active=true`): eso sí se puede
        // reconstruir con seguridad como "approved" (ya pasó por un admin,
        // aunque fuera implícitamente). El default de la columna ('pending')
        // ya deja el resto en el estado correcto sin tocar nada más:
        //
        // Hallazgo de seguridad #6 (2026-09-30): la versión anterior de este
        // UPDATE no tenía WHERE, así que también aprobaba las reseñas que
        // NUNCA se habían moderado (`is_active=false`, en cola de
        // "Pendiente"). Seguían ocultas por is_active, pero un admin que las
        // viera como "Aprobado" y activara el interruptor "Visible" publicaba
        // sin haberlas revisado. No hay forma de reconstruir, a partir de
        // datos viejos, CUÁLES de esas is_active=false habían sido
        // rechazadas alguna vez vs. simplemente nunca revisadas — por eso se
        // dejan en el default ('pending') en vez de inventar un estado.
        DB::table('testimonials')->where('is_active', true)->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropIndex('testimonials_tour_status_active_index');
            $table->dropColumn([
                'title_es', 'title_en', 'title_pt',
                'traveler_type', 'photos', 'helpful_count',
                'status', 'review_date', 'submitter_email', 'locale',
            ]);
        });
    }
};
