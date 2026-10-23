<?php

namespace Tests\Feature\Seo;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PT with no description/itinerary of its own renders the Spanish body (Tour::localized()).
 * That page must be noindex, absent from hreflang and from the sitemap, and the rule must switch
 * itself off as soon as PT gets content. The rule is scoped to PT: untranslated EN stays
 * indexable and in the sitemap.
 */
class TourUntranslatedLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'slug' => 'nazca-es',
            'slug_en' => 'nazca-en',
            'slug_pt' => 'nazca-pt',
            'description_es' => 'Descripcion en espanol',
            'description_en' => 'English description',
            'description_pt' => null,
            'itinerary_pt' => null,
            'is_published' => true,
        ], $overrides));
    }

    private function robots(string $html): string
    {
        preg_match('#<meta name="robots" content="([^"]+)"#', $html, $m);

        return $m[1] ?? '';
    }

    public function test_untranslated_pt_is_noindex_and_outside_hreflang(): void
    {
        $this->tour();

        $pt = $this->get('/pt/tours/detalle/nazca-pt')->assertOk()->getContent();
        $this->assertStringStartsWith('noindex', $this->robots($pt));
        $this->assertStringNotContainsString('rel="alternate" hreflang="pt"', $pt);
        $this->assertStringContainsString('rel="alternate" hreflang="es"', $pt);
        $this->assertStringContainsString('rel="alternate" hreflang="en"', $pt);

        foreach (['/es/tours/detalle/nazca-es', '/en/tours/detalle/nazca-en'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringStartsWith('index', $this->robots($html), $path);
            $this->assertStringNotContainsString('rel="alternate" hreflang="pt"', $html, $path);
        }
    }

    public function test_sitemap_omits_the_untranslated_locale_only_for_that_tour(): void
    {
        $this->tour();
        $this->tour([
            'slug' => 'otro-es', 'slug_en' => 'otro-en', 'slug_pt' => 'otro-pt',
            'description_pt' => 'Descricao em portugues',
        ]);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/es/tours/detalle/nazca-es</loc>', $xml);
        $this->assertStringContainsString('/en/tours/detalle/nazca-en</loc>', $xml);
        $this->assertStringNotContainsString('/pt/tours/detalle/nazca-pt', $xml);
        $this->assertStringContainsString('/pt/tours/detalle/otro-pt</loc>', $xml);
    }

    public function test_rule_switches_itself_off_once_pt_is_translated(): void
    {
        $tour = $this->tour();
        $tour->update(['itinerary_pt' => [['time' => '4:00 AM', 'title' => 'Saida', 'description' => 'Do hotel']]]);

        $pt = $this->get('/pt/tours/detalle/nazca-pt')->assertOk()->getContent();
        $this->assertStringStartsWith('index', $this->robots($pt));
        $this->assertStringContainsString('rel="alternate" hreflang="pt"', $pt);
        $this->assertStringContainsString('/pt/tours/detalle/nazca-pt</loc>', $this->get('/sitemap.xml')->getContent());
    }

    public function test_untranslated_en_stays_indexable_and_in_the_sitemap(): void
    {
        $this->tour([
            'slug' => 'catacumbas-es', 'slug_en' => 'catacombs-en', 'slug_pt' => 'catacumbas-pt',
            'description_en' => null, 'itinerary_en' => null,
            'description_pt' => 'Descricao em portugues',
        ]);

        $en = $this->get('/en/tours/detalle/catacombs-en')->assertOk()->getContent();
        $this->assertStringStartsWith('index', $this->robots($en));
        $this->assertStringContainsString('rel="alternate" hreflang="en"', $en);

        $es = $this->get('/es/tours/detalle/catacumbas-es')->assertOk()->getContent();
        $this->assertStringContainsString('rel="alternate" hreflang="en"', $es);

        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('/en/tours/detalle/catacombs-en</loc>', $xml);
    }

    public function test_has_translation_treats_blank_content_as_missing(): void
    {
        $tour = new Tour(['description_pt' => " \u{00A0}\n", 'itinerary_pt' => []]);

        $this->assertTrue($tour->hasTranslation('es'));
        $this->assertFalse($tour->hasTranslation('pt'));
    }
}
