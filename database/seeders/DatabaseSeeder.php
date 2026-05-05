<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@limaviewtours.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('LimaTours2026!'),
                'email_verified_at' => now(),
            ]
        );

        // Regiones
        $regions = [
            ['slug' => 'lima', 'name_es' => 'Lima', 'name_en' => 'Lima', 'eyebrow_es' => 'EXPLORA LA CAPITAL', 'eyebrow_en' => 'EXPLORE THE CAPITAL', 'hero_image' => 'assets/banners/Rectangle 19216.jpg', 'order' => 1],
            ['slug' => 'ica', 'name_es' => 'Ica', 'name_en' => 'Ica', 'eyebrow_es' => 'AVENTURA EN EL DESIERTO', 'eyebrow_en' => 'DESERT ADVENTURE', 'hero_image' => 'assets/banners/Rectangle 19219.jpg', 'order' => 2],
            ['slug' => 'cusco', 'name_es' => 'Cusco', 'name_en' => 'Cusco', 'eyebrow_es' => 'CIUDADELA SAGRADA', 'eyebrow_en' => 'SACRED CITADEL', 'hero_image' => 'assets/banners/Rectangle 19218.jpg', 'order' => 3],
        ];
        foreach ($regions as $r) {
            Region::firstOrCreate(['slug' => $r['slug']], $r);
        }

        // Categorías
        $categories = [
            ['slug' => 'cultural', 'name_es' => 'Tours Culturales', 'name_en' => 'Cultural Tours', 'order' => 1],
            ['slug' => 'aventura', 'name_es' => 'Tours de Aventura', 'name_en' => 'Adventure Tours', 'order' => 2],
            ['slug' => 'gastronomia', 'name_es' => 'Experiencias Culinarias', 'name_en' => 'Culinary Experiences', 'order' => 3],
            ['slug' => 'otros', 'name_es' => 'Otros', 'name_en' => 'Other', 'order' => 4],
        ];
        foreach ($categories as $c) {
            Category::firstOrCreate(['slug' => $c['slug']], $c);
        }

        $this->call([
            TourSeeder::class,
            TestimonialSeeder::class,
            OfferSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
