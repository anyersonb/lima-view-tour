<?php

namespace Tests\Feature;

use App\Support\ImageOptimizer;
use App\Support\ImageTooLargeException;
use App\Support\TourThumbnail;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

class ImagePixelCapTest extends TestCase
{
    private string $originalLimit;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required.');
        }

        $this->originalLimit = (string) ini_get('memory_limit');
        ini_set('memory_limit', '-1');
        Storage::fake('public');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalLimit);
        parent::tearDown();
    }

    /** ~90 byte PNG whose IHDR declares a huge canvas (never decodable). */
    private function bombPng(int $w = 30000, int $h = 30000): string
    {
        $ihdr = 'IHDR'.pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));
    }

    private function normalPng(int $w = 1000, int $h = 600): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    private function temp(string $bytes): TemporaryUploadedFile
    {
        Storage::disk('local')->put('livewire-tmp/t.png', $bytes);

        return new TemporaryUploadedFile('t.png', 'local');
    }

    public function test_thumbnail_rejects_bomb_header_with_unlimited_memory(): void
    {
        $this->assertSame('-1', ini_get('memory_limit'));
        Storage::disk('public')->put('tours/covers/bomb.png', $this->bombPng());

        $this->assertSame('too_big', TourThumbnail::generate('tours/covers/bomb.png', true));
        $this->assertFalse(Storage::disk('public')->exists(TourThumbnail::thumbPath('tours/covers/bomb.png')));
    }

    public function test_thumbnail_still_works_for_normal_image(): void
    {
        Storage::disk('public')->put('tours/covers/ok.png', str_repeat('x', 0).$this->normalPng(1600, 1000));

        $this->assertSame('created', TourThumbnail::generate('tours/covers/ok.png', true));
    }

    public function test_optimizer_rejects_bomb_before_decoding(): void
    {
        $this->expectException(ImageTooLargeException::class);

        ImageOptimizer::store($this->temp($this->bombPng()), 'tours/covers', 1600, disk: 'public');
    }

    public function test_optimizer_stores_nothing_for_bomb_and_accepts_normal(): void
    {
        try {
            ImageOptimizer::store($this->temp($this->bombPng()), 'tours/covers', 1600, disk: 'public');
            $this->fail('Bomb was not rejected');
        } catch (ImageTooLargeException) {
            $this->assertSame([], Storage::disk('public')->allFiles('tours/covers'));
        }

        $path = ImageOptimizer::store($this->temp($this->normalPng()), 'tours/covers', 1600, disk: 'public');
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_limit_boundary(): void
    {
        $this->assertFalse(ImageOptimizer::exceedsPixelLimit(8000, 5000));
        $this->assertTrue(ImageOptimizer::exceedsPixelLimit(8001, 5000));
    }
}
