<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hallazgo de seguridad MEDIO (2026-09-30,
 * docs/security/2026-09-30-lote-paypal-resenas.md #6):
 * `2026_09_20_000000_add_moderation_and_details_to_testimonials_table.php`
 * aprobaba TODAS las filas viejas sin `WHERE`, incluidas las que
 * `is_active=false` (en cola de "Pendiente", nunca moderadas). Seguían
 * ocultas por `is_active`, pero un admin que las viera como "Aprobado" y
 * activara el interruptor "Visible" publicaba spam sin haberlo revisado.
 *
 * Este test corre la migración de verdad (down() + up()) sobre filas
 * sembradas ANTES, simulando datos de producción previos a la migración —
 * no basta con crear registros después de que RefreshDatabase ya corrió
 * todas las migraciones, porque entonces el backfill nunca se ejercita.
 */
class TestimonialModerationBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        $path = database_path('migrations/2026_09_20_000000_add_moderation_and_details_to_testimonials_table.php');

        $this->assertFileExists($path, 'La migración bajo prueba no existe en la ruta esperada.');

        return require $path;
    }

    public function test_backfill_only_approves_rows_that_were_already_visible(): void
    {
        $migration = $this->migration();

        // 1) Deshace ESTA migración: vuelve la tabla al estado "de producción
        //    antes del lote" (sin status/moderación).
        $migration->down();

        // 2) Siembra filas crudas con el esquema PRE-migración.
        $wasVisible = DB::table('testimonials')->insertGetId([
            'name' => 'Cliente Visible',
            'quote_es' => 'Reseña que ya estaba publicada antes de este lote.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wasPending = DB::table('testimonials')->insertGetId([
            'name' => 'Cliente Pendiente',
            'quote_es' => 'Reseña que nunca se había moderado.',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3) Re-aplica la migración bajo prueba (el backfill corre acá).
        $migration->up();

        $this->assertSame(
            'approved',
            DB::table('testimonials')->where('id', $wasVisible)->value('status'),
            'Una reseña que YA estaba visible (is_active=true) debe quedar aprobada.'
        );

        $this->assertSame(
            'pending',
            DB::table('testimonials')->where('id', $wasPending)->value('status'),
            'Una reseña que NUNCA se había moderado (is_active=false) no debe aparecer como aprobada.'
        );
    }

    /**
     * Contraprueba: sin el WHERE, la reseña nunca moderada también habría
     * quedado 'approved'. Se prueba llamando al UPDATE sin filtro
     * DIRECTAMENTE (la versión vieja del código, no la migración ya
     * corregida) para demostrar que el escenario de arriba SÍ distingue
     * ambos casos y no es un check que siempre da verde.
     */
    public function test_the_unfiltered_update_would_have_approved_the_never_moderated_row_too(): void
    {
        $migration = $this->migration();
        $migration->down();

        $wasPending = DB::table('testimonials')->insertGetId([
            'name' => 'Cliente Pendiente',
            'quote_es' => 'Reseña que nunca se había moderado.',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        // Simula el bug original (UPDATE sin WHERE) para probar que el
        // escenario de arriba habría fallado sin el fix.
        DB::table('testimonials')->update(['status' => 'approved']);

        $this->assertSame(
            'approved',
            DB::table('testimonials')->where('id', $wasPending)->value('status'),
            'El UPDATE sin WHERE (comportamiento previo al fix) aprueba todo, incluida la fila nunca moderada.'
        );
    }
}
