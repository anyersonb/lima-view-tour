<?php

namespace Tests\Feature\Seo;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lote 2026-10-12 (S-01/S-02/S-03/S-09): the sitemap must list the REAL
 * per-locale tour URL (no 301 targets), its hreflang must match the <head>
 * of the page, its images must live under /storage/, and the listing cards
 * must link to the per-locale slug.
 */
class SitemapTourUrlsTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'slug' => 'laguna-humantay',
            'slug_en' => 'humantay-lake-tour',
            'slug_pt' => 'lagoa-humantay',
            'cover_image' => 'tours/155.jpg',
            'is_published' => true,
            // Own EN/PT bodies: untranslated locales are left out of hreflang and the sitemap.
            'description_en' => 'English description',
            'description_pt' => 'Descricao em portugues',
        ], $overrides));
    }

    public function test_sitemap_uses_the_slug_of_each_locale_and_never_a_301_target(): void
    {
        $this->tour();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/es/tours/detalle/laguna-humantay</loc>', $xml);
        $this->assertStringContainsString('/en/tours/detalle/humantay-lake-tour</loc>', $xml);
        $this->assertStringContainsString('/pt/tours/detalle/lagoa-humantay</loc>', $xml);
        // The Spanish slug under /en or /pt is a 301 and must not appear anywhere.
        $this->assertStringNotContainsString('/en/tours/detalle/laguna-humantay', $xml);
        $this->assertStringNotContainsString('/pt/tours/detalle/laguna-humantay', $xml);
    }

    public function test_every_tour_loc_in_the_sitemap_answers_200_without_redirect(): void
    {
        $this->tour();
        $this->tour(['slug' => 'city-tour-cusco', 'slug_en' => 'cusco-city-tour', 'slug_pt' => null, 'cover_image' => null]);

        $xml = $this->get('/sitemap.xml')->getContent();
        preg_match_all('#<loc>([^<]*/tours/detalle/[^<]+)</loc>#', $xml, $m);

        $this->assertCount(6, $m[1]);
        foreach ($m[1] as $loc) {
            $this->get(parse_url($loc, PHP_URL_PATH))->assertOk();
        }
    }

    public function test_sitemap_hreflang_matches_the_head_of_the_page(): void
    {
        $this->tour();

        $xml = $this->get('/sitemap.xml')->getContent();
        $page = $this->get('/en/tours/detalle/humantay-lake-tour')->getContent();

        foreach (['es', 'en', 'pt'] as $l) {
            preg_match('#<link rel="alternate" hreflang="'.$l.'"\s+href="([^"]+)"#', $page, $head);
            $this->assertNotEmpty($head, "no hreflang {$l} in head");
            $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="'.$l.'" href="'.$head[1].'"/>', $xml);
        }
    }

    public function test_sitemap_images_go_through_storage(): void
    {
        $this->tour();

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('<image:loc>'.url('/storage/tours/155.jpg').'</image:loc>', $xml);
        $this->assertDoesNotMatchRegularExpression('#<image:loc>[^<]*?/tours/155\.jpg#', str_replace('/storage/tours/155.jpg', '', $xml));
    }

    public function test_tour_without_cover_has_no_image_node(): void
    {
        $this->tour(['cover_image' => null]);

        $this->assertStringNotContainsString('<image:image>', $this->get('/sitemap.xml')->getContent());
    }

    public function test_listing_and_home_cards_link_to_the_slug_of_the_page_locale(): void
    {
        $this->tour(['is_featured' => true, 'title_es' => 'Tour a Humantay', 'title_en' => 'a Humantay Lake']);

        foreach (['/en/tours', '/en', '/en/buscar?q=a'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('/en/tours/detalle/humantay-lake-tour', $html, $path);
            $this->assertStringNotContainsString('/en/tours/detalle/laguna-humantay', $html, $path);
        }

        $pt = $this->get('/pt/tours')->getContent();
        $this->assertStringContainsString('/pt/tours/detalle/lagoa-humantay', $pt);
        $this->assertStringNotContainsString('/pt/tours/detalle/laguna-humantay', $pt);
    }
}
