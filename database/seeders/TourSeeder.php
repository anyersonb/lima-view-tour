<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Region;
use App\Models\Tour;
use Illuminate\Database\Seeder;

class TourSeeder extends Seeder
{
    public function run(): void
    {
        $regions = Region::pluck('id', 'slug');
        $cats = Category::pluck('id', 'slug');

        $tours = [
            [
                'slug' => 'huacachina-paracas-full-day',
                'region_id' => $regions['ica'] ?? null,
                'category_id' => $cats['aventura'] ?? null,
                'title_es' => 'Tour de día Completo al Oasis de Huacachina + Islas Ballestas en Paracas',
                'title_en' => 'Full Day Tour to Huacachina Oasis + Ballestas Islands in Paracas',
                'subtitle_es' => 'Aventura, naturaleza y desierto en una sola jornada',
                'description_es' => 'Disfruta de un día completo combinando la magia del oasis de Huacachina con la riqueza natural de las Islas Ballestas en Paracas. Una experiencia diseñada para viajeros que buscan diversidad de paisajes en una sola jornada.',
                'description_en' => 'Enjoy a full day combining the magic of Huacachina oasis with the natural wealth of the Ballestas Islands. An experience designed for travelers seeking diverse landscapes in a single day.',
                'price' => 100,
                'price_before' => 125,
                'duration' => 'Full Day',
                'departure_time' => '05:00 AM',
                'return_time' => '10:30 PM',
                'max_capacity' => 20,
                'cover_image' => 'assets/banners/Rectangle 19210.jpg',
                'gallery' => ['assets/banners/Rectangle 19210.jpg','assets/banners/Rectangle 19211.jpg','assets/banners/Rectangle 19212.jpg','assets/banners/Rectangle 19214.jpg','assets/banners/Rectangle 19215.jpg'],
                'badge_text' => 'CUPOS LIMITADOS',
                'badge_type' => 'warn',
                'rating' => 4.6,
                'reviews_count' => 30,
                'is_featured' => true,
                'is_published' => true,
                'order' => 1,
                'itinerary_es' => [
                    ['time' => '05:00 AM', 'title' => 'Recojo del hotel', 'description' => 'Pasamos a recogerte por tu hotel en Lima en una unidad cómoda y climatizada.'],
                    ['time' => '07:30 AM', 'title' => 'Llegada a Paracas', 'description' => 'Desayuno breve y briefing del recorrido por las Islas Ballestas.'],
                    ['time' => '08:30 AM', 'title' => 'Islas Ballestas', 'description' => 'Embarcamos para observar lobos marinos, pingüinos y aves guaneras.'],
                    ['time' => '11:00 AM', 'title' => 'Reserva de Paracas', 'description' => 'Recorrido panorámico: Catedral, Playa Roja y mirador del Cóndor.'],
                    ['time' => '01:30 PM', 'title' => 'Almuerzo en Ica', 'description' => 'Almuerzo típico (no incluido).'],
                    ['time' => '03:00 PM', 'title' => 'Oasis de Huacachina', 'description' => 'Tiempo libre y opcional de tubulares + sandboarding.'],
                    ['time' => '06:30 PM', 'title' => 'Retorno a Lima', 'description' => 'Salida con dejada en hotel céntrico.'],
                ],
                'includes_es' => ['Recojo y retorno al hotel', 'Transporte turístico climatizado', 'Guía oficial bilingüe', 'Embarque a Islas Ballestas', 'Ingreso a Reserva de Paracas'],
                'excludes_es' => ['Almuerzo en Ica', 'Tubulares en Huacachina', 'Bebidas adicionales', 'Propinas'],
                'recommendations_es' => "Llevar protector solar SPF50+, lentes de sol, sombrero, ropa cómoda, cámara, agua y documento de identidad.",
                'notes_es' => 'El recorrido marítimo puede sufrir cambios por condiciones climáticas. En caso de cancelación se reembolsa o reagenda.',
                'seo_title' => 'Tour Huacachina + Islas Ballestas desde Lima | Lima View Tours',
                'seo_description' => 'Reserva el tour Full Day Huacachina + Islas Ballestas. Desde Lima, con guía oficial, transporte y mejor precio.',
            ],
            [
                'slug' => 'lima-ancestral-colonial',
                'region_id' => $regions['lima'] ?? null,
                'category_id' => $cats['cultural'] ?? null,
                'title_es' => 'Full Day Lima Ancestral, Colonial y Moderna',
                'title_en' => 'Full Day Ancient, Colonial and Modern Lima',
                'description_es' => 'Recorre las tres caras de Lima: la milenaria Pachacamac, el Centro Histórico colonial y los modernos distritos de Miraflores y Barranco.',
                'price' => 100,
                'price_before' => 125,
                'duration' => 'Full Day',
                'cover_image' => 'assets/banners/Rectangle 19211.jpg',
                'gallery' => ['assets/banners/Rectangle 19211.jpg','assets/banners/Rectangle 19215.jpg','assets/banners/Rectangle 19216.jpg'],
                'badge_text' => '5 CUPOS DE 20',
                'badge_type' => 'error',
                'rating' => 4.8,
                'reviews_count' => 28,
                'is_featured' => true,
                'order' => 2,
            ],
            [
                'slug' => 'nazca-huacachina-2-dias',
                'region_id' => $regions['ica'] ?? null,
                'category_id' => $cats['aventura'] ?? null,
                'title_es' => 'Las Enigmáticas Líneas de Nazca + Oasis de Huacachina e Islas Ballestas',
                'title_en' => 'Mysterious Nazca Lines + Huacachina Oasis & Ballestas Islands',
                'description_es' => 'Dos días para descubrir los misterios del Perú: las enigmáticas Líneas de Nazca, el oasis de Huacachina y la fauna marina de Paracas.',
                'price' => 220,
                'price_before' => 250,
                'duration' => '2 Días',
                'cover_image' => 'assets/banners/Rectangle 19212.jpg',
                'gallery' => ['assets/banners/Rectangle 19212.jpg','assets/banners/Rectangle 19214.jpg','assets/banners/Rectangle 19219.jpg'],
                'badge_text' => 'MÁS RESERVADO',
                'badge_type' => 'success',
                'rating' => 4.6,
                'reviews_count' => 30,
                'is_featured' => true,
                'order' => 3,
            ],
            [
                'slug' => 'nazca-full-day',
                'region_id' => $regions['ica'] ?? null,
                'category_id' => $cats['aventura'] ?? null,
                'title_es' => 'Full day a las Líneas de Nazca',
                'title_en' => 'Full day to the Nazca Lines',
                'description_es' => 'Sobrevuela las enigmáticas Líneas de Nazca en avioneta privada y descubre uno de los mayores misterios de la humanidad.',
                'price' => 300,
                'price_before' => 350,
                'duration' => 'Full Day',
                'cover_image' => 'assets/banners/Rectangle 19214.jpg',
                'gallery' => ['assets/banners/Rectangle 19214.jpg','assets/banners/Rectangle 19219.jpg'],
                'badge_text' => 'CUPOS LIMITADOS',
                'badge_type' => 'warn',
                'rating' => 4.8,
                'reviews_count' => 28,
                'order' => 4,
            ],
            [
                'slug' => 'city-tour-lima-catacumbas',
                'region_id' => $regions['lima'] ?? null,
                'category_id' => $cats['cultural'] ?? null,
                'title_es' => 'City Tour Lima + Catacumbas y Centro Histórico',
                'title_en' => 'Lima City Tour + Catacombs and Historic Center',
                'description_es' => 'Conoce las plazas, catedrales y catacumbas del Centro Histórico de Lima, declarado Patrimonio de la Humanidad.',
                'price' => 65,
                'price_before' => 80,
                'duration' => '4 horas',
                'cover_image' => 'assets/banners/Rectangle 19215.jpg',
                'gallery' => ['assets/banners/Rectangle 19215.jpg'],
                'badge_text' => 'NUEVO TOUR',
                'badge_type' => 'success',
                'rating' => 4.7,
                'reviews_count' => 18,
                'order' => 5,
            ],
            [
                'slug' => 'machu-picchu-full-day',
                'region_id' => $regions['cusco'] ?? null,
                'category_id' => $cats['cultural'] ?? null,
                'title_es' => 'Machu Picchu Full Day + Tren Panorámico desde Cusco',
                'title_en' => 'Full Day Machu Picchu + Panoramic Train from Cusco',
                'description_es' => 'Visita la maravilla del mundo Machu Picchu en tren panorámico desde Cusco. Una experiencia única e inolvidable.',
                'price' => 420,
                'price_before' => 480,
                'duration' => 'Full Day',
                'cover_image' => 'assets/banners/Rectangle 19217.jpg',
                'gallery' => ['assets/banners/Rectangle 19217.jpg','assets/banners/Rectangle 19218.jpg'],
                'badge_text' => 'EXPERIENCIA TOP',
                'badge_type' => 'success',
                'rating' => 4.9,
                'reviews_count' => 65,
                'is_featured' => true,
                'order' => 6,
            ],
        ];

        foreach ($tours as $t) {
            Tour::updateOrCreate(['slug' => $t['slug']], $t);
        }
    }
}
