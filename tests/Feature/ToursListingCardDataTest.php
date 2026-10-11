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

    /** @return array{0: string, 1: string} [mobile cards, desktop cards] */
    private function bothCards(string $locale = 'es'): array
    {
        $html = $this->get("/{$locale}/tours")->assertOk()->getContent();

        return [$this->mobileCards($html), substr($html, strpos($html, 'hidden sm:grid'))];
    }

    public function test_cards_show_pickup_and_free_cancellation_when_the_tour_switches_are_on(): void
    {
        $this->tour(['pickup_included' => true, 'free_cancellation' => true]);

        foreach ($this->bothCards() as $cards) {
            $this->assertStringContainsString('Recojo incluido', $cards);
            $this->assertStringContainsString('Cancelación gratuita', $cards);
        }
    }

    public function test_cards_hide_each_feature_when_its_switch_is_off(): void
    {
        $this->tour(['pickup_included' => false, 'free_cancellation' => true]);
        foreach ($this->bothCards() as $cards) {
            $this->assertStringNotContainsString('Recojo incluido', $cards);
            $this->assertStringContainsString('Cancelación gratuita', $cards);
            $this->assertStringNotContainsString('Sin recojo', $cards);
        }

        Tour::query()->update(['pickup_included' => true, 'free_cancellation' => false]);
        foreach ($this->bothCards() as $cards) {
            $this->assertStringContainsString('Recojo incluido', $cards);
            $this->assertStringNotContainsString('Cancelación gratuita', $cards);
        }
    }

    public function test_switches_default_to_on_for_a_tour_created_without_them(): void
    {
        $tour = Tour::factory()->create()->fresh();

        $this->assertTrue($tour->pickup_included);
        $this->assertTrue($tour->free_cancellation);
    }

    public function test_migration_leaves_existing_tours_with_both_switches_on(): void
    {
        $a = $this->tour();
        $b = $this->tour();
        \Illuminate\Support\Facades\DB::table('tours')->update(['pickup_included' => false, 'free_cancellation' => false]);

        $migration = require database_path('migrations/2026_10_11_000002_add_pickup_and_cancellation_flags_to_tours_table.php');
        $migration->up(); // already has the columns: must be a safe re-run

        foreach ([$a, $b] as $t) {
            $row = \Illuminate\Support\Facades\DB::table('tours')->find($t->id);
            $this->assertEquals(1, $row->pickup_included);
            $this->assertEquals(1, $row->free_cancellation);
        }

        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('tours', 'pickup_included'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('tours', 'free_cancellation'));
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('tours', 'free_cancellation'));
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
