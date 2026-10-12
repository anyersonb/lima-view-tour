<?php

namespace Tests\Feature\Seo;

use App\Models\Tour;
use App\Support\TourJsonLd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FAQPage is emitted only from FAQs the page really shows, in the page language. */
class TourFaqPageJsonLdTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $o = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'is_published' => true,
            'faqs_es' => [],
            'faqs_en' => [],
            'faqs_pt' => [],
        ], $o));
    }

    private function blocks(string $html): array
    {
        preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si', $html, $m);

        return array_map(fn ($b) => json_decode(html_entity_decode($b), true) ?? json_decode($b, true), $m[1]);
    }

    public function test_no_faqs_means_no_faqpage_and_no_block(): void
    {
        $tour = $this->tour();

        $this->assertNull(TourJsonLd::faqPage($tour, 'es'));
        $this->assertNull(TourJsonLd::for($tour, 'es'));

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();
        $this->assertStringNotContainsString('FAQPage', $html);
    }

    public function test_incomplete_pairs_are_ignored(): void
    {
        $tour = $this->tour(['faqs_es' => [['question' => 'Solo pregunta', 'answer' => '']]]);

        $this->assertNull(TourJsonLd::faqPage($tour, 'es'));
    }

    public function test_faqpage_matches_the_visible_questions_and_answers(): void
    {
        $tour = $this->tour(['faqs_es' => [
            ['question' => '¿Incluye recojo?', 'answer' => 'Sí, en tu hotel.'],
            ['question' => '¿Hay descuento?', 'answer' => 'Para niños.'],
        ]]);

        $html = $this->get("/es/tours/detalle/{$tour->slug}")->assertOk()->getContent();
        $faq = collect($this->blocks($html))->first(fn ($b) => ($b['@type'] ?? null) === 'FAQPage');

        $this->assertNotNull($faq, 'FAQPage block present');
        $this->assertSame(['¿Incluye recojo?', '¿Hay descuento?'], array_column($faq['mainEntity'], 'name'));
        $this->assertSame('Sí, en tu hotel.', $faq['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertStringContainsString('¿Incluye recojo?', $html, 'the question is visible on the page');
    }

    public function test_locale_without_faqs_falls_back_to_spanish_like_the_page(): void
    {
        $tour = $this->tour(['faqs_es' => [['question' => 'P es', 'answer' => 'R es']], 'faqs_en' => []]);

        $this->assertSame('P es', TourJsonLd::faqPage($tour, 'en')['mainEntity'][0]['name']);

        $tour->update(['faqs_en' => [['question' => 'Q en', 'answer' => 'A en']]]);
        $this->assertSame('Q en', TourJsonLd::faqPage($tour->fresh(), 'en')['mainEntity'][0]['name']);
    }

    public function test_it_joins_the_editors_graph_without_duplicating_a_manual_faqpage(): void
    {
        $manual = json_encode(['@context' => 'https://schema.org', '@graph' => [['@type' => 'TouristTrip', 'name' => 'X']]]);
        $tour = $this->tour(['schema_jsonld_es' => $manual, 'faqs_es' => [['question' => 'P', 'answer' => 'R']]]);

        $out = json_decode(TourJsonLd::for($tour, 'es'), true);
        $this->assertSame(['TouristTrip', 'FAQPage'], array_column($out['@graph'], '@type'));

        $manualWithFaq = json_encode(['@context' => 'https://schema.org', '@graph' => [['@type' => 'FAQPage', 'mainEntity' => []]]]);
        $tour->update(['schema_jsonld_es' => $manualWithFaq]);
        $this->assertSame($manualWithFaq, TourJsonLd::for($tour->fresh(), 'es'));
    }
}
