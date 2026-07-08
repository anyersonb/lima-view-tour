<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Orden manual EXCLUSIVO de la sección "Más Comprados" del home.
            // NULL = el tour se ordena solo por número real de compras.
            $table->unsignedSmallInteger('featured_order')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('featured_order');
        });
    }
};
