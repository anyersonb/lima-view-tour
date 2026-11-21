<?php

namespace Tests\Feature\Seo;

use App\Models\Tour;
use App\Support\TourJsonLd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El precio del JSON-LD escrito a mano se sincroniza con tours.price al renderizar. */
class TourJsonLdOfferPriceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array> */
    private function blocks(string $path): array
    {
        $html = $this->get($path)->assertOk()->getContent();
        preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si', $html, $m);

        return array_map(fn ($b) => json_decode($b, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    private function offerPrices(string $path): array
    {
        $out = [];
        $walk = function ($n) use (&$walk, &$out) {
            if (! is_array($n)) {
                return;
            }
            if (($n['@type'] ?? null) === 'Offer') {
                $out[] = [$n['price'] ?? null, $n['priceCurrency'] ?? null];
            }
            foreach ($n as $v) {
                $walk($v);
            }
        };
        foreach ($this->blocks($path) as $b) {
            $walk($b);
        }

        return $out;
    }

    public function test_pretty_printed_root_schema_gets_current_price(): void
    {
        $json = "{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"TouristTrip\",\n  \"name\": \"Pachacamac\",\n"
            ."  \"offers\": {\n    \"@type\": \"Offer\",\n    \"price\": \"35\",\n    \"priceCurrency\": \"PEN\",\n    \"availability\": \"https://schema.org/InStock\"\n  }\n}";
        $tour = Tour::factory()->create([
            'slug' => 'pachacamac', 'price' => 30, 'currency' => 'USD',
            'schema_jsonld_es' => $json, 'is_published' => true,
        ]);

        $this->assertSame([['30', 'USD']], $this->offerPrices('/es/tours/detalle/pachacamac'));
        $this->assertSame('35', json_decode($tour->fresh()->schema_jsonld_es, true)['offers']['price'], 'la BD no se muta');
    }

    public function test_graph_schema_and_decimal_price_in_english(): void
    {
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'TouristTrip', 'name' => 'X', 'offers' => ['@type' => 'Offer', 'price' => '35', 'priceCurrency' => 'USD']],
            ['@type' => 'Product', 'name' => 'X', 'offers' => [['@type' => 'Offer', 'price' => '99']]],
            ['@type' => 'BreadcrumbList', 'itemListElement' => []],
        ]]);
        Tour::factory()->create([
            'slug' => 'tour-grafo', 'slug_en' => 'graph-tour', 'price' => 30.50, 'currency' => 'USD',
            'schema_jsonld_es' => $json, 'is_published' => true,
        ]);

        $this->assertSame([['30.5', 'USD'], ['30.5', 'USD']], $this->offerPrices('/en/tours/detalle/graph-tour'));
    }

    public function test_invalid_json_is_left_untouched(): void
    {
        $bad = '{"offers": {"@type":"Offer","price":"35"';
        $tour = Tour::factory()->make(['price' => 30, 'schema_jsonld_es' => $bad]);

        $this->assertSame($bad, TourJsonLd::for($tour, 'es'));
    }

    public function test_format_price(): void
    {
        $this->assertSame('30', TourJsonLd::formatPrice('30.00'));
        $this->assertSame('30.5', TourJsonLd::formatPrice(30.5));
        $this->assertSame('0', TourJsonLd::formatPrice(0));
        $this->assertNull(TourJsonLd::formatPrice(null));
    }
}
