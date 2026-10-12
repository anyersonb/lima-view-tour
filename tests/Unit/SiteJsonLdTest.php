<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\SiteJsonLd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteJsonLdTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_uses_the_data_of_the_settings_and_the_locale_home(): void
    {
        Setting::set('contact_phone', '+51 925 886 725');
        Setting::set('contact_email', 'info@limaviewtours.com');
        Setting::set('social_instagram', 'https://instagram.com/limaviewtours');

        $graph = SiteJsonLd::graph('en')['@graph'];

        $this->assertSame('TravelAgency', $graph[0]['@type'][0]);
        $this->assertSame('+51925886725', $graph[0]['telephone']);
        $this->assertSame('info@limaviewtours.com', $graph[0]['email']);
        $this->assertSame(['https://instagram.com/limaviewtours'], $graph[0]['sameAs']);
        $this->assertSame(url('/en'), $graph[0]['url']);
        $this->assertSame('WebSite', $graph[1]['@type']);
        $this->assertSame('en-US', $graph[1]['inLanguage']);
    }

    public function test_empty_settings_leave_the_keys_out_instead_of_inventing_values(): void
    {
        $org = SiteJsonLd::graph('es')['@graph'][0];

        $this->assertArrayNotHasKey('telephone', $org);
        $this->assertArrayNotHasKey('email', $org);
        $this->assertArrayNotHasKey('sameAs', $org);
    }

    public function test_json_is_valid(): void
    {
        $decoded = json_decode(SiteJsonLd::json('pt'), true);

        $this->assertSame('https://schema.org', $decoded['@context']);
        $this->assertSame('pt-BR', $decoded['@graph'][1]['inLanguage']);
    }
}
