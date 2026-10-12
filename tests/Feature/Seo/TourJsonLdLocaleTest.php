<?php

namespace Tests\Feature\Seo;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** S-07: the tour JSON-LD must describe the page language, not always Spanish. */
class TourJsonLdLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function esSchema(): string
    {
        // Written by hand with "www" while the app host is the apex (or the reverse):
        // the rewrite must not depend on that.
        $base = str_replace('://', '://www.', rtrim(url('/'), '/'));

        return json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'TouristTrip',
                    'name' => 'Laguna Humantay',
                    'description' => 'Excursión de día completo a la laguna.',
                    'url' => $base.'/es/tours/detalle/laguna-humantay',
                    'provider' => ['@type' => 'TravelAgency', 'name' => 'Lima View Tours', 'url' => $base.'/es'],
                    'offers' => ['@type' => 'Offer', 'price' => '50', 'priceCurrency' => 'USD'],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => $base.'/es'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tours', 'item' => $base.'/es/tours'],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => 'Laguna Humantay', 'item' => $base.'/es/tours/detalle/laguna-humantay'],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function tour(array $o = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'slug' => 'laguna-humantay',
            'slug_en' => 'humantay-lake-tour',
            'slug_pt' => null,
            'title_es' => 'Laguna Humantay',
            'title_en' => 'Humantay Lake',
            'title_pt' => null,
            'meta_description_en' => 'Full-day trip to Humantay Lake.',
            'schema_jsonld_es' => $this->esSchema(),
            'is_published' => true,
        ], $o));
    }

    /** @return array<int, array> */
    private function blocks(string $path): array
    {
        $html = $this->get($path)->assertOk()->getContent();
        preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si', $html, $m);

        return array_map(fn ($b) => json_decode($b, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    private function graph(array $block, string $type): array
    {
        foreach ($block['@graph'] as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }
        $this->fail("no {$type} node");
    }

    public function test_english_page_gets_english_name_description_and_its_own_url(): void
    {
        $this->tour();

        $blocks = $this->blocks('/en/tours/detalle/humantay-lake-tour');

        $this->assertCount(1, $blocks);
        $trip = $this->graph($blocks[0], 'TouristTrip');
        $this->assertSame('Humantay Lake', $trip['name']);
        $this->assertSame('Full-day trip to Humantay Lake.', $trip['description']);
        $this->assertSame(url('/en/tours/detalle/humantay-lake-tour'), $trip['url']);
        $this->assertSame(url('/en'), $trip['provider']['url']);
        $this->assertSame('50', $trip['offers']['price']);

        $items = $this->graph($blocks[0], 'BreadcrumbList')['itemListElement'];
        $this->assertSame(url('/en'), $items[0]['item']);
        $this->assertSame(url('/en/tours'), $items[1]['item']);
        $this->assertSame(url('/en/tours/detalle/humantay-lake-tour'), $items[2]['item']);
        $this->assertSame('Humantay Lake', $items[2]['name']);
        $this->assertNotSame('Inicio', $items[0]['name']);
    }

    public function test_url_equals_the_canonical_of_the_page(): void
    {
        $this->tour();
        $path = '/en/tours/detalle/humantay-lake-tour';

        $html = $this->get($path)->getContent();
        preg_match('#<link rel="canonical" href="([^"]+)"#', $html, $c);
        $trip = $this->graph($this->blocks($path)[0], 'TouristTrip');

        $this->assertNotEmpty($c);
        $this->assertSame($c[1], $trip['url']);
    }

    public function test_untranslated_locale_keeps_spanish_text_but_its_own_url(): void
    {
        $this->tour();

        // PT has no title/description/slug: clean fallback to Spanish text, URL of the /pt page.
        $trip = $this->graph($this->blocks('/pt/tours/detalle/laguna-humantay')[0], 'TouristTrip');

        $this->assertSame('Laguna Humantay', $trip['name']);
        $this->assertSame('Excursión de día completo a la laguna.', $trip['description']);
        $this->assertSame(url('/pt/tours/detalle/laguna-humantay'), $trip['url']);
    }

    public function test_spanish_page_is_printed_untouched(): void
    {
        $tour = $this->tour();

        $this->assertEquals(json_decode($tour->schema_jsonld_es, true), $this->blocks('/es/tours/detalle/laguna-humantay')[0]);
    }

    public function test_a_schema_written_for_the_locale_is_printed_as_written(): void
    {
        $own = json_encode(['@context' => 'https://schema.org', '@type' => 'TouristTrip', 'name' => 'Hand written EN', 'url' => 'https://example.test/x']);
        $this->tour(['schema_jsonld_en' => $own]);

        $blocks = $this->blocks('/en/tours/detalle/humantay-lake-tour');

        $this->assertCount(1, $blocks);
        $this->assertSame('Hand written EN', $blocks[0]['name']);
        $this->assertSame('https://example.test/x', $blocks[0]['url']);
    }

    public function test_no_schema_at_all_prints_no_block(): void
    {
        $this->tour(['schema_jsonld_es' => null]);

        $this->assertCount(0, $this->blocks('/en/tours/detalle/humantay-lake-tour'));
    }

    public function test_pt_translation_is_used_when_loaded(): void
    {
        $this->tour(['slug_pt' => 'lagoa-humantay', 'title_pt' => 'Lagoa Humantay', 'meta_description_pt' => 'Passeio de dia inteiro.']);

        $trip = $this->graph($this->blocks('/pt/tours/detalle/lagoa-humantay')[0], 'TouristTrip');

        $this->assertSame('Lagoa Humantay', $trip['name']);
        $this->assertSame('Passeio de dia inteiro.', $trip['description']);
        $this->assertSame(url('/pt/tours/detalle/lagoa-humantay'), $trip['url']);
    }
}
