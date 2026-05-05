<?php

namespace Database\Seeders;

use App\Models\Offer;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $offers = [
            [
                'title_es' => '10% de descuento haciendo tu reserva anticipada',
                'title_en' => '10% off booking in advance',
                'description_es' => 'Reserva con 30 días de anticipación y obtén un 10% de descuento en cualquier tour del catálogo.',
                'price' => 200,
                'image' => 'assets/banners/Rectangle 19211.jpg',
                'cta_label_es' => 'Leer más',
                'cta_url' => '/es/tours',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'title_es' => 'Tours grupales con tarifas especiales',
                'title_en' => 'Group tours with special rates',
                'description_es' => 'Para grupos de 6 o más personas, descuentos progresivos según el tamaño del grupo.',
                'price' => 200,
                'image' => 'assets/banners/Rectangle 19212.jpg',
                'cta_label_es' => 'Leer más',
                'cta_url' => '/es/tours',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'title_es' => 'Combo Lima + Cusco con vuelo incluido',
                'title_en' => 'Lima + Cusco combo with flight included',
                'description_es' => 'Paquete completo de 5 días/4 noches con vuelo doméstico, hospedaje y tours.',
                'price' => 200,
                'image' => 'assets/banners/Rectangle 19214.jpg',
                'cta_label_es' => 'Leer más',
                'cta_url' => '/es/tours',
                'is_active' => true,
                'order' => 3,
            ],
        ];

        foreach ($offers as $o) {
            Offer::updateOrCreate(['title_es' => $o['title_es']], $o);
        }
    }
}
