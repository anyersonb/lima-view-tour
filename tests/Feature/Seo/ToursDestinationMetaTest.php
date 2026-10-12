<?php

namespace Tests\Feature\Seo;

use App\Models\Region;
use App\Models\Setting;
use App\Support\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Own title/meta description per destination (Lima, Ica, Cusco) and for /tours
 * in es/en/pt, shipped as lang defaults. An admin Setting still wins.
 */
class ToursDestinationMetaTest extends TestCase
{
    use RefreshDatabase;

    private function metaOf(string $html): array
    {
        preg_match('#<title>(.*?)</title>#si', $html, $t);
        preg_match('#<meta name="description" content="(.*?)"#si', $html, $d);

        return [
            trim(html_entity_decode($t[1] ?? '', ENT_QUOTES)),
            trim(html_entity_decode($d[1] ?? '', ENT_QUOTES)),
        ];
    }

    private function regions(): void
    {
        foreach (['lima', 'ica', 'cusco'] as $slug) {
            Region::create(['slug' => $slug, 'name_es' => ucfirst($slug), 'name_en' => ucfirst($slug), 'is_active' => true]);
        }
    }

    public function test_every_destination_and_locale_has_its_own_title_and_description_within_limits(): void
    {
        $seen = [];

        foreach (['es', 'en', 'pt'] as $locale) {
            app()->setLocale($locale);

            foreach ([null, 'lima', 'ica', 'cusco'] as $cat) {
                $key = PageSeo::toursKey($cat);
                $title = PageSeo::title($key, PageSeo::langDefault($key, 'title') ?? 'FALLBACK');
                $desc = PageSeo::description($key, PageSeo::langDefault($key, 'description') ?? 'FALLBACK');

                $this->assertNotSame('FALLBACK', $title, "$locale/$key title");
                $this->assertNotSame('FALLBACK', $desc, "$locale/$key description");
                $this->assertLessThanOrEqual(60, mb_strlen($title), "$locale/$key title length: $title");
                $this->assertLessThanOrEqual(155, mb_strlen($desc), "$locale/$key description length: $desc");
                $this->assertNotSame('', PageSeo::toursHeading($cat));
                $this->assertStringNotContainsString('seo.tours_h1_', PageSeo::toursHeading($cat));

                $seen[] = "$locale|$title";
                $seen[] = "$locale|$desc";
            }
        }

        $this->assertSame(count($seen), count(array_unique($seen)), 'no duplicated title/description across destinations');
    }

    public function test_heading_keys_by_destination(): void
    {
        app()->setLocale('en');
        $this->assertSame('Tours in Lima', PageSeo::toursHeading('lima'));
        $this->assertSame('All our tours in Peru', PageSeo::toursHeading(null));
        app()->setLocale('pt');
        $this->assertSame('Tours em Cusco', PageSeo::toursHeading('cusco'));
        app()->setLocale('es');
        $this->assertSame('Tours en Ica', PageSeo::toursHeading('ica'));
    }
}
