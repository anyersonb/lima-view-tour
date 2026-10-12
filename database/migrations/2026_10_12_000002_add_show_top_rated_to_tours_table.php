<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tour "Mejor valorado" badge switch. Default false: nothing changes on
 * the site until an admin turns it on for a tour. Guarded so re-running from
 * Maintenance is safe (production migrates from a button).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tours', 'show_top_rated')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->boolean('show_top_rated')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tours', 'show_top_rated')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->dropColumn('show_top_rated');
            });
        }
    }
};
