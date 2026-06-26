<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add pickup_point and pickup_detail columns to bookings.
     * Both are nullable so existing rows are unaffected.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Zone/district selected by the customer (e.g. "Miraflores")
            $table->string('pickup_point')->nullable()->after('locale');
            // Free-text hotel name or address
            $table->string('pickup_detail')->nullable()->after('pickup_point');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['pickup_point', 'pickup_detail']);
        });
    }
};
