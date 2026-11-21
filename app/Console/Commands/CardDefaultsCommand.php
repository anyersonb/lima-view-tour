<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Support\TourFlags;
use App\Support\TourListing;
use Illuminate\Console\Command;

/**
 * Propone y carga, SOLO donde estén vacíos, los campos de la tarjeta del home
 * (card_title_{es,en,pt} y card_chips_{es,en,pt}) a partir de datos REALES del
 * tour publicado: título, duración, idiomas, recojo y modalidad.
 *
 * - Idempotente: un campo que ya tiene valor (lo cargó el admin) NUNCA se pisa.
 * - Nada inventado: si un dato no existe en el tour no se pone; si no se puede
 *   acortar el título con seguridad, se deja vacío y se avisa.
 * - --dry-run muestra lo que haría sin escribir nada.
 *
 *   php artisan lvt:card-defaults --dry-run
 *   php artisan lvt:card-defaults
 */
class CardDefaultsCommand extends Command
{
    protected $signature = 'lvt:card-defaults {--dry-run : Solo muestra la propuesta, no escribe en la BD}';

    protected $description = 'Rellena (solo si están vacíos) título corto y chips de la tarjeta del home con datos reales de cada tour publicado';

    private const LOCALES = ['es', 'en', 'pt'];

    private const TITLE_MAX = 28;

    /**
     * Títulos cortos derivados del título REAL de cada tour (se reconocen por
     * palabras clave del título en español). Cada entrada: short[es,en,pt] y
     * accent[es,en,pt] = palabra destacada que aparece en el propio título/itinerario.
     * Sin coincidencia: título propio si ya mide <= TITLE_MAX; si no, queda vacío.
     */
    private function catalogue(): array
    {
        return [
            ['re' => '/machu\s*picchu/', 'short' => ['Machu Picchu Full Day', 'Machu Picchu Full Day', 'Machu Picchu Full Day'],
                'accent' => ['Tren', 'Train', 'Trem']],
            ['re' => '/nazca(?=.*(huacachina|ballestas))/', 'short' => ['Nazca + Huacachina', 'Nazca + Huacachina', 'Nazca + Huacachina'],
                'accent' => ['Ballestas', 'Ballestas', 'Ballestas']],
            ['re' => '/nazca/', 'short' => ['Líneas de Nazca', 'Nazca Lines', 'Linhas de Nazca'], 'accent' => null],
            ['re' => '/catacumbas/', 'short' => ['City Tour Lima + Catacumbas', 'City Tour Lima + Catacombs', 'City Tour Lima + Catacumbas'],
                'accent' => ['Catacumbas', 'Catacombs', 'Catacumbas']],
            ['re' => '/(huacachina|paracas|ballestas)/', 'short' => ['Paracas + Huacachina', 'Paracas + Huacachina', 'Paracas + Huacachina'],
                'accent' => ['Ballestas', 'Ballestas', 'Ballestas']],
            ['re' => '/^(full day )?lima\b/', 'short' => ['Full Day en Lima', 'Full Day in Lima', 'Full Day em Lima'], 'accent' => null],
        ];
    }

