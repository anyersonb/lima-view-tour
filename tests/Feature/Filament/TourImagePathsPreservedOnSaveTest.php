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
 * Saving the Tour form WITHOUT touching the image fields must never change
 * cover_image / gallery, whatever kind of path they hold (seeded assets/...,
 * storage uploads, missing files, odd names).
 */
class TourImagePathsPreservedOnSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function paths(): array
    {
        return [
            'assets path (public/, not in storage)' => ['assets/banners/hero.jpg', false],
            'tours path existing in storage' => ['tours/covers/exists.webp', true],
            'tours path missing on disk' => ['tours/covers/missing.webp', false],
            'name with spaces' => ['tours/covers/Machu Picchu 01.jpg', false],
            'name with ellipsis U+2026' => ["tours/covers/Machu\u{2026}Picchu.jpg", false],
            'name with accents, existing' => ['tours/covers/Cañón año.jpg', true],
        ];
    }

    /** @dataProvider paths */
    public function test_cover_survives_a_save_without_touching_it(string $path, bool $onDisk): void
    {
        if ($onDisk) {
            Storage::disk('public')->put($path, 'x');
        }
        $tour = Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'cover_image' => $path]);

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])->call('save')->assertHasNoFormErrors();

        $this->assertSame($path, $tour->refresh()->cover_image);
    }

    /** @dataProvider paths */
    public function test_gallery_survives_a_save_without_touching_it(string $path, bool $onDisk): void
    {
        if ($onDisk) {
            Storage::disk('public')->put($path, 'x');
        }
        $gallery = [$path, 'assets/gallery/other.jpg'];
        $tour = Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'gallery' => $gallery]);

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])->call('save')->assertHasNoFormErrors();

        $this->assertSame($gallery, array_values((array) $tour->refresh()->gallery));
    }

    /** @dataProvider paths */
    public function test_seo_image_survives_a_save_without_touching_it(string $path, bool $onDisk): void
    {
        if ($onDisk) {
            Storage::disk('public')->put($path, 'x');
        }
        $tour = Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'seo_image' => $path]);

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])->call('save')->assertHasNoFormErrors();

        $this->assertSame($path, $tour->refresh()->seo_image);
    }

    private function png(): string
    {
        $img = imagecreatetruecolor(800, 500);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    /** Control: a deliberate new upload still replaces a (non-existent in storage) cover. */
    public function test_uploading_a_new_cover_replaces_the_old_one(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required.');
        }
        $tour = Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'cover_image' => 'assets/banners/hero.jpg']);

        Livewire::test(EditTour::class, ['record' => $tour->getKey()])
            ->set('data.cover_image', [UploadedFile::fake()->createWithContent('new.png', $this->png())])
            ->call('save')
            ->assertHasNoFormErrors();

        $new = $tour->refresh()->cover_image;
        $this->assertStringStartsWith('tours/covers/', $new);
        Storage::disk('public')->assertExists($new);
    }

    /** Control: removing a photo from the gallery on purpose still removes it. */
    public function test_removing_a_gallery_photo_on_purpose_removes_it(): void
    {
        Storage::disk('public')->put('tours/gallery/a.webp', 'x');
        $tour = Tour::factory()->create(['is_published' => true, 'slug_en' => null, 'slug_pt' => null, 'gallery' => ['tours/gallery/a.webp', 'assets/gallery/b.jpg']]);

        $lw = Livewire::test(EditTour::class, ['record' => $tour->getKey()]);
        $state = $lw->get('data.gallery');
        $this->assertCount(2, $state);
        $keyOfB = array_search('assets/gallery/b.jpg', $state, true);
        unset($state[$keyOfB]);
        $lw->set('data.gallery', $state)->call('save')->assertHasNoFormErrors();

        $this->assertSame(['tours/gallery/a.webp'], array_values((array) $tour->refresh()->gallery));
    }
}
