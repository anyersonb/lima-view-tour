<?php

namespace Tests\Unit\Models;

use App\Models\Tour;
use Tests\TestCase;

/** Tour::localized(): translated content or a clean fallback to Spanish. */
class TourLocalizedContentTest extends TestCase
{
    private function tour(array $attrs): Tour
    {
        return (new Tour)->forceFill($attrs);
    }

    public function test_returns_the_translation_when_it_has_content(): void
    {
        $t = $this->tour(['includes_es' => ['A'], 'includes_pt' => ['B']]);

        $this->assertSame(['B'], $t->localized('includes', 'pt'));
    }

    /** @dataProvider blanks */
    public function test_falls_back_to_spanish_when_the_translation_is_blank(mixed $blank): void
    {
        $t = $this->tour(['notes_es' => 'Nota ES', 'notes_pt' => $blank]);

        $this->assertSame('Nota ES', $t->localized('notes', 'pt'));
    }

    /** @return array<string, array{0: mixed}> */
    public static function blanks(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'spaces' => ['   '],
            'empty array' => [[]],
            'rows with empty cells' => [[['title' => '', 'description' => ' ']]],
        ];
    }

    public function test_spanish_locale_and_missing_column_return_the_spanish_value(): void
    {
        $t = $this->tour(['itinerary_es' => [['title' => 'Uno']]]);

        $this->assertSame([['title' => 'Uno']], $t->localized('itinerary', 'es'));
        $this->assertSame([['title' => 'Uno']], $t->localized('itinerary', 'pt'));
    }

    public function test_defaults_to_the_application_locale(): void
    {
        app()->setLocale('en');
        $t = $this->tour(['notes_es' => 'ES', 'notes_en' => 'EN']);

        $this->assertSame('EN', $t->localized('notes'));
    }
}