    public function handle(): int
    {
        if (! TourFlags::has('card_title_es') || ! TourFlags::has('card_chips_es')) {
            $this->error('Faltan las columnas card_* en tours. Aplica antes la migración 2026_11_21_000001_add_card_fields_to_tours_table.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'MODO PRUEBA (--dry-run): no se escribe nada.' : 'Aplicando: solo se rellenan campos vacíos.');

        $rows = [];
        $changed = 0;

        foreach (Tour::published()->ordered()->get() as $tour) {
            $dirty = [];
            $entry = $this->match($tour);

            foreach (self::LOCALES as $i => $l) {
                // Título corto
                $current = trim((string) $tour->getAttribute("card_title_{$l}"));
                $proposal = $this->shortTitle($tour, $entry, $i, $l);
                if ($current !== '') {
                    $rows[] = [$tour->id, "card_title_{$l}", 'YA TIENE', $current];
                } elseif ($proposal === null) {
                    $rows[] = [$tour->id, "card_title_{$l}", 'SIN PROPUESTA', '(no se puede acortar con seguridad)'];
                } else {
                    $dirty["card_title_{$l}"] = $proposal;
                    $rows[] = [$tour->id, "card_title_{$l}", 'RELLENA', $proposal];
                }

                // Chips
                $existing = $tour->cardChips($l);
                $chips = $this->chips($tour, $entry, $i, $l);
                if ($existing !== []) {
                    $rows[] = [$tour->id, "card_chips_{$l}", 'YA TIENE', implode(' · ', array_column($existing, 'text'))];
                } elseif ($chips === []) {
                    $rows[] = [$tour->id, "card_chips_{$l}", 'SIN PROPUESTA', '(el tour no trae datos)'];
                } else {
                    $dirty["card_chips_{$l}"] = $chips;
                    $rows[] = [$tour->id, "card_chips_{$l}", 'RELLENA', implode(' · ', array_map(
                        static fn (array $c): string => $c['accent'] ? '['.$c['text'].']' : $c['text'],
                        $chips
                    ))];
                }
            }

            if ($dirty !== []) {
                $changed++;
                if (! $dry) {
                    $tour->forceFill($dirty)->saveQuietly();
                }
            }
        }

        $this->table(['Tour', 'Campo', 'Acción', 'Valor'], $rows);
        $this->info(($dry ? 'Se rellenarían' : 'Rellenados').' '.$changed.' tour(s). Entre corchetes = chip destacado (naranja).');

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|null */
    private function match(Tour $tour): ?array
    {
        $t = TourListing::ascii((string) $tour->title_es);
        foreach ($this->catalogue() as $e) {
            if (preg_match($e['re'], $t)) {
                return $e;
            }
        }

        return null;
    }

    private function shortTitle(Tour $tour, ?array $entry, int $i, string $l): ?string
    {
        if ($entry !== null) {
            return $entry['short'][$i];
        }
        $own = trim((string) ($l === 'es' ? $tour->title_es : ($tour->{"title_{$l}"} ?: '')));

        return $own !== '' && mb_strlen($own) <= self::TITLE_MAX ? $own : null;
    }

    /** @return array<int, array{text: string, accent: bool}> */
    private function chips(Tour $tour, ?array $entry, int $i, string $l): array
    {
        $chips = [];

        if ($d = TourListing::durationLabel($tour->duration, $l)) {
            // "Día completo" no cabe en el chip de ~75 px; el diseño usa "Full day" (y la duración real del tour es "Full Day").
            if ($d === __('ui.duration_full_day', [], $l)) { $d = 'Full day'; }
            $chips[] = ['text' => $d, 'accent' => false];
        }
        if ($tour->pickup_included) {
            $chips[] = ['text' => $l === 'es' ? 'Recojo' : 'Pickup', 'accent' => false];
        }
        $codes = array_map('strtoupper', TourListing::languageCodes($tour->language));
        if ($codes !== []) {
            $chips[] = ['text' => implode(' / ', $codes), 'accent' => false];
        }

        // Destacado real: palabra clave del propio tour; si no hay, su modalidad (Grupal / Privado).
        $accent = $entry['accent'][$i] ?? null;
        if ($accent === null && ($m = TourListing::modality($tour->group_type, $l))) {
            // Etiqueta corta (cabe en el chip de ~78 px): Grupal / Group / Grupo, Privado / Private / Privado.
            $short = ['group' => ['Grupal', 'Group', 'Grupo'], 'private' => ['Privado', 'Private', 'Privado']];
            $accent = $short[$m['slug']][$i] ?? $m['label'];
        }
        if ($accent !== null) {
            $chips[] = ['text' => $accent, 'accent' => true];
        }

        return array_slice($chips, 0, 4);
    }
}
