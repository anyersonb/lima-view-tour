<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Safety net for site measurement: lists, in business language, what would
 * leave the site without analytics. The cookie banner cannot be switched off
 * (always on), so the remaining silent failure is a missing tracking ID.
 */
class AnalyticsHealth
{
    /**
     * @return list<string> Problems in Spanish; empty array = measuring OK.
     */
    public static function check(): array
    {
        $problems = [];

        if (self::isBlank('seo_gtm_id')) {
            $problems[] = 'Falta el ID de Google Tag Manager (seo_gtm_id): Hotjar, Clarity y Google Ads no se cargan en el sitio.';
        }

        if (self::isBlank('seo_google_analytics_id')) {
            $problems[] = 'Falta el ID de Google Analytics 4 (seo_google_analytics_id): no se registran visitas en Analytics.';
        }

        return $problems;
    }

    private static function isBlank(string $key): bool
    {
        try {
            $value = Setting::get($key);
        } catch (Throwable) {
            // Settings table missing / DB down: cannot confirm measurement.
            return true;
        }

        return ! is_string($value) || trim($value) === '';
    }
}
