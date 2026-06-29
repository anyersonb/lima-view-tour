<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'site_name', 'value' => 'Lima View Tours', 'group' => 'general'],
            ['key' => 'site_tagline_es', 'value' => 'Descubre la magia de Perú, un viaje que transforma', 'group' => 'general'],
            ['key' => 'site_tagline_en', 'value' => 'Discover the magic of Peru, a journey that transforms', 'group' => 'general'],
            ['key' => 'site_description_es', 'value' => 'Tours por Lima, Ica, Cusco y Machu Picchu con guías oficiales y experiencias auténticas.', 'group' => 'general'],
            ['key' => 'site_description_en', 'value' => 'Tours in Lima, Ica, Cusco and Machu Picchu with official guides and authentic experiences.', 'group' => 'general'],

            // Contacto
            ['key' => 'contact_email', 'value' => 'hola@limaviewtours.com', 'group' => 'contact'],
            ['key' => 'contact_phone', 'value' => '+51 925 886 725', 'group' => 'contact'],
            ['key' => 'contact_phone_secondary', 'value' => '190010088', 'group' => 'contact'],
            ['key' => 'contact_address_es', 'value' => 'Av. Larcomar 233, Of. 410 — Miraflores, Lima', 'group' => 'contact'],
            ['key' => 'contact_address_en', 'value' => 'Larcomar Ave. 233, Off. 410 — Miraflores, Lima', 'group' => 'contact'],
            ['key' => 'contact_hours_es', 'value' => 'Lun – Vie: 9:00 a.m. – 7:00 p.m.', 'group' => 'contact'],
            ['key' => 'contact_hours_en', 'value' => 'Mon – Fri: 9:00 a.m. – 7:00 p.m.', 'group' => 'contact'],
            ['key' => 'whatsapp', 'value' => '51925886725', 'group' => 'contact'],

            // Redes sociales
            ['key' => 'social_instagram', 'value' => 'https://instagram.com/limaviewtours', 'group' => 'social'],
            ['key' => 'social_facebook', 'value' => 'https://facebook.com/limaviewtours', 'group' => 'social'],
            ['key' => 'social_tiktok', 'value' => 'https://tiktok.com/@limaviewtours', 'group' => 'social'],
            ['key' => 'social_youtube', 'value' => 'https://youtube.com/@limaviewtours', 'group' => 'social'],

            // SEO
            ['key' => 'seo_default_title', 'value' => 'Lima View Tours — Tours auténticos por Perú', 'group' => 'seo'],
            ['key' => 'seo_default_description', 'value' => 'Descubre Lima, Ica, Cusco y Machu Picchu con Lima View Tours. Guías oficiales, transporte cómodo y experiencias diseñadas para viajeros exigentes.', 'group' => 'seo'],
            ['key' => 'seo_default_keywords', 'value' => 'tours peru, lima view tours, machu picchu, huacachina, paracas, cusco', 'group' => 'seo'],
            ['key' => 'seo_og_image', 'value' => 'assets/banners/banner-hero.jpg', 'group' => 'seo'],
            ['key' => 'seo_google_site_verification', 'value' => '', 'group' => 'seo'],
            ['key' => 'seo_bing_site_verification', 'value' => '', 'group' => 'seo'],
            ['key' => 'seo_google_analytics_id', 'value' => '', 'group' => 'seo'],
            ['key' => 'seo_gtm_id', 'value' => '', 'group' => 'seo'],
            ['key' => 'seo_facebook_pixel', 'value' => '', 'group' => 'seo'],

            // Hero
            ['key' => 'hero_title_es', 'value' => 'Descubre la magia de Perú, un viaje que transforma', 'group' => 'home'],
            ['key' => 'hero_title_en', 'value' => 'Discover the magic of Peru, a journey that transforms', 'group' => 'home'],
            ['key' => 'hero_subtitle_es', 'value' => 'Vive una aventura inolvidable por los destinos más impresionantes del Perú.', 'group' => 'home'],
            ['key' => 'hero_image', 'value' => 'assets/banners/banner-hero.jpg', 'group' => 'home'],

            // Stats
            ['key' => 'stats_travelers', 'value' => '+824', 'group' => 'home'],
            ['key' => 'stats_years', 'value' => '+11', 'group' => 'home'],
            ['key' => 'stats_rating', 'value' => '4.8', 'group' => 'home'],
            ['key' => 'stats_tours', 'value' => '+50', 'group' => 'home'],

            // Métodos de pago
            ['key' => 'payment_methods', 'type' => 'array', 'value' => json_encode(['VISA','Mastercard','AmEx','PayPal','Culqi']), 'group' => 'payment'],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(['key' => $s['key']], $s);
        }
    }
}
