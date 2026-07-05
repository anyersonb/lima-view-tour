<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->json('faqs_es')->nullable()->after('notes_es');
            $table->json('faqs_en')->nullable()->after('notes_en');
            $table->json('faqs_pt')->nullable()->after('notes_pt');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['faqs_es', 'faqs_en', 'faqs_pt']);
        });
    }
};
