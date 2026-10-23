<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fills the empty ES/EN content of the Nazca + Huacachina tour (slug below).
 *
 * Source (verbatim, whitespace/HTML cleanup only): Internet Archive captures of the
 * old WordPress sheet "Full day a las Lineas de Nazca":
 *   ES  /full-day-a-las-lineas-de-nazca/    capture 2025-04-21
 *   EN  /en/full-day-to-the-nazca-lines/    capture 2025-06-17
 * The tour's own WP post (ID 1062) had all these fields empty.
 *
 * Rules: looks the tour up by slug, writes ONLY fields that are blank, never touches
 * price, slug, reviews, gallery or PT. Idempotent. The per-person price table of the
 * old sheet ($300/$250/$200) is intentionally NOT copied: it contradicts the current price.
 */
return new class extends Migration
{
    private const SLUG = 'las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina';

    /** @return array<string, mixed> */
    private function content(): array
    {
        $step = fn (string $time, string $title, string $description): array => [
            'time' => $time,
            'title' => $title,
            'description' => $description,
        ];

        return [
            'description_es' => 'Descubre la enigmática belleza de nazca y la espectacular aventura de huacachina en este emocionante tour de dia comnpleto. Sumérgete en la historia y el misterio de las famosas líneas de nazca, y disfruta de la emoción de deslizarte por las altas dunas de arena y practicar sandboarding en el oasis de huacachina. Este tour te llevará a través de paisajes impresionantes y experiencias únicas que te dejarán maravillado.',
            'description_en' => 'Discover the enigmatic beauty of Nazca and the spectacular adventure of Huacachina on this exciting full-day tour. Immerse yourself in the history and mystery of the famous Nazca Lines, and enjoy the thrill of sliding down high sand dunes and sandboarding in the Huacachina Oasis. This tour will take you through stunning landscapes and unique experiences that will leave you in awe.',

            'itinerary_es' => [
                $step('4:00 AM', 'Salida desde tu hotel', 'Iniciaremos nuestro viaje con una salida puntual desde tu hotel . Asegúrate de estar listo para partir a las 4:00 AM.'),
                $step('4:00 AM - 11:30 AM', 'Viaje hacia Nazca', 'Durante el trayecto, realizaremos varias paradas estratégicas para desayunar, utilizar los servicios higiénicos y comprar agua, snacks u otros artículos necesarios. Disfruta de un desayuno ligero en una parada seleccionada, antes de continuar nuestro viaje hacia Nazca.'),
                $step('11:30 AM', 'Llegada a Nazca', 'A nuestra llegada a Nazca, nos dirigiremos directamente al aeródromo es aquí donde nos prepararemos para el sobrevuelo a las Líneas de Nazca.'),
                $step('12:00 PM', 'Sobrevuelo de las Líneas de Nazca', 'A las 12:00 PM, abordaremos una avioneta para realizar el sobrevuelo sobre las impresionantes y enigmáticas Líneas de Nazca. Durante aproximadamente 30 minutos, tendrás la oportunidad de ver desde el aire estas antiguas figuras geométricas y animales trazadas en el desierto.'),
                $step('12:30 PM - 4:00 PM', 'Tiempo libre en Nazca y traslado a Huacachina', 'Después del sobrevuelo, tendrás un breve tiempo libre para descansar y explorar los alrededores antes de emprender nuestro viaje hacia el Oasis de Huacachina.'),
                $step('4:00 PM', 'Llegada a Huacachina y Almuerzo', 'Alrededor de las 4:00 PM, llegaremos al Oasis de Huacachina, donde disfrutaremos de un almuerzo en un restaurante local. Esta es una excelente oportunidad para relajarte y disfrutar del hermoso paisaje del oasis.'),
                $step('5:00 PM - 6:00 PM', 'Actividades de Aventura en Huacachina', 'Después del almuerzo, realizaremos emocionantes deportes de aventura como el paseo en buggies y el sandboarding en las dunas de Huacachina. Estas actividades están diseñadas para ofrecer una experiencia llena de adrenalina y diversión.'),
                $step('6:30 PM - 10:30 PM', 'Retorno a Lima', 'Iniciaremos nuestro regreso a Lima, realizando paradas en el camino para utilizar los servicios higiénicos y estirar las piernas. Disfruta de las vistas y relájate durante el viaje de regreso.'),
                $step('10:30 PM', 'Llegada a Lima', 'Nuestra llegada a Lima está programada para las 10:30 PM. Te dejaremos en tu hotel, concluyendo así un día lleno de aventura y descubrimientos.'),
            ],
            'itinerary_en' => [
                $step('4:00 AM', 'Departure from your hotel', 'We will begin our trip with a punctual departure from your hotel. Make sure you are ready to leave at 4:00 AM.'),
                $step('4:00 AM - 11:30 AM', 'Travel to Nazca', 'During the journey, we will make several strategic stops to have breakfast, use the toilets and buy water, snacks or other necessary items. Enjoy a light breakfast at a selected stop, before continuing our journey towards Nazca.'),
                $step('11:30 AM', 'Arrival in Nazca', 'Upon arrival in Nazca, we will go directly to the airfield, this is where we will prepare for the overflight of the Nazca Lines.'),
                $step('12:00 PM', 'Overflight of the Nazca Lines', 'At 12:00 PM, we will board a small plane to fly over the impressive and enigmatic Nazca Lines. For approximately 30 minutes, you will have the opportunity to see these ancient geometric figures and animals drawn in the desert from the air.'),
                $step('12:30 PM - 4:00 PM', 'Free time in Nazca and transfer to Huacachina', 'After the flyover, you will have a short free time to rest and explore the surroundings before embarking on our journey to the Huacachina Oasis.'),
                $step('4:00 PM', 'Arrival in Huacachina and Lunch', 'Around 4:00 PM, we will arrive at the Huacachina Oasis, where we will enjoy lunch at a local restaurant. This is an excellent opportunity to relax and enjoy the beautiful scenery of the oasis.'),
                $step('5:00 PM - 6:00 PM', 'Adventure Activities in Huacachina', 'After lunch, we will do exciting adventure sports such as buggy riding and sandboarding in the dunes of Huacachina. These activities are designed to offer an experience full of adrenaline and fun.'),
                $step('6:30 PM - 10:30 PM', 'Return to Lima', 'We will begin our return to Lima, making stops along the way to use the toilets and stretch our legs. Enjoy the views and relax on the return trip.'),
                $step('10:30 PM', 'Arrival in Lima', 'Our arrival in Lima is scheduled for 10:30 PM. We will drop you off at your hotel, concluding a day full of adventure and discoveries.'),
            ],

            'includes_es' => [
                'Recojo del hotel y retorno al hotel.',
                'Tour Oasis Huacachina en Ica.',
                'Ticket de ingreso.',
                'Asistencia permanente',
                'Paseo en bugguie',
                'Aventura en sandboarding',
                'Traslado de Lima a Nazca - de Nazca a Ica y de Ica a Lima',
                'Guía de turismo en inglés y español..',
                'Traslado al aeródromo en Nazca',
            ],
            'includes_en' => [
                'Pick up from the hotel and return to the hotel.',
                'Huacachina Oasis Tour in Ica.',
                'Entrance ticket.',
                'Permanent assistance',
                'Buggy ride',
                'Sandboarding adventure',
                'Transfer from Lima to Nazca - from Nazca to Ica and from Ica to Lima',
                'Tourist guide in English and Spanish.',
                'Transfer to the airfield in Nazca',
            ],

            'excludes_es' => [
                'No incluye alimentación no mencionada',
                'No incluye tabla de sanboarding profesional (costo adicional $20 dólares por persona)',
                'No incluye sobrevuelo para las LÍneas de Nazca $100 dólares por persona',
                'No incluye impuesto aeroportuario $20 dólares por persona',
            ],
            'excludes_en' => [
                'Does not include food not mentioned',
                'Does not include professional sanboarding board (additional cost $20 per person)',
                'Does not include overflight for the Nazca Lines $100 dollars per person',
                'Does not include airport tax $20 dollars per person',
            ],

            'recommendations_es' => implode("\n", [
                'Ropa y zapatos cómodos.', 'Gafas de sol', 'Protector solar', 'Un sombrero', 'Agua', 'Sueter',
                'Ropa de cambio para el dia el nazca',
            ]),
            'recommendations_en' => implode("\n", [
                'Comfortable clothes and shoes.', 'Sunglasses', 'Sunscreen', 'A hat', 'Water', 'Sweater',
                'Change of clothes for Nazca Day',
            ]),

            // "Puntos de recojo": the tour has no dedicated column, notes_* is the free-text slot
            // (same convention as the Lima tours that carry their pickup text there).
            'notes_es' => 'Nuestro servicio de traslado incluido cubre las áreas de Miraflores, Barranco y San Isidro. Si tu hotel o alojamiento está fuera de estos distritos, también puedes dirigirte a nuestro punto de encuentro central ( centro comercial LARCOMAR frente al hotel JW Marriot',
            'notes_en' => 'Our included transfer service covers the areas of Miraflores, Barranco and San Isidro. If your hotel or accommodation is outside these districts, you can also go to our central meeting point (LARCOMAR shopping center in front of the JW Marriot hotel)',
        ];
    }

    public function up(): void
    {
        $tour = DB::table('tours')->where('slug', self::SLUG)->first();

        if (! $tour) {
            Log::warning('nazca content: tour not found, nothing done.', ['slug' => self::SLUG]);

            return;
        }

        $update = [];
        foreach ($this->content() as $column => $value) {
            if ($this->isBlank($tour->{$column} ?? null)) {
                $update[$column] = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $value;
            }
        }

        if ($update === []) {
            Log::info('nazca content: no blank field, nothing done.', ['tour_id' => $tour->id]);

            return;
        }

        DB::table('tours')->where('id', $tour->id)->update($update);

        Log::info('nazca content: filled blank fields.', ['tour_id' => $tour->id, 'fields' => array_keys($update)]);
    }

    /** Blanks only the fields that still hold exactly what up() wrote. */
    public function down(): void
    {
        $tour = DB::table('tours')->where('slug', self::SLUG)->first();

        if (! $tour) {
            return;
        }

        foreach ($this->content() as $column => $value) {
            $written = is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $value;

            if (($tour->{$column} ?? null) === $written) {
                DB::table('tours')->where('id', $tour->id)->update([$column => null]);
            }
        }
    }

    /** null, '', whitespace-only, or an empty JSON list. */
    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        // /u makes \s match NBSP (U+00A0), which the legacy import left in these columns.
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $text === '' || $text === '[]' || $text === 'null';
    }
};
