<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idioma fijado por el admin para el cliente de este link (es/en/pt).
     * NULL = automático por Accept-Language del navegador (comportamiento
     * previo, así los links existentes no cambian).
     */
    public function up(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('travel_date');
        });
    }

    public function down(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
