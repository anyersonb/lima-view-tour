<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tour switches for the two listing-card promises. NOT NULL default true
 * + explicit backfill: every existing tour keeps showing both (what the site
 * showed before), and an admin turns off the ones that do not apply.
 * Guarded so re-running from Maintenance is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            if (! Schema::hasColumn('tours', 'pickup_included')) {
                $table->boolean('pickup_included')->default(true);
            }
            if (! Schema::hasColumn('tours', 'free_cancellation')) {
                $table->boolean('free_cancellation')->default(true);
            }
        });

        DB::table('tours')->update(['pickup_included' => true, 'free_cancellation' => true]);
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['pickup_included', 'free_cancellation']);
        });
    }
};
