<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Descuento / oferta especial aplicada manualmente desde el admin
            $table->string('discount_type', 10)->nullable()->after('total_price'); // 'percent' | 'fixed'
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type'); // 10 (=10%) ó 10.00 (=US$10)
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_value'); // monto descontado calculado
            // Detalle para "tour personalizado / ninguno de los anteriores"
            $table->text('custom_tour_details')->nullable()->after('tour_title_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount', 'custom_tour_details']);
        });
    }
};
