<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Language in which the customer takes the tour ('es' | 'en' | 'pt').
 *
 * It used to be validated at checkout and thrown away. Nullable, no backfill:
 * past bookings simply have no recorded language (shown as empty). Guarded so
 * running it twice from Maintenance is harmless. The application tolerates the
 * column being absent (see App\Support\TourLanguage::hasColumn()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'tour_language')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('tour_language', 5)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'tour_language')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('tour_language');
        });
    }
};
