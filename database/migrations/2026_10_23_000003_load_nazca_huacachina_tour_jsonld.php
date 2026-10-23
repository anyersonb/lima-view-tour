<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Loads the ES/EN JSON-LD of the Nazca + Huacachina tour (this slug only).
 *
 * Content: exactly what the SEO audit specifies (.claude/informes/2026-10-23-tour-nazca/SEO.md,
 * decision 1) on top of the graph production already emits: the 9 real itinerary steps
 * (name = step title, description = time as shown on the sheet), image = the tour's own
 * og:image, no aggregateRating. PT is left empty on purpose: TourJsonLd falls back to ES
 * and rewrites urls/name for /pt.
 *
 * These two columns hold text we wrote ourselves, so they ARE overwritten (unlike content
 * columns). Each payload is validated with json_decode before touching the table.
 */
return new class extends Migration
{
    private const SLUG = 'las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina';

    /** @return array<string, string> */
    private function payloads(): array
    {
        return [
            'schema_jsonld_es' => <<<'JSON'
{"@context":"https://schema.org","@graph":[{"@type":"TouristTrip","name":"Tour a las Lineas de Nazca + Oasis de Huacachina con Buggy y Sandboarding","description":"Aventura desde Lima a Ica: sobrevuelo de las enigmaticas Lineas de Nazca y visita al Oasis de Huacachina con paseo en buggy y sandboarding en las dunas. Con guia y traslado.","url":"https://www.limaviewtours.com/es/tours/detalle/las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina","image":"https://www.limaviewtours.com/storage/tours/seo/01m260qgvsdt0xhhtbt346jjgy.webp","provider":{"@type":"TravelAgency","name":"Lima View Tours","url":"https://www.limaviewtours.com/es","telephone":"+51925886725"},"itinerary":{"@type":"ItemList","itemListElement":[{"@type":"ListItem","position":1,"name":"Salida desde tu hotel","description":"4:00 AM"},{"@type":"ListItem","position":2,"name":"Viaje hacia Nazca","description":"4:00 AM - 11:30 AM"},{"@type":"ListItem","position":3,"name":"Llegada a Nazca","description":"11:30 AM"},{"@type":"ListItem","position":4,"name":"Sobrevuelo de las Líneas de Nazca","description":"12:00 PM"},{"@type":"ListItem","position":5,"name":"Tiempo libre en Nazca y traslado a Huacachina","description":"12:30 PM - 4:00 PM"},{"@type":"ListItem","position":6,"name":"Llegada a Huacachina y Almuerzo","description":"4:00 PM"},{"@type":"ListItem","position":7,"name":"Actividades de Aventura en Huacachina","description":"5:00 PM - 6:00 PM"},{"@type":"ListItem","position":8,"name":"Retorno a Lima","description":"6:30 PM - 10:30 PM"},{"@type":"ListItem","position":9,"name":"Llegada a Lima","description":"10:30 PM"}]},"offers":{"@type":"Offer","url":"https://www.limaviewtours.com/es/tours/detalle/las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina","price":"400","priceCurrency":"USD","priceValidUntil":"2026-12-31","availability":"https://schema.org/InStock"}},{"@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Lima View Tours","item":"https://www.limaviewtours.com/es"},{"@type":"ListItem","position":2,"name":"Tours","item":"https://www.limaviewtours.com/es/tours"},{"@type":"ListItem","position":3,"name":"Tours en Ica","item":"https://www.limaviewtours.com/es/tours#ica"},{"@type":"ListItem","position":4,"name":"Lineas de Nazca + Oasis de Huacachina","item":"https://www.limaviewtours.com/es/tours/detalle/las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina"}]}]}
JSON,
            'schema_jsonld_en' => <<<'JSON'
{"@context":"https://schema.org","@graph":[{"@type":"TouristTrip","name":"NAZCA LINES TOUR + HUACACHINA OASIS TOUR WITH BUGGY AND SANDBOARDING","description":"Fly over the Nazca Lines and explore Huacachina with buggy and sandboarding. Adventure from Lima with guide. Book online.","url":"https://www.limaviewtours.com/en/tours/detalle/nazca-lines-huacachina-tour","image":"https://www.limaviewtours.com/storage/tours/seo/01m260qgvsdt0xhhtbt346jjgy.webp","provider":{"@type":"TravelAgency","name":"Lima View Tours","url":"https://www.limaviewtours.com/en","telephone":"+51925886725"},"itinerary":{"@type":"ItemList","itemListElement":[{"@type":"ListItem","position":1,"name":"Departure from your hotel","description":"4:00 AM"},{"@type":"ListItem","position":2,"name":"Travel to Nazca","description":"4:00 AM - 11:30 AM"},{"@type":"ListItem","position":3,"name":"Arrival in Nazca","description":"11:30 AM"},{"@type":"ListItem","position":4,"name":"Overflight of the Nazca Lines","description":"12:00 PM"},{"@type":"ListItem","position":5,"name":"Free time in Nazca and transfer to Huacachina","description":"12:30 PM - 4:00 PM"},{"@type":"ListItem","position":6,"name":"Arrival in Huacachina and Lunch","description":"4:00 PM"},{"@type":"ListItem","position":7,"name":"Adventure Activities in Huacachina","description":"5:00 PM - 6:00 PM"},{"@type":"ListItem","position":8,"name":"Return to Lima","description":"6:30 PM - 10:30 PM"},{"@type":"ListItem","position":9,"name":"Arrival in Lima","description":"10:30 PM"}]},"offers":{"@type":"Offer","url":"https://www.limaviewtours.com/en/tours/detalle/nazca-lines-huacachina-tour","price":"400","priceCurrency":"USD","priceValidUntil":"2026-12-31","availability":"https://schema.org/InStock"}},{"@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Home","item":"https://www.limaviewtours.com/en"},{"@type":"ListItem","position":2,"name":"Tours","item":"https://www.limaviewtours.com/en/tours"},{"@type":"ListItem","position":3,"name":"Tours en Ica","item":"https://www.limaviewtours.com/en/tours#ica"},{"@type":"ListItem","position":4,"name":"NAZCA LINES TOUR + HUACACHINA OASIS TOUR WITH BUGGY AND SANDBOARDING","item":"https://www.limaviewtours.com/en/tours/detalle/nazca-lines-huacachina-tour"}]}]}
JSON,
        ];
    }

    public function up(): void
    {
        $update = [];

        foreach ($this->payloads() as $column => $json) {
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
                throw new RuntimeException("nazca jsonld: invalid JSON for {$column}: ".json_last_error_msg());
            }

            $update[$column] = $json;
        }

        $tour = DB::table('tours')->where('slug', self::SLUG)->first(['id']);

        if (! $tour) {
            Log::warning('nazca jsonld: tour not found, nothing done.', ['slug' => self::SLUG]);

            return;
        }

        DB::table('tours')->where('id', $tour->id)->update($update);

        Log::info('nazca jsonld: loaded ES/EN schema.', ['tour_id' => $tour->id]);
    }

    /**
     * Intentionally a no-op: the previous value is not stored here (it was hand-written
     * production data); restore it from the pre-deploy SQL backup if needed.
     */
    public function down(): void
    {
        Log::warning('nazca jsonld down(): nothing reverted; restore schema_jsonld_es/en from the pre-deploy backup.');
    }
};
