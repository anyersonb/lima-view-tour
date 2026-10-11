<?php

namespace Tests\Unit;

use App\Support\TourListing;
use Tests\TestCase;

class TourListingTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: string, 2: ?string}> */
    public static function durations(): array
    {
        return [
            'hrs es' => ['11 Hrs', 'es', '11 horas'],
            'hrs en' => ['11 Hrs', 'en', '11 hours'],
            'hrs pt' => ['11 Hrs', 'pt', '11 horas'],
            'horas en' => ['4 Horas', 'en', '4 hours'],
            'one hour' => ['1 hora', 'en', '1 hour'],
            'days' => ['2 Días', 'en', '2 days'],
            'days pt' => ['3 dias', 'pt', '3 dias'],
            'full day es' => ['Full Day', 'es', 'Día completo'],
            'full day en' => ['Full Day', 'en', 'Full day'],
            'full day pt' => ['Full Day', 'pt', 'Dia inteiro'],
            'half day' => ['Medio día', 'en', 'Half day'],
            'free text kept' => ['Según itinerario', 'en', 'Según itinerario'],
            'empty is null, no invented Full Day' => ['', 'es', null],
            'null is null' => [null, 'en', null],
        ];
    }

    /** @dataProvider durations */
    public function test_duration_label_is_localized_and_never_invented(?string $raw, string $locale, ?string $expected): void
    {
        $this->assertSame($expected, TourListing::durationLabel($raw, $locale));
    }

    public function test_hrs_duration_is_classified_as_full_day_not_other(): void
    {
        $this->assertSame('full-day', TourListing::classifyDuration('11 Hrs')['bucket']);
        $this->assertSame(11, TourListing::classifyDuration('11 Hrs')['weight']);
        $this->assertSame('medio-dia', TourListing::classifyDuration('4 Horas')['bucket']);
        $this->assertNull(TourListing::classifyDuration('')['bucket']);
    }

    public function test_language_label_is_translated_per_locale(): void
    {
        $this->assertSame('Español / Inglés', TourListing::languageLabel('Español/Inglés', 'es'));
        $this->assertSame('Spanish / English', TourListing::languageLabel('Español / Inglés', 'en'));
        $this->assertSame('Espanhol / Inglês', TourListing::languageLabel('Español / Inglés', 'pt'));
        $this->assertSame('English', TourListing::languageLabel('Inglés', 'en'));
        $this->assertSame(['es', 'en'], TourListing::languageCodes('Español / Inglés'));
        $this->assertNull(TourListing::languageLabel('', 'en'));
        $this->assertNull(TourListing::languageLabel(null, 'en'));
    }

    /** @return array<int, array{0: string, 1: ?string}> */
    public static function modalities(): array
    {
        return [
            ['Grupal', 'group'],
            ['Tour Grupal', 'group'],
            ['GRUPAL', 'group'],
            ['Group', 'group'],
            ['Tour privado', 'private'],
            ['Privado', 'private'],
            ['Tour Grupal y privado', 'group-private'],
            ['Grupal / Privado', 'group-private'],
            ['MIN. 02 PERSONAS', null],
            ['Mínimo 2 personas', null],
            ['', null],
        ];
    }

    /** @dataProvider modalities */
    public function test_modality_collapses_equivalents_and_drops_non_modalities(string $raw, ?string $slug): void
    {
        $this->assertSame($slug, TourListing::modality($raw)['slug'] ?? null);
    }

    public function test_modality_label_is_localized(): void
    {
        $this->assertSame('Grupal', TourListing::modality('Tour Grupal', 'es')['label']);
        $this->assertSame('Group', TourListing::modality('Tour Grupal', 'en')['label']);
        $this->assertSame('Em grupo', TourListing::modality('Tour Grupal', 'pt')['label']);
        $this->assertSame('Private', TourListing::modality('Tour privado', 'en')['label']);
        $this->assertSame('Group and private', TourListing::modality('Tour Grupal y privado', 'en')['label']);
    }
}
