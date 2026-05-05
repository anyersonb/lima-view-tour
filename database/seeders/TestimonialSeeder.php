<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Sara Fernández', 'country' => 'España', 'rating' => 5, 'source' => 'Google', 'quote_es' => 'Una experiencia inolvidable. Atención impecable de principio a fin.', 'is_featured' => true, 'order' => 1],
            ['name' => 'Rebeca Figueroa', 'country' => 'Colombia', 'rating' => 5, 'source' => 'Google', 'quote_es' => 'Reservar fue muy fácil y los guías excelentes. Volveremos seguro.', 'is_featured' => true, 'order' => 2],
            ['name' => 'Liam Carter', 'country' => 'USA', 'rating' => 5, 'source' => 'Tripadvisor', 'quote_es' => 'Una agencia confiable y muy organizada. Cada parada del recorrido fue una sorpresa positiva.', 'is_featured' => true, 'order' => 3],
            ['name' => 'Ana Suárez', 'country' => 'México', 'rating' => 5, 'source' => 'Google', 'quote_es' => 'El tour superó nuestras expectativas. Recomiendo 100%.', 'is_featured' => true, 'order' => 4],
            ['name' => 'Valeriy Roberts', 'country' => 'USA', 'rating' => 5, 'source' => 'Tripadvisor', 'quote_es' => 'Logística impecable, guías apasionados y un trato cálido que hizo del viaje algo memorable.', 'is_featured' => true, 'order' => 5],
        ];

        foreach ($items as $i) {
            Testimonial::updateOrCreate(['name' => $i['name']], $i);
        }
    }
}
