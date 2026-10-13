<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Support\TourThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TourThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required.');
        }

        Storage::fake('public');
    }

    /** Noisy PNG so it is heavy, like the real 2.4 MB cover. */
    private function putBigPng(string $path, int $w = 1600, int $h = 1000): int
    {
        $img = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 4000; $i++) {
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagepng($img, null, 0);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        Storage::disk('public')->put($path, $bytes);

        return strlen($bytes);
    }

    public function test_generates_a_much_lighter_webp_and_url_points_to_it(): void
    {
        $original = $this->putBigPng('tours/covers/big.png');

        $this->assertSame('created', TourThumbnail::generate('tours/covers/big.png'));

        $thumb = TourThumbnail::thumbPath('tours/covers/big.png');
        Storage::disk('public')->assertExists($thumb);

        $size = Storage::disk('public')->size($thumb);
        $this->assertLessThan($original / 10, $size);

        [$w] = getimagesizefromstring(Storage::disk('public')->get($thumb));
        $this->assertSame(TourThumbnail::WIDTH, $w);

        $this->assertStringContainsString('/storage/'.$thumb, TourThumbnail::url('tours/covers/big.png'));
    }

    public function test_url_falls_back_to_the_original_when_there_is_no_thumbnail(): void
    {
        $this->putBigPng('tours/covers/nothumb.png');

        $url = TourThumbnail::url('tours/covers/nothumb.png');

        $this->assertStringContainsString('tours/covers/nothumb.png', $url);
        $this->assertStringNotContainsString('thumbs', $url);
    }

    public function test_it_never_touches_external_assets_or_missing_files(): void
    {
        $this->assertSame('unsupported', TourThumbnail::generate('assets/banners/banner-hero.jpg'));
        $this->assertSame('unsupported', TourThumbnail::generate('https://example.com/a.png'));
        $this->assertSame('missing', TourThumbnail::generate('tours/covers/nope.png'));
        $this->assertStringContainsString('banner-hero.jpg', TourThumbnail::url('assets/banners/banner-hero.jpg'));
        $this->assertNull(TourThumbnail::url(null));
    }

    public function test_is_idempotent_and_force_regenerates(): void
    {
        $this->putBigPng('tours/covers/idem.png');

        $this->assertSame('created', TourThumbnail::generate('tours/covers/idem.png'));
        $this->assertSame('exists', TourThumbnail::generate('tours/covers/idem.png'));
        $this->assertSame('created', TourThumbnail::generate('tours/covers/idem.png', true));
    }

    public function test_an_already_small_image_is_left_alone(): void
    {
        $img = imagecreatetruecolor(200, 100);
        ob_start();
        imagepng($img);
        Storage::disk('public')->put('tours/covers/tiny.png', (string) ob_get_clean());

        $this->assertSame('small', TourThumbnail::generate('tours/covers/tiny.png'));
    }

    public function test_command_generates_for_every_tour_and_survives_a_missing_file(): void
    {
        $this->putBigPng('tours/covers/a.png');
        Tour::withoutEvents(function () {
            Tour::factory()->create(['cover_image' => 'tours/covers/a.png']);
            Tour::factory()->create(['cover_image' => 'tours/covers/gone.png']);
            Tour::factory()->create(['cover_image' => null]);
        });

        $this->artisan('tours:thumbnails')->expectsOutputToContain('created: 1')->assertSuccessful();

        Storage::disk('public')->assertExists(TourThumbnail::thumbPath('tours/covers/a.png'));
    }

    public function test_saving_a_tour_with_a_new_cover_generates_the_thumbnail(): void
    {
        $this->putBigPng('tours/covers/save.png');

        Tour::factory()->create(['cover_image' => 'tours/covers/save.png']);

        Storage::disk('public')->assertExists(TourThumbnail::thumbPath('tours/covers/save.png'));
    }

    public function test_a_broken_cover_never_blocks_saving_the_tour(): void
    {
        Storage::disk('public')->put('tours/covers/broken.png', 'not an image');

        $tour = Tour::factory()->create(['cover_image' => 'tours/covers/broken.png']);

        $this->assertNotNull($tour->fresh());
        Storage::disk('public')->assertMissing(TourThumbnail::thumbPath('tours/covers/broken.png'));
    }

    public function test_the_listing_mobile_card_uses_the_thumbnail_and_falls_back_to_the_original(): void
    {
        $this->putBigPng('tours/covers/withthumb.png');
        $this->putBigPng('tours/covers/without.png');
        Tour::factory()->create(['cover_image' => 'tours/covers/withthumb.png']); // saved hook builds the thumb
        Tour::withoutEvents(fn () => Tour::factory()->create(['cover_image' => 'tours/covers/without.png']));

        $html = $this->get('/es/tours')->assertOk()->getContent();
        $start = strpos($html, 'data-tour-cards>');
        $cards = substr($html, $start, strpos($html, 'data-tour-empty', $start) - $start);

        // Un unico <img>: la miniatura reemplaza al original (sin <source> sin type que lo reintroduzca); sin miniatura, el original root-relativo.
        $this->assertMatchesRegularExpression('#<img src="[^"]*thumbs/withthumb-#', $cards);
        $this->assertMatchesRegularExpression('#<img src="[^"]*tours/covers/without.png#', $cards);
        $this->assertStringNotContainsString('<source', $cards);
        $this->assertStringNotContainsString('tours/covers/withthumb.png', $cards);
        $this->assertDoesNotMatchRegularExpression('#src="https?://[^"]*tours/covers#', $cards);
    }
}
