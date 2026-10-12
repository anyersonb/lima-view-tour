<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Filament\Resources\OfferResource\Pages\EditOffer;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\RegionResource\Pages\EditRegion;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Saving a form WITHOUT touching its image fields must keep the stored path,
 * even when the file is not on the disk (legacy assets/..., a file that was
 * lost, odd names). Without ->fetchFileInformation(false) Filament drops the
 * unreadable file from the state and the save wipes the column.
 * Same contract as TourImagePathsPreservedOnSaveTest, for the other resources.
 */
class FileUploadPathsPreservedOnSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['public', 'media', 'local'] as $disk) {
            Storage::fake($disk);
        }
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));
    }

    /** @return array<string, array{0: string}> */
    public static function paths(): array
    {
        return [
            'legacy asset outside storage' => ['assets/banners/hero.jpg'],
            'upload missing on disk' => ['offers/lost-file.webp'],
            'name with spaces and accents' => ['offers/Cañón año 01.jpg'],
        ];
    }

    /** @dataProvider paths */
    public function test_offer_image_survives_a_save_without_touching_it(string $path): void
    {
        $offer = Offer::create(['title_es' => 'Oferta', 'cta_label_es' => 'Leer más', 'is_active' => true, 'image' => $path]);

        Livewire::test(EditOffer::class, ['record' => $offer->getKey()])->call('save')->assertHasNoFormErrors();

        $this->assertSame($path, $offer->refresh()->image);
    }

    /** @dataProvider paths */
    public function test_region_images_survive_a_save_without_touching_them(string $path): void
    {
        $region = Region::create(['slug' => 'cusco', 'name_es' => 'Cusco', 'is_active' => true, 'hero_image' => $path, 'seo_image' => $path]);

        Livewire::test(EditRegion::class, ['record' => $region->slug])->call('save')->assertHasNoFormErrors();

        $region->refresh();
        $this->assertSame($path, $region->hero_image);
        $this->assertSame($path, $region->seo_image);
    }

    /** @dataProvider paths */
    public function test_page_block_images_survive_a_save_without_touching_them(string $path): void
    {
        $page = Page::factory()->create([
            'slug' => 'contacto',
            'hero_image' => $path,
            'blocks' => ['img_hero' => $path, 'img_collage_1' => 'paginas/lost.webp'],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getKey()])->call('save')->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame($path, $page->hero_image);
        $this->assertSame($path, $page->blocks['img_hero'] ?? null);
        $this->assertSame('paginas/lost.webp', $page->blocks['img_collage_1'] ?? null);
    }

    /** @dataProvider paths */
    public function test_settings_images_survive_a_save_without_touching_them(string $path): void
    {
        foreach (['seo_og_image', 'home_hero_image', 'home_destino_img_1', 'home_exp_img_2', 'gracias_banner_image'] as $key) {
            Setting::set($key, $path);
        }

        Livewire::test(Settings::class)->call('save');

        foreach (['seo_og_image', 'home_hero_image', 'home_destino_img_1', 'home_exp_img_2', 'gracias_banner_image'] as $key) {
            $this->assertSame($path, Setting::get($key), $key);
        }
    }
}
