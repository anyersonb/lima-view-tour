<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Listing cards must show only what the tour has in the CMS, localized, and
 * the meta description must not read "tours por Todos nuestros tours".
 */
class ToursListingCardDataTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $o = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'duration' => '11 Hrs',
            'language' => 'Español / Inglés',
            'group_type' => 'Tour Grupal',
            'title_es' => 'Pachacamac completo',
            'title_en' => 'Pachacamac full',
            'title_pt' => 'Pachacamac inteiro',
        ], $o));
    }

    /** HTML of the mobile row cards only (first data-tour-cards block). */
    private function mobileCards(string $html): string
    {
        $start = strpos($html, 'sm:hidden" data-tour-cards>');
        $this->assertNotFalse($start);
        $end = strpos($html, 'hidden sm:grid', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    public function test_mobile_card_does_not_promise_pickup_or_free_cancellation_the_tour_does_not_have(): void
    {
        $this->tour();

        $cards = $this->mobileCards($this->get('/es/tours')->assertOk()->getContent());

        $this->assertStringContainsString('tour-card-row', $cards);
        $this->assertStringNotContainsString(__('ui.pickup_included', [], 'es'), $cards);
        $this->assertStringNotContainsString(__('ui.free_cancellation', [], 'es'), $cards);
        // Control: what the tour does have is still there.
        $this->assertStringContainsString('11 horas', $cards);
        $this->assertStringContainsString('Español / Inglés', $cards);
    }

    public function test_duration_and_language_are_localized_in_en_and_pt(): void
    {
        $this->tour();

        $en = $this->mobileCards($this->get('/en/tours')->assertOk()->getContent());
        $this->assertStringContainsString('11 hours', $en);
        $this->assertStringContainsString('Spanish / English', $en);
        $this->assertStringNotContainsString('Hrs', $en);
        $this->assertStringNotContainsString('Español', $en);

        $pt = $this->mobileCards($this->get('/pt/tours')->assertOk()->getContent());
        $this->assertStringContainsString('11 horas', $pt);
        $this->assertStringContainsString('Espanhol / Inglês', $pt);
    }

    public function test_tour_without_duration_or_language_has_no_invented_features(): void
    {
        $this->tour(['duration' => null, 'language' => '']);

        $cards = $this->mobileCards($this->get('/en/tours')->assertOk()->getContent());

        $this->assertStringContainsString('tour-card-row', $cards);
        $this->assertStringNotContainsString('Full Day', $cards);
        $this->assertStringNotContainsString('Spanish', $cards);
    }

    public function test_cards_carry_titles_in_the_three_languages_for_the_client_search(): void
    {
        $this->tour();

        $html = $this->get('/en/tours')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-search="pachacamac completo pachacamac full pachacamac inteiro"'));
    }

    public function test_modality_filter_groups_equivalents_and_hides_non_modalities_in_every_locale(): void
    {
        $this->tour(['group_type' => 'Grupal']);
        $this->tour(['group_type' => 'Tour Grupal']);
        $this->tour(['group_type' => 'MIN. 02 PERSONAS']);
        $this->tour(['group_type' => 'Tour privado']);

        $en = $this->get('/en/tours')->assertOk()->getContent();

        // The options render twice (desktop aside + mobile drawer).
        $this->assertSame(2, substr_count($en, 'value="group"'));
        $this->assertSame(2, substr_count($en, 'value="private"'));
        $this->assertStringNotContainsString('MIN. 02', $en);
        $this->assertStringNotContainsString('Tour Grupal', $en);
        $this->assertMatchesRegularExpression('/>\s*Group\s*</', $en);
        $this->assertMatchesRegularExpression('/>\s*Private\s*</', $en);

        $pt = $this->get('/pt/tours')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>\s*Em grupo\s*</', $pt);
        $this->assertDoesNotMatchRegularExpression('/>\s*Tour privado\s*</', $pt);
    }

    public function test_meta_description_is_clean_for_all_and_for_each_region(): void
    {
        $region = Region::query()->create(['slug' => 'cusco', 'name_es' => 'Cusco', 'name_en' => 'Cusco', 'is_active' => true]);
        $this->tour(['region_id' => $region->id]);

        foreach (['es', 'en', 'pt'] as $locale) {
            $all = $this->metaDescription($this->get("/{$locale}/tours")->assertOk()->getContent());
            $this->assertSame(__('ui.tours_meta_description_all', [], $locale), $all);
            $this->assertStringNotContainsString('Todos nuestros tours', $all);
            $this->assertStringNotContainsString('All our tours', $all);
            $this->assertStringNotContainsString('Todos os nossos tours', $all);
            $this->assertGreaterThanOrEqual(120, mb_strlen($all));
            $this->assertLessThanOrEqual(160, mb_strlen($all));

            $cat = $this->metaDescription($this->get("/{$locale}/tours/categoria/cusco")->assertOk()->getContent());
            $this->assertStringContainsString('Cusco', $cat);
            $this->assertStringNotContainsString(' por ', $cat);
            $this->assertGreaterThanOrEqual(120, mb_strlen($cat));
            $this->assertLessThanOrEqual(160, mb_strlen($cat));
        }
    }

    private function metaDescription(string $html): string
    {
        $this->assertSame(1, preg_match('/<meta name="description" content="([^"]*)"/', $html, $m), 'meta description not found');

        return html_entity_decode($m[1], ENT_QUOTES);
    }
}
