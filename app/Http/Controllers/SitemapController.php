<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Region;
use App\Models\Tour;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $locales = config('app.supported_locales', ['es', 'en', 'pt']);
        $base = rtrim(config('app.url'), '/');

        $urls = [];

        // Static routes
        $staticRoutes = [
            ['path' => '',           'priority' => '1.0', 'changefreq' => 'daily'],
            ['path' => '/tours',     'priority' => '0.9', 'changefreq' => 'daily'],
            ['path' => '/nosotros',  'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/contacto',  'priority' => '0.6', 'changefreq' => 'monthly'],
            ['path' => '/resenas',   'priority' => '0.6', 'changefreq' => 'weekly'],
            ['path' => '/terminos',  'priority' => '0.3', 'changefreq' => 'yearly'],
            ['path' => '/privacidad','priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        foreach ($staticRoutes as $r) {
            foreach ($locales as $locale) {
                $urls[] = [
                    'loc' => $base . '/' . $locale . $r['path'],
                    'lastmod' => now()->toAtomString(),
                    'priority' => $r['priority'],
                    'changefreq' => $r['changefreq'],
                    'alternates' => collect($locales)->mapWithKeys(fn ($l) => [$l => $base . '/' . $l . $r['path']])->all(),
                ];
            }
        }

        // Region category pages
        foreach (Region::active()->get() as $region) {
            foreach ($locales as $locale) {
                $urls[] = [
                    'loc' => $base . '/' . $locale . '/tours/categoria/' . $region->slug,
                    'lastmod' => $region->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                    'alternates' => collect($locales)->mapWithKeys(fn ($l) => [$l => $base . '/' . $l . '/tours/categoria/' . $region->slug])->all(),
                ];
            }
        }

        // Tour pages
        foreach (Tour::published()->ordered()->get() as $tour) {
            foreach ($locales as $locale) {
                $urls[] = [
                    'loc' => $base . '/' . $locale . '/tours/detalle/' . $tour->slug,
                    'lastmod' => $tour->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'priority' => $tour->is_featured ? '0.9' : '0.7',
                    'changefreq' => 'weekly',
                    'image' => $tour->cover_image ? $base . '/' . ltrim($tour->cover_image, '/') : null,
                    'alternates' => collect($locales)->mapWithKeys(fn ($l) => [$l => $base . '/' . $l . '/tours/detalle/' . $tour->slug])->all(),
                ];
            }
        }

        // Blog index pages (one per locale)
        foreach ($locales as $locale) {
            $urls[] = [
                'loc'        => $base . '/' . $locale . '/blog',
                'lastmod'    => now()->toAtomString(),
                'priority'   => '0.7',
                'changefreq' => 'daily',
                'alternates' => collect($locales)->mapWithKeys(fn ($l) => [$l => $base . '/' . $l . '/blog'])->all(),
            ];
        }

        // Blog post pages
        foreach (BlogPost::published()->orderByDesc('updated_at')->get() as $post) {
            foreach ($locales as $locale) {
                $urls[] = [
                    'loc'        => $base . '/' . $locale . '/blog/' . $post->slug,
                    'lastmod'    => $post->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'priority'   => '0.6',
                    'changefreq' => 'weekly',
                    'image'      => $post->cover_image ? $base . '/storage/' . ltrim($post->cover_image, '/') : null,
                    'alternates' => collect($locales)->mapWithKeys(fn ($l) => [$l => $base . '/' . $l . '/blog/' . $post->slug])->all(),
                ];
            }
        }

        // CMS pages
        foreach (Page::published()->where('show_in_sitemap', true)->get() as $page) {
            foreach ($locales as $locale) {
                $urls[] = [
                    'loc' => $base . '/' . $locale . '/' . $page->slug,
                    'lastmod' => $page->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'priority' => $page->sitemap_priority ?: '0.5',
                    'changefreq' => $page->sitemap_changefreq ?: 'monthly',
                    'alternates' => collect($locales)->mapWithKeys(fn ($l) => [$l => $base . '/' . $l . '/' . $page->slug])->all(),
                ];
            }
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(): Response
    {
        $base = rtrim(config('app.url'), '/');
        $env = config('app.env');

        $lines = [];
        if ($env !== 'production') {
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'User-agent: *';
            $lines[] = 'Allow: /';
            $lines[] = 'Disallow: /admin';
            $lines[] = 'Disallow: /admin/';
            $lines[] = 'Disallow: /checkout';
            $lines[] = 'Disallow: /es/checkout';
            $lines[] = 'Disallow: /en/checkout';
            $lines[] = 'Disallow: /buscar';
            $lines[] = 'Disallow: /es/buscar';
            $lines[] = 'Disallow: /en/buscar';
            $lines[] = '';
            $lines[] = 'User-agent: AhrefsBot';
            $lines[] = 'Crawl-delay: 10';
            $lines[] = '';
            $lines[] = 'User-agent: SemrushBot';
            $lines[] = 'Crawl-delay: 10';
            $lines[] = '';
            $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
