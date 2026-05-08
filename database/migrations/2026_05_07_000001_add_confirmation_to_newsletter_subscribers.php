<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('subscribed_at');
            $table->string('confirmation_token', 64)->nullable()->unique()->after('confirmed_at');
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('confirmation_token');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'confirmation_token', 'unsubscribe_token']);
        });
    }
};
