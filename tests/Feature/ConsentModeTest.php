<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consent Mode v2 + GTM gated by consent (2026-10-06).
 *
 * Production loaded GTM (and, through it, Clarity and Hotjar) with no consent
 * condition at all. The contract guarded here:
 *   - consent default = denied is printed BEFORE the first gtag config / GTM;
 *   - the GTM snippet only lives inside a function (lvtLoadGtm) that runs
 *     after consent, never as a bare top-level script, and there is no
 *     <noscript> iframe loading the container;
 *   - the banner renders Accept and Reject with the same style, and the
 *     footer exposes the "cookie preferences" re-open control;
 *   - phone/email/maps click events ride the same delegated listener.
 */
class ConsentModeTest extends TestCase
{
    use RefreshDatabase;

    private function html(string $locale = 'es'): string
    {
        Setting::set('seo_gtm_id', 'GTM-TEST123');
        Setting::set('seo_google_analytics_id', 'G-TEST123456');
        Setting::set('cookie_banner_enabled', true, 'boolean');

        $response = $this->get('/'.$locale);
        $response->assertOk();

        return $response->getContent();
    }

    public function test_consent_default_denied_comes_before_any_gtag_config_or_gtm(): void
    {
        $html = $this->html();

        $default = strpos($html, "gtag('consent', 'default'");
        $config = strpos($html, "gtag('config'");
        $gtmSrc = strpos($html, 'googletagmanager.com/gtm.js');
        $gtagJs = strpos($html, 'googletagmanager.com/gtag/js');

        $this->assertNotFalse($default, 'consent default missing');
        $this->assertNotFalse($config, 'gtag config missing (test would be vacuous)');
        $this->assertNotFalse($gtmSrc, 'GTM snippet missing (test would be vacuous)');
        $this->assertLessThan($config, $default);
        $this->assertLessThan($gtmSrc, $default);
        $this->assertLessThan($gtagJs, $default);

        foreach (['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage'] as $key) {
            $this->assertMatchesRegularExpression("/gtag\\('consent', 'default', \\{[^}]*{$key}:\\s+'denied'/s", $html);
        }
        $this->assertStringContainsString('wait_for_update:    500', $html);

        // Stored "granted" preference is applied via update BEFORE config.
        $update = strpos($html, "gtag('consent', 'update', window.lvtConsent.granted)");
        $this->assertNotFalse($update);
        $this->assertLessThan($config, $update);
    }

    public function test_gtm_snippet_is_never_executed_unconditionally_when_banner_is_on(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('ns.html?id=', $html, 'GTM noscript iframe must not exist');

        // The loader is wrapped in a function and only invoked under consent.
        $this->assertStringContainsString('window.lvtLoadGtm = function', $html);
        $this->assertStringContainsString("if (window.lvtConsent.get() === 'granted') window.lvtLoadGtm();", $html);
        $this->assertStringContainsString("addEventListener('lvt-consent-granted', window.lvtLoadGtm)", $html);

        // No bare call: the only invocations of lvtLoadGtm are the two guarded ones.
        $this->assertSame(2, substr_count($html, 'window.lvtLoadGtm()') + substr_count($html, 'window.lvtLoadGtm)'));
        // The snippet text sits inside the function body (after its declaration).
        $this->assertGreaterThan(
            strpos($html, 'window.lvtLoadGtm = function'),
            strpos($html, 'googletagmanager.com/gtm.js')
        );
    }

    public function test_with_banner_disabled_gtm_loads_directly_as_before(): void
    {
        Setting::set('seo_gtm_id', 'GTM-TEST123');
        Setting::set('cookie_banner_enabled', false, 'boolean');

        $html = $this->get('/es')->assertOk()->getContent();

        $this->assertStringContainsString("window.lvtLoadGtm();\n", $html);
        $this->assertStringNotContainsString("gtag('consent', 'default'", $html);
        $this->assertStringNotContainsString('<button type="button" data-cookie-preferences', $html);
    }

    /**
     * @dataProvider locales
     */
    public function test_banner_and_footer_control_render_in_every_public_locale(string $locale, string $accept, string $reject, string $prefs): void
    {
        $html = $this->html($locale);

        $this->assertStringContainsString('<button type="button" data-cookie-preferences', $html);
        $this->assertStringContainsString($prefs, $html);
        $this->assertStringContainsString($accept, $html);
        $this->assertStringContainsString($reject, $html);
        $this->assertStringContainsString('@click="accept()"', $html);
        $this->assertStringContainsString('@click="reject()"', $html);
    }

    public static function locales(): array
    {
        return [
            'es' => ['es', 'Aceptar', 'Rechazar', 'Preferencias de cookies'],
            'en' => ['en', 'Accept', 'Reject', 'Cookie preferences'],
            'pt' => ['pt', 'Aceitar', 'Recusar', 'Preferências de cookies'],
        ];
    }

    public function test_accept_and_reject_buttons_share_the_same_style(): void
    {
        $html = $this->html();

        preg_match_all('/<button\s+type="button"\s+@click="(accept|reject)\(\)"\s+style="([^"]*)"/s', $html, $m);
        $this->assertSame(['reject', 'accept'], $m[1]);
        $this->assertSame(
            preg_replace('/\s+/', ' ', $m[2][0]),
            preg_replace('/\s+/', ' ', $m[2][1]),
            'Accept and Reject must have identical visual weight'
        );
    }

    public function test_phone_email_maps_events_use_the_delegated_listener(): void
    {
        $html = $this->html();

        foreach (['phone_click', 'email_click', 'maps_click', "lvtTrack('whatsapp_click'"] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }
}
