<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Database\Seeder;

/**
 * Reseñas reales migradas desde el WooCommerce de producción (limaviewtours.com)
 * para el tour "Huacachina + Islas Ballestas". Idempotente: no duplica.
 */
class HuacachinaReviewsSeeder extends Seeder
{
    public function run(): void
    {
        $slug = 'tour-de-dia-completo-al-oasis-de-huacachina-islas-ballestas-en-paracas-2';
        $tour = Tour::where('slug', $slug)->first();

        if (! $tour) {
            $this->command?->warn("Tour {$slug} no encontrado; se omiten reseñas.");
            return;
        }

        $reviews = [
            ['Stephannia Pérez Aspajo', '2026-02-25 18:46:48', 'Hicimos el Full Day Paracas – Huacachina con retorno al hotel y todo salió perfecto. Desde la salida hasta el regreso nos sentimos acompañados, cuidados y con todas las comodidades.'],
            ['Verónica Preciado', '2026-02-25 18:56:27', 'Super recomendado, realizamos el tour Islas Ballestas y Huacachina, el bus muy cómodo, los guías te explican muy bien todo el recorrido, el restaurante donde almorzamos muy bonito y delicioso, los lugares que visitamos espectaculares, una experiencia increíble…. Muchas Gracias Lima View Tours!'],
            ['Renzo Sánchez', '2026-02-25 18:58:37', 'Realizamos el tour a Ica y Paracas en familia. Todo fue puntual, los traslados cómodos y el guía nos explicaba cada lugar con mucha pasión. A mis hijos les encantó el paseo en buggy por las dunas de Huacachina.'],
            ['Aldahir Olivares', '2026-02-25 19:02:24', 'Una experiencia inolvidable en Huacachina. Desde el primer momento el trato fue excelente. El equipo fue muy amable, puntual y siempre atento a cada detalle. Nos explicaron todo con paciencia y buena vibra, haciendo que el tour fuera muy divertido y seguro. El paseo en los buggies por las dunas fue increíble, y el sandboard una experiencia única. ¡Definitivamente lo recomiendo al 100%! Fue uno de los mejores tours que he hecho en Perú.'],
            ['Karina Segura', '2026-02-25 19:04:24', 'Estuvo increíble, la atención de Leonardo fue excepcional, en todo momento me ayudó, aclaró mis dudas y estuvo al pendiente. Los lugares magníficos, la comida espectacular, en definitiva lo haríamos de nuevo. 100% recomendable.'],
            ['Paola Grisales', '2026-02-25 19:07:10', 'Excelente servicio. Puntuales, muy completo el tour, el guía Ricardo explica muy bien toda la historia. Super recomendado.'],
        ];

        foreach ($reviews as [$name, $date, $text]) {
            Testimonial::updateOrCreate(
                ['tour_id' => $tour->id, 'name' => $name],
                [
                    'quote_es'    => $text,
                    'rating'      => 5.0,
                    'source'      => 'Web',
                    'is_active'   => true,
                    'is_featured' => false,
                    'created_at'  => $date,
                    'updated_at'  => $date,
                ]
            );
        }

        $this->command?->info('6 reseñas de Huacachina sembradas.');
    }
}
