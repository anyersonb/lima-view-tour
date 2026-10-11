<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cover upload through the real Filament form (ImageOptimizer::saver): an image
 * over 40 MP must surface as a validation error on `cover_image`, never a 500,
 * and a normal image must upload fine.
 */
class TourCoverUploadFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required.');
        }

        Storage::fake('public');
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));
    }

    private function tour(?string $cover = null): Tour
    {
        return Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'cover_image' => $cover]);
    }

    private function bombPng(): string
    {
        $ihdr = 'IHDR'.pack('NNCCCCC', 30000, 30000, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr)).pack('N', 0).'IEND'.pack('N', crc32('IEND'));
    }

    private function normalPng(): string
    {
        $img = imagecreatetruecolor(800, 500);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    /** @test */
    public function an_image_over_40_megapixels_gives_a_cover_validation_error_not_a_500(): void
    {
        Storage::disk('public')->put('tours/covers/old.webp', 'old');
        $tour = $this->tour('tours/covers/old.webp');

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->set('data.cover_image', [UploadedFile::fake()->createWithContent('bomb.png', $this->bombPng())])
            ->call('save')
            ->assertHasFormErrors(['cover_image']);

        $this->assertSame('tours/covers/old.webp', $tour->refresh()->cover_image);
        $this->assertTrue(
            Storage::disk('public')->exists('tours/covers/old.webp'),
            'A rejected upload must not delete the existing cover from disk.'
        );
    }

    /** @test */
    public function a_normal_image_uploads_and_replaces_the_cover(): void
    {
        $tour = $this->tour();

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->set('data.cover_image', [UploadedFile::fake()->createWithContent('ok.png', $this->normalPng())])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $tour->refresh()->cover_image;
        $this->assertNotEmpty($path);
        $this->assertStringStartsWith('tours/covers/', $path);
        Storage::disk('public')->assertExists($path);
    }
}
