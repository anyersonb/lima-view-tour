<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Organization (TravelAgency) + WebSite JSON-LD for the home, built from the
 * same data the page shows (Settings: contact phone/email, social profiles).
 *
 * PREPARED, NOT WIRED (S-12): since Lote B (2026-08-31) every JSON-LD is
 * written by hand in the panel (Configuración → SEO → "Schema global del
 * sitio", read by layouts/app.blade.php) and the site prints nothing on its
 * own. Using this class is a decision to reverse that: whoever inserts it in
 * the home (e.g. <x-schema-raw :json="\App\Support\SiteJsonLd::json($locale)" />)
 * must not do it while the global Setting is filled, or the page would print
 * two Organization blocks.
 *
 * Only facts that exist are emitted: an empty Setting leaves its key out
 * instead of inventing a value.
 */
final class SiteJsonLd
{
    private const IN_LANGUAGE = ['es' => 'es-PE', 'en' => 'en-US', 'pt' => 'pt-BR'];

    /** @return array<string, mixed> */
    public static function graph(?string $locale = null): array
    {
        $locale = in_array($locale, ['es', 'en', 'pt'], true) ? $locale : app()->getLocale();
        $base = rtrim(url('/'), '/');
        $home = $base.'/'.$locale;
        $name = (string) (Setting::get('site_name') ?: 'Lima View Tours');

        $phone = preg_replace('/[^0-9+]/', '', (string) Setting::get('contact_phone'));
        $email = trim((string) Setting::get('contact_email'));

        $sameAs = collect(['social_facebook', 'social_instagram', 'social_tiktok', 'social_youtube', 'social_tripadvisor', 'social_google_reviews'])
            ->map(fn (string $key) => trim((string) Setting::get($key)))
            ->filter(fn (string $url) => str_starts_with($url, 'http'))
            ->values()
            ->all();

        $organization = array_filter([
            '@type' => ['TravelAgency', 'LocalBusiness'],
            '@id' => $base.'/#organization',
            'name' => $name,
            'url' => $home,
            'logo' => asset('assets/logos/logo.png'),
            'telephone' => $phone ?: null,
            'email' => $email ?: null,
            'sameAs' => $sameAs ?: null,
        ], fn ($v) => $v !== null);

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $organization,
                [
                    '@type' => 'WebSite',
                    '@id' => $base.'/#website',
                    'name' => $name,
                    'url' => $home,
                    'inLanguage' => self::IN_LANGUAGE[$locale],
                    'publisher' => ['@id' => $base.'/#organization'],
                ],
            ],
        ];
    }

    public static function json(?string $locale = null): string
    {
        return json_encode(self::graph($locale), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
