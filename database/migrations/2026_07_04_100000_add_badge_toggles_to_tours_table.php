<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->boolean('show_best_seller')->default(true)->after('is_featured');
            $table->boolean('show_offer_badge')->default(true)->after('show_best_seller');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['show_best_seller', 'show_offer_badge']);
        });
    }
};
