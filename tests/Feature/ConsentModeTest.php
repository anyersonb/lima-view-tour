<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\LocalizedPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Medicion sin banner (decision del dueno 2026-10-26, confirmada 2026-11-18).
 *
 * Contrato que protege este archivo:
 *   - NO hay banner de cookies ni boton "preferencias de cookies";
 *   - consent default = granted se emite ANTES de cualquier gtag('config'),
 *     GTM o Pixel;
 *   - GA4 / GTM / Meta Pixel cargan siempre que su ID exista en Settings y
 *     no se emiten si el ID esta vacio;
 *   - el valor legado cookie_banner_enabled no cambia nada;
 *   - la politica de privacidad declara las herramientas (GA, GTM, Clarity,
 *     Hotjar, Meta);
 *   - los eventos phone/email/maps siguen en el listener delegado.
 */
class ConsentModeTest extends TestCase
{
    use RefreshDatabase;

    private function html(string $locale = 'es', array $overrides = []): string
    {
        $values = array_merge([
            'seo_gtm_id' => 'GTM-TEST123',
            'seo_google_analytics_id' => 'G-TEST123456',
            'seo_facebook_pixel' => '123456789012345',
        ], $overrides);

        foreach ($values as $key => $value) {
            Setting::set($key, $value);
        }

        $response = $this->get('/'.$locale);
        $response->assertOk();

        return $response->getContent();
    }

    public function test_consent_default_granted_comes_before_any_gtag_config_gtm_or_pixel(): void
    {
        $html = $this->html();

        $default = strpos($html, "gtag('consent', 'default'");
        $config = strpos($html, "gtag('config'");
        $gtmSrc = strpos($html, 'googletagmanager.com/gtm.js');
        $gtagJs = strpos($html, 'googletagmanager.com/gtag/js');
        $pixel = strpos($html, 'connect.facebook.net');

        $this->assertNotFalse($default, 'consent default missing');
        $this->assertNotFalse($config, 'gtag config missing (test would be vacuous)');
        $this->assertNotFalse($gtmSrc, 'GTM snippet missing (test would be vacuous)');
        $this->assertNotFalse($pixel, 'Pixel snippet missing (test would be vacuous)');
        $this->assertLessThan($config, $default);
        $this->assertLessThan($gtmSrc, $default);
        $this->assertLessThan($gtagJs, $default);
        $this->assertLessThan($pixel, $default);

        foreach (['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage'] as $key) {
            $this->assertMatchesRegularExpression("/gtag\\('consent', 'default', \\{[^}]*{$key}:\\s+'granted'/s", $html);
        }
        $this->assertStringNotContainsString("'denied'", $html, 'no consent key may default to denied');
    }

    public function test_gtm_ga4_and_pixel_load_when_ids_exist(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('googletagmanager.com/gtm.js?id=', $html);
        $this->assertStringContainsString("'GTM-TEST123'", $html);
        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-TEST123456', $html);
        $this->assertStringContainsString("gtag('config', 'G-TEST123456'", $html);
        $this->assertStringContainsString("fbq('init', '123456789012345')", $html);
        $this->assertStringContainsString("fbq('track', 'PageView')", $html);

        // Sin consent gating: no quedan loaders condicionados.
        $this->assertStringNotContainsString('lvtLoadGtm', $html);
        $this->assertStringNotContainsString('lvtLoadFbPixel', $html);
        $this->assertStringNotContainsString('lvtConsent', $html);
    }

    public function test_nothing_is_emitted_when_ids_are_empty(): void
    {
        $html = $this->html('es', [
            'seo_gtm_id' => '',
            'seo_google_analytics_id' => '',
            'seo_facebook_pixel' => '',
        ]);

        $this->assertStringNotContainsString('googletagmanager.com/gtm.js', $html);
        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertStringNotContainsString("gtag('config'", $html);
        $this->assertStringNotContainsString('connect.facebook.net', $html);
        $this->assertStringNotContainsString("fbq('init'", $html);
    }

    public function test_each_tool_depends_only_on_its_own_id(): void
    {
        $onlyGtm = $this->html('es', [
            'seo_google_analytics_id' => '',
            'seo_facebook_pixel' => '',
        ]);

        $this->assertStringContainsString('googletagmanager.com/gtm.js', $onlyGtm);
        $this->assertStringNotContainsString("gtag('config'", $onlyGtm);
        $this->assertStringNotContainsString('connect.facebook.net', $onlyGtm);
    }

    public function test_no_noscript_iframes_for_gtm_or_pixel(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('ns.html?id=', $html, 'GTM noscript iframe must not exist');
        $this->assertStringNotContainsString('facebook.com/tr?id=', $html, 'Pixel noscript img must not exist');
    }

    /**
     * El valor legado cookie_banner_enabled (0 o 1) ya no tiene efecto.
     *
     * @dataProvider legacyValues
     */
    public function test_legacy_cookie_banner_setting_has_no_effect(bool $legacy): void
    {
        Setting::set('cookie_banner_enabled', $legacy, 'boolean');

        $html = $this->html();

        $this->assertStringContainsString("gtag('consent', 'default'", $html);
        $this->assertStringContainsString('googletagmanager.com/gtm.js', $html);
        $this->assertStringNotContainsString('data-cookie-preferences', $html);
        $this->assertStringNotContainsString('lvt-cookie-banner', $html);
    }

    public static function legacyValues(): array
    {
        return ['legacy 0' => [false], 'legacy 1' => [true]];
    }

    /**
     * @dataProvider locales
     */
    public function test_no_cookie_banner_nor_preferences_button_in_any_public_locale(string $locale, string $accept, string $reject, string $prefs): void
    {
        $html = $this->html($locale);

        $this->assertStringNotContainsString('data-cookie-preferences', $html);
        $this->assertStringNotContainsString('lvt-cookie-banner', $html);
        $this->assertStringNotContainsString($prefs, $html);
        $this->assertStringNotContainsString('@click="accept()"', $html);
        $this->assertStringNotContainsString('@click="reject()"', $html);
        $this->assertStringNotContainsString('lvt_consent', $html);
    }

    public static function locales(): array
    {
        return [
            'es' => ['es', 'Aceptar', 'Rechazar', 'Preferencias de cookies'],
            'en' => ['en', 'Accept', 'Reject', 'Cookie preferences'],
            'pt' => ['pt', 'Aceitar', 'Recusar', 'Preferências de cookies'],
        ];
    }

    /**
     * @dataProvider locales
     */
    public function test_privacy_policy_declares_the_measurement_tools(string $locale): void
    {
        $slug = LocalizedPages::slugFor('legal.privacy', $locale);
        $html = $this->get('/'.$locale.'/'.$slug)->assertOk()->getContent();

        foreach (['Google Analytics', 'Google Tag Manager', 'Clarity', 'Hotjar', 'Meta Pixel'] as $tool) {
            $this->assertStringContainsString($tool, $html, "privacy ($locale) must mention $tool");
        }
    }

    public function test_phone_email_maps_events_use_the_delegated_listener(): void
    {
        $html = $this->html();

        foreach (['phone_click', 'email_click', 'maps_click', "lvtTrack('whatsapp_click'"] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }
}
