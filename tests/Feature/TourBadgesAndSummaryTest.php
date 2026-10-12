<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Data contract for the home card: badges, real-review summary, localized languages. */
class TourBadgesAndSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge(['is_published' => true], $overrides));
    }

    private function review(Tour $tour, int $rating): Testimonial
    {
        return Testimonial::create([
            'tour_id' => $tour->id,
            'name' => 'Viajero',
            'quote_es' => 'Comentario de prueba con más de diez caracteres.',
            'rating' => $rating,
            'source' => 'Web',
            'is_active' => true,
            'status' => 'approved',
        ]);
    }

    public function test_review_summary_is_null_without_real_reviews_even_with_dirty_legacy_columns(): void
    {
        $tour = $this->tour(['rating' => 4.8, 'reviews_count' => 30]);

        $this->assertNull($tour->reviewSummary());
    }

    public function test_review_summary_comes_from_published_reviews_only(): void
    {
        $tour = $this->tour(['rating' => 1.0, 'reviews_count' => 0]);
        $this->review($tour, 5);
        $this->review($tour, 4);

        $summary = $tour->fresh()->reviewSummary();

        $this->assertSame(['rating' => 4.5, 'count' => 2], $summary);
    }

    public function test_show_top_rated_exists_defaults_to_false_and_is_boolean(): void
    {
        $this->assertTrue(Schema::hasColumn('tours', 'show_top_rated'));

        $tour = $this->tour()->fresh();
        $this->assertFalse($tour->show_top_rated);

        $tour->update(['show_top_rated' => true]);
        $this->assertTrue($tour->fresh()->show_top_rated);
    }

    public function test_badge_keys_exist_in_all_locales(): void
    {
        $expected = [
            'es' => ['Mejor valorado', 'Más vendido'],
            'en' => ['Top rated', 'Best seller'],
            'pt' => ['Mais bem avaliado', 'Mais vendido'],
        ];

        foreach ($expected as $locale => [$top, $best]) {
            $this->assertSame($top, __('ui.badge_top_rated', [], $locale));
            $this->assertSame($best, __('ui.badge_best_seller', [], $locale));
        }
    }

    public function test_languages_label_is_localized_per_locale(): void
    {
        $tour = $this->tour(['language' => 'Español / Inglés']);

        $this->assertSame('Español / Inglés', $tour->languagesLabel('es'));
        $this->assertSame('Spanish / English', $tour->languagesLabel('en'));
        $this->assertSame('Espanhol / Inglês', $tour->languagesLabel('pt'));
    }

    public function test_languages_label_is_null_when_tour_declares_none(): void
    {
        $this->assertNull($this->tour(['language' => ''])->languagesLabel('es'));
    }
}
