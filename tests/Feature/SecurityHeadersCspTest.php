<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the CSP fix (2026-09-22): Microsoft Clarity, Hotjar and the full
 * set of GA4/GTM ingestion domains were being silently blocked in
 * production even though GTM (GTM-NR3GFXNC) loaded the scripts fine —
 * script-src/connect-src just never allowed them through.
 *
 * These tests parse the CSP header into its individual directives and
 * assert each domain lives in the CORRECT directive, so a regression that
 * drops a domain (or puts it under the wrong directive) fails loudly
 * instead of passing on a str_contains() over the whole header.
 */
class SecurityHeadersCspTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string> directive name => space-separated token list
     */
    private function parseCsp(string $header): array
    {
        $directives = [];

        foreach (explode(';', $header) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            [$name, $value] = array_pad(explode(' ', $chunk, 2), 2, '');
            $directives[$name] = $value;
        }

        return $directives;
    }

    public function test_public_page_csp_allows_clarity_hotjar_and_ga4_domains_in_the_right_directives(): void
    {
        $response = $this->get('/es');
        $response->assertOk();

        $csp = $this->parseCsp($response->headers->get('Content-Security-Policy'));

        $this->assertArrayHasKey('script-src', $csp);
        $this->assertArrayHasKey('connect-src', $csp);
        $this->assertArrayHasKey('style-src', $csp);
        $this->assertArrayHasKey('font-src', $csp);
        $this->assertArrayHasKey('frame-src', $csp);

        // script-src
        foreach ([
            'https://*.googletagmanager.com',
            'https://*.clarity.ms',
            'https://c.bing.com',
            'https://*.hotjar.com',
        ] as $domain) {
            $this->assertStringContainsString($domain, $csp['script-src'], "script-src missing {$domain}");
        }

        // connect-src
        foreach ([
            'https://*.google-analytics.com',
            'https://*.analytics.google.com',
            'https://analytics.google.com',
            'https://*.googletagmanager.com',
            'https://stats.g.doubleclick.net',
            'https://*.clarity.ms',
            'https://c.bing.com',
            'https://*.hotjar.com',
            'https://*.hotjar.io',
            'wss://*.hotjar.com',
            'https://connect.facebook.net',
            'https://www.facebook.com',
        ] as $domain) {
            $this->assertStringContainsString($domain, $csp['connect-src'], "connect-src missing {$domain}");
        }

        // Google Ads (AW-16662327751) via GTM/gtag
        foreach (['https://googleads.g.doubleclick.net', 'https://www.googleadservices.com'] as $domain) {
            $this->assertStringContainsString($domain, $csp['script-src'], "script-src missing {$domain}");
        }
        foreach ([
            'https://ad.doubleclick.net',
            'https://googleads.g.doubleclick.net',
            'https://www.googleadservices.com',
        ] as $domain) {
            $this->assertStringContainsString($domain, $csp['connect-src'], "connect-src missing {$domain}");
        }
        $this->assertStringNotContainsString('https://ad.doubleclick.net', $csp['script-src']);
        $this->assertStringNotContainsString('doubleclick.net *', $csp['script-src']);
        $this->assertStringNotContainsString('*.doubleclick.net', $csp['script-src'].' '.$csp['connect-src']);

        // style-src / font-src / frame-src
        $this->assertStringContainsString('https://*.hotjar.com', $csp['style-src']);
        $this->assertStringContainsString('https://*.hotjar.com', $csp['font-src']);
        $this->assertStringContainsString('https://*.hotjar.com', $csp['frame-src']);
        $this->assertStringContainsString('https://www.facebook.com', $csp['frame-src']);

        // img-src already allows https: broadly — must stay untouched.
        $this->assertStringContainsString('https:', $csp['img-src']);
    }

    public function test_admin_panel_csp_does_not_receive_the_public_analytics_extras(): void
    {
        // /admin itself requires auth and its 302 to /admin/login short-circuits
        // BEFORE the panel's ->middleware() stack (SecurityHeaders included), so
        // that redirect carries no CSP header at all. /admin/login is the public,
        // unauthenticated page that actually runs the full panel middleware
        // stack — it's the right place to assert the admin CSP.
        $response = $this->get('/admin/login');
        $response->assertOk();

        $csp = $this->parseCsp($response->headers->get('Content-Security-Policy'));

        $this->assertArrayHasKey('script-src', $csp);
        $this->assertStringNotContainsString('clarity.ms', $csp['script-src']);
        $this->assertStringNotContainsString('hotjar.com', $csp['script-src']);
        $this->assertStringNotContainsString('c.bing.com', $csp['script-src']);

        $this->assertArrayHasKey('connect-src', $csp);
        $this->assertStringNotContainsString('clarity.ms', $csp['connect-src']);
        $this->assertStringNotContainsString('hotjar.com', $csp['connect-src']);
        $this->assertStringNotContainsString('stats.g.doubleclick.net', $csp['connect-src']);
        foreach (['ad.doubleclick.net', 'googleads.g.doubleclick.net', 'googleadservices.com'] as $host) {
            $this->assertStringNotContainsString($host, $csp['script-src']);
            $this->assertStringNotContainsString($host, $csp['connect-src']);
        }
    }

    public function test_public_csp_allows_google_country_domains_in_connect_src_only(): void
    {
        $response = $this->get('/es');
        $response->assertOk();

        $csp = $this->parseCsp($response->headers->get('Content-Security-Policy'));
        $connect = explode(' ', $csp['connect-src']);

        foreach (['com.pe', 'de', 'co.uk', 'fr', 'es', 'com.br', 'com.ar', 'cl', 'com.mx', 'it', 'nl', 'ca', 'com.au'] as $tld) {
            $this->assertContains("https://www.google.{$tld}", $connect, "connect-src missing www.google.{$tld}");
        }

        // No invalid wildcard and no widening of script-src for these hosts.
        $this->assertStringNotContainsString('google.*', $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('https://www.google.com.pe', $csp['script-src']);
    }

    public function test_admin_csp_does_not_allow_google_country_domains(): void
    {
        $response = $this->get('/admin/login');
        $response->assertOk();

        $header = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($header);
        $this->assertStringNotContainsString('https://www.google.com.pe', $header);
        $this->assertStringNotContainsString('https://www.google.de', $header);
        $this->assertStringNotContainsString('https://www.google.co.uk', $header);
    }

    public function test_public_csp_has_no_bare_google_host_from_empty_cctld_entries(): void
    {
        $response = $this->get('/es');
        $response->assertOk();

        $csp = $this->parseCsp($response->headers->get('Content-Security-Policy'));

        foreach (explode(' ', $csp['connect-src']) as $token) {
            $this->assertNotSame('https://www.google.', $token, 'Bare https://www.google. host in connect-src');
            $this->assertDoesNotMatchRegularExpression('#^https://www\.google\.$#', $token);
        }

        foreach (['com.ve', 'co'] as $invalid) {
            $this->assertStringNotContainsString("https://www.google.{$invalid} ", $csp['connect-src'].' ');
        }

        foreach (['co.ve', 'com.ni', 'com.sv', 'hn'] as $tld) {
            $this->assertStringContainsString("https://www.google.{$tld}", $csp['connect-src']);
        }
    }
}
