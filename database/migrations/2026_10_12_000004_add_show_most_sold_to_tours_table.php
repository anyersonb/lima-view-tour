<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Más vendido" badge of the new card: opt-in per tour (default false, client
 * decision: not marked = not shown). Separate from show_best_seller, which
 * defaults to true and still drives the "most booked" seal of the tour page.
 * Nothing existing is touched, so there is nothing to back up. Guarded so
 * re-running from Maintenance is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tours', 'show_most_sold')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->boolean('show_most_sold')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tours', 'show_most_sold')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->dropColumn('show_most_sold');
            });
        }
    }
};
