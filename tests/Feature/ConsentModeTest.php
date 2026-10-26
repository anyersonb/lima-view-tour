<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\LocalizedPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Measurement without a cookie banner (owner decision, 2026-10-26).
 *
 * The cookie banner was removed for every visitor. The contract guarded here:
 *   - Consent Mode v2 starts GRANTED for the four signals, without wait_for_update,
 *     and is printed BEFORE the first gtag config / GTM / Pixel;
 *   - GTM and the Meta Pixel load unconditionally (no consent guard), gated only
 *     by the ID existing in Settings;
 *   - there is no banner, no footer "cookie preferences" button, and no leftover
 *     lvtConsent / lvt-consent-granted plumbing;
 *   - window.lvtTrack and the delegated phone/email/maps/whatsapp events stay;
 *   - the privacy policy names the tools in use.
 */
class ConsentModeTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNALS = ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage'];

    private function html(string $locale = 'es', bool $withIds = true): string
    {
        if ($withIds) {
            Setting::set('seo_gtm_id', 'GTM-TEST123');
            Setting::set('seo_google_analytics_id', 'G-TEST123456');
            Setting::set('seo_facebook_pixel', '123456789012345');
        }

        return $this->get('/'.$locale)->assertOk()->getContent();
    }

    public function test_consent_default_is_granted_for_all_signals_before_any_tag(): void
    {
        $html = $this->html();

        $default = strpos($html, "gtag('consent', 'default'");
        $config = strpos($html, "gtag('config'");
        $gtmSrc = strpos($html, 'googletagmanager.com/gtm.js');
        $gtagJs = strpos($html, 'googletagmanager.com/gtag/js');
        $fbq = strpos($html, "fbq('init'");

        foreach ([$default, $config, $gtmSrc, $gtagJs, $fbq] as $pos) {
            $this->assertNotFalse($pos, 'tag missing (test would be vacuous)');
        }
        foreach ([$config, $gtmSrc, $gtagJs, $fbq] as $pos) {
            $this->assertLessThan($pos, $default, 'consent default must come first');
        }

        foreach (self::SIGNALS as $key) {
            $this->assertMatchesRegularExpression("/gtag\\('consent', 'default', \\{[^}]*{$key}:\\s+'granted'/s", $html);
            $this->assertDoesNotMatchRegularExpression("/{$key}:\\s+'denied'/", $html);
        }
        $this->assertStringNotContainsString('wait_for_update', $html);
        $this->assertStringNotContainsString("'consent', 'update'", $html);
    }

    public function test_gtm_and_pixel_load_without_any_consent_guard(): void
    {
        $html = $this->html();

        $this->assertStringContainsString("googletagmanager.com/gtm.js?id='+i", $html);
        $this->assertStringContainsString("'dataLayer','GTM-TEST123'", $html);
        $this->assertStringContainsString("fbq('track', 'PageView')", $html);

        foreach (['lvtConsent', 'lvtLoadGtm', 'lvtLoadFbPixel', 'lvt-consent-granted', 'lvt_consent', 'lvt_cookie_consent'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $html, "leftover consent plumbing: {$legacy}");
        }
    }

    public function test_tags_are_still_gated_by_their_setting_id(): void
    {
        $html = $this->html('es', false);

        $this->assertStringContainsString("gtag('consent', 'default'", $html);
        $this->assertStringNotContainsString('googletagmanager.com/gtm.js', $html);
        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertStringNotContainsString("fbq('init'", $html);
    }

    /**
     * @dataProvider locales
     */
    public function test_no_banner_and_no_footer_button_in_every_public_locale(string $locale, string $prefs, string $accept): void
    {
        $html = $this->html($locale);

        $this->assertStringNotContainsString('id="lvt-cookie-banner"', $html);
        $this->assertStringNotContainsString('data-cookie-preferences', $html);
        $this->assertStringNotContainsString('lvt-cookie-preferences', $html);
        $this->assertStringNotContainsString('@click="accept()"', $html);
        $this->assertStringNotContainsString($prefs, $html);
        $this->assertStringNotContainsString('>'.$accept.'<', $html);
    }

    public static function locales(): array
    {
        return [
            'es' => ['es', 'Preferencias de cookies', 'Aceptar'],
            'en' => ['en', 'Cookie preferences', 'Accept'],
            'pt' => ['pt', 'Preferências de cookies', 'Aceitar'],
        ];
    }

    public function test_lvt_track_and_delegated_click_events_are_present(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('window.lvtTrack = function', $html);
        foreach (['phone_click', 'email_click', 'maps_click', "lvtTrack('whatsapp_click'"] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    /**
     * @dataProvider privacyLocales
     */
    public function test_privacy_policy_names_the_measurement_tools(string $locale): void
    {
        $html = $this->get(LocalizedPages::url('legal.privacy', $locale))->assertOk()->getContent();

        foreach (['Google Analytics', 'Google Tag Manager', 'Microsoft Clarity', 'Hotjar', 'Meta Pixel'] as $tool) {
            $this->assertStringContainsString($tool, $html);
        }
    }

    public static function privacyLocales(): array
    {
        return ['es' => ['es'], 'en' => ['en'], 'pt' => ['pt']];
    }
}
