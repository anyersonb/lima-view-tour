<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The cookie banner can no longer be switched off from the CMS (it is always on
 * in the layout). Normalises any legacy row to 1 so data matches behaviour.
 * Tolerant: no table / no row = no-op. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'cookie_banner_enabled')->update(['value' => '1']);
        }

        Cache::forget('settings.all');
        Cache::forget('settings.all_view');
    }

    public function down(): void
    {
        // Irreversible on purpose: the previous value (possibly 0) must not be restored.
    }
};
