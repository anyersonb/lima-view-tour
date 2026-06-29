<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class StaticPagesSeeder extends Seeder
{
    public function run(): void
    {
        Page::firstOrCreate(
            ['slug' => 'contacto'],
            [
                'title_es'          => 'Contáctanos',
                'title_en'          => 'Contact Us',
                'title_pt'          => 'Fale Conosco',
                'content_es'        => null,
                'content_en'        => null,
                'content_pt'        => null,
                'blocks'            => [],
                'is_published'      => true,
                'show_in_sitemap'   => true,
                'sitemap_priority'  => '0.6',
                'sitemap_changefreq'=> 'monthly',
            ]
        );

        Page::firstOrCreate(
            ['slug' => 'nosotros'],
            [
                'title_es'          => 'Nosotros',
                'title_en'          => 'About Us',
                'title_pt'          => 'Sobre Nós',
                'content_es'        => null,
                'content_en'        => null,
                'content_pt'        => null,
                'blocks'            => [],
                'is_published'      => true,
                'show_in_sitemap'   => true,
                'sitemap_priority'  => '0.6',
                'sitemap_changefreq'=> 'monthly',
            ]
        );
    }
}
