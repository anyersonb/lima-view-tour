<?php

namespace App\Support;

use App\Models\Tour;
use Illuminate\Support\Str;

/**
 * JSON-LD of the tour page, localised to the language of the page (S-07).
 *
 * The editor writes the structured data by hand in the panel (one field per
 * language: schema_jsonld_es / _en / _pt). Automatic emitters stay OFF (Lote B):
 * with every field empty the page prints no block at all.
 *
 * Rules:
 *  - The field of the page language, when filled, is printed as written.
 *  - Otherwise the Spanish field is the fallback, but it is not printed as is:
 *    a copy is localised so the schema does not contradict the visible page.
 *    url/@id/item point to the page's own URL (same as <link rel=canonical>),
 *    name/description use the translation of the tour when one exists, and the
 *    breadcrumb labels follow the page language.
 *  - Anything without a translation keeps the Spanish text (clean fallback).
 *
 * The returned string goes through <x-schema-raw>, which re-encodes it safely.
 */
final class TourJsonLd
{
    private const IN_LANGUAGE = ['es' => 'es-PE', 'en' => 'en-US', 'pt' => 'pt-BR'];

    public static function for(Tour $tour, ?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();

        return self::withFaqPage(self::custom($tour, $locale), $tour, $locale);
    }

    /**
     * FAQPage built from the FAQs the page shows (Tour::localized('faqs'), same
     * Spanish fallback and same "complete pairs only" filter as the Blade view).
     * Null when the page shows none, so nothing is emitted without visible FAQs.
     *
     * @return array<string, mixed>|null
     */
    public static function faqPage(Tour $tour, ?string $locale = null): ?array
    {
        $locale = $locale ?: app()->getLocale();

        $entities = [];
        foreach ((array) ($tour->localized('faqs', $locale) ?: []) as $faq) {
            $q = is_array($faq) ? trim((string) ($faq['question'] ?? '')) : '';
            $a = is_array($faq) ? trim((string) ($faq['answer'] ?? '')) : '';

            if ($q === '' || $a === '') {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return ['@type' => 'FAQPage', 'mainEntity' => $entities];
    }

    /** Adds the FAQPage to the editor's JSON-LD (as @graph member) or emits it alone. */
    private static function withFaqPage(?string $custom, Tour $tour, string $locale): ?string
    {
        $faq = self::faqPage($tour, $locale);
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ($faq === null) {
            return $custom;
        }

        if ($custom === null) {
            return json_encode(['@context' => 'https://schema.org'] + $faq, $flags);
        }

        $data = json_decode($custom, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            return $custom;
        }

        $nodes = isset($data['@graph']) && is_array($data['@graph'])
            ? $data['@graph']
            : [array_diff_key($data, ['@context' => 1])];

        // The editor already wrote a FAQPage by hand: do not duplicate it.
        foreach ($nodes as $node) {
            if (is_array($node) && in_array('FAQPage', (array) ($node['@type'] ?? []), true)) {
                return $custom;
            }
        }

        $nodes[] = $faq;

        return json_encode(
            ['@context' => $data['@context'] ?? 'https://schema.org', '@graph' => array_values($nodes)],
            $flags
        );
    }

    private static function custom(Tour $tour, string $locale): ?string
    {

        $own = self::filled($tour->{"schema_jsonld_{$locale}"} ?? null);
        if ($own !== null) {
            return $own;
        }

        $fallback = self::filled($tour->schema_jsonld_es ?? null);
        if ($fallback === null) {
            return null;
        }

        if ($locale === 'es') {
            return $fallback;
        }

        $data = json_decode($fallback, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            // Not our job to fix invalid JSON: <x-schema-raw> prints nothing for it.
            return $fallback;
        }

        $data = self::localise($data, $tour, $locale);

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Canonical URL of the tour page in the given locale. */
    public static function pageUrl(Tour $tour, string $locale): string
    {
        return url('/'.$locale.'/tours/detalle/'.$tour->slugFor($locale));
    }

    private static function filled(mixed $value): ?string
    {
        return ($value !== null && trim((string) $value) !== '') ? (string) $value : null;
    }

    private static function localise(array $data, Tour $tour, string $locale): array
    {
        $ctx = [
            'locale' => $locale,
            'page' => self::pageUrl($tour, $locale),
            'esSlugPage' => rtrim(url('/'), '/').'/'.$locale.'/tours/detalle/'.$tour->slug,
            'base' => rtrim(url('/'), '/'),
            'name' => self::filled($tour->{"title_{$locale}"} ?? null),
            'description' => self::description($tour, $locale),
        ];

        return self::walk($data, $ctx);
    }

    private static function description(Tour $tour, string $locale): ?string
    {
        $meta = self::filled($tour->{"meta_description_{$locale}"} ?? null);
        if ($meta !== null) {
            return $meta;
        }

        $body = self::filled($tour->{"description_{$locale}"} ?? null);

        return $body !== null ? Str::limit(trim(strip_tags($body)), 300) : null;
    }

    /** @param  array<string, mixed>  $ctx */
    private static function walk(array $node, array $ctx): array
    {
        // List (e.g. @graph, itemListElement): recurse item by item.
        if (array_is_list($node)) {
            return array_map(fn ($item) => is_array($item) ? self::walk($item, $ctx) : $item, $node);
        }

        $types = (array) ($node['@type'] ?? []);

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = self::walk($value, $ctx);
            } elseif (is_string($value)) {
                $node[$key] = self::rewriteUrl($value, $ctx);
            }
        }

        if (array_intersect($types, ['TouristTrip', 'Product', 'TouristAttraction', 'Trip'])) {
            if ($ctx['name'] !== null && isset($node['name'])) {
                $node['name'] = $ctx['name'];
            }
            if ($ctx['description'] !== null && isset($node['description'])) {
                $node['description'] = $ctx['description'];
            }
            if (isset($node['url'])) {
                $node['url'] = $ctx['page'];
            }
            if (isset($node['inLanguage'])) {
                $node['inLanguage'] = self::IN_LANGUAGE[$ctx['locale']] ?? $ctx['locale'];
            }
        }

        if (in_array('ListItem', $types, true)) {
            $node = self::localiseListItem($node, $ctx);
        }

        return $node;
    }

    /**
     * Spanish site URLs -> the same URL in the page language. Host-agnostic on
     * purpose (www / apex / http written by hand in the panel): the result is
     * always the site's own canonical host.
     */
    private static function rewriteUrl(string $value, array $ctx): string
    {
        if (! preg_match('~^https?://([^/?#]+)(/es)(?=$|[/?#])(.*)$~i', $value, $m)) {
            return $value;
        }

        $strip = static fn (string $h): string => preg_replace('~^www\.~i', '', strtolower($h));
        $appHost = (string) parse_url($ctx['base'], PHP_URL_HOST);

        if ($strip($m[1]) !== $strip($appHost)) {
            return $value;
        }

        $rewritten = $ctx['base'].'/'.$ctx['locale'].$m[3];

        // The Spanish slug under /en or /pt is a 301: point at the real URL.
        return $rewritten === $ctx['esSlugPage'] ? $ctx['page'] : $rewritten;
    }

    private static function localiseListItem(array $node, array $ctx): array
    {
        $item = $node['item'] ?? null;
        $url = is_array($item) ? ($item['@id'] ?? $item['url'] ?? null) : (is_string($item) ? $item : null);
        if ($url === null) {
            return $node;
        }

        $label = null;
        $home = $ctx['base'].'/'.$ctx['locale'];

        if ($url === $ctx['page']) {
            $label = $ctx['name'];
        } elseif (rtrim($url, '/') === $home) {
            $label = __('ui.home', [], $ctx['locale']);
        } elseif (rtrim($url, '/') === $home.'/tours') {
            $label = 'Tours';
        }

        if ($label !== null && $label !== '') {
            $node['name'] = $label;
            if (is_array($node['item'] ?? null) && isset($node['item']['name'])) {
                $node['item']['name'] = $label;
            }
        }

        return $node;
    }
}
