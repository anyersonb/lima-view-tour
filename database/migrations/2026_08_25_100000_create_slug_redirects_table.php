<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de slugs (2026-08-25).
 *
 * El slug español de los tours estaba BLINDADO (disabled en edición) desde el
 * 2026-07-02 justo por esto: renombrarlo dejaba en 404 la URL ya indexada y
 * compartida. La prueba está en routes/web.php, donde hay un 301 escrito a
 * mano para el único tour que sí se renombró antes del blindaje.
 *
 * Al abrir el campo (pedido de 2026-08-25, "editable tipo WordPress") hace
 * falta el mismo mecanismo que usa WordPress con wp_old_slug_redirect: cada
 * vez que cambia el slug efectivo de un contenido en un idioma, el anterior
 * queda registrado acá y la ficha responde a esa URL con un 301 al slug
 * vigente. Así el editor cambia la URL sin romper nada.
 *
 * Sin `new_slug` a propósito: la resolución va old_slug → registro → slug
 * ACTUAL del registro. Un solo salto siempre, incluso tras varios renombres
 * seguidos, y nunca queda un destino obsoleto guardado en la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('redirectable_type');
            $table->unsignedBigInteger('redirectable_id');
            $table->string('locale', 5);
            // 191 y no 255: los slugs más largos en producción rondan los 95
            // caracteres y 191 es el máximo indexable con utf8mb4 en MySQL 5.7.
            $table->string('old_slug', 191);
            $table->timestamps();

            $table->unique(
                ['redirectable_type', 'redirectable_id', 'locale', 'old_slug'],
                'slug_redirects_unique_old_slug'
            );
            $table->index(
                ['redirectable_type', 'locale', 'old_slug'],
                'slug_redirects_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
