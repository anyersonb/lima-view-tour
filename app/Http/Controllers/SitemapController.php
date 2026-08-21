<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Region;
use App\Models\Tour;
use App\Support\LocalizedPages;
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
        ];

        // Páginas institucionales: desde la propuesta ESPASEO su path cambia por
        // idioma (/es/nosotros, /en/about-us, /pt/sobre-nos), así que no pueden
        // ir en $staticRoutes, que asume el mismo path para los 3 idiomas.
        $institutional = [
            'about'         => ['priority' => '0.7', 'changefreq' => 'monthly'],
            'contact'       => ['priority' => '0.6', 'changefreq' => 'monthly'],
            'reviews'       => ['priority' => '0.6', 'changefreq' => 'weekly'],
            'legal.terms'   => ['priority' => '0.3', 'changefreq' => 'yearly'],
            'legal.privacy' => ['priority' => '0.3', 'changefreq' => 'yearly'],
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

        foreach ($institutional as $key => $meta) {
            $alternates = collect($locales)
                ->mapWithKeys(fn ($l) => [$l => $base . LocalizedPages::path($key, $l)])
                ->all();

            foreach ($locales as $locale) {
                $urls[] = [
                    'loc' => $base . LocalizedPages::path($key, $locale),
                    'lastmod' => now()->toAtomString(),
                    'priority' => $meta['priority'],
                    'changefreq' => $meta['changefreq'],
                    'alternates' => $alternates,
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
        // Se excluyen las institucionales: ya salieron arriba con su path por
        // idioma. Si se dejaran pasar, el sitemap publicaría /en/nosotros y
        // /pt/nosotros (el slug español bajo otro idioma), que desde este lote
        // responden 301 — y un sitemap con URLs que redirigen es exactamente lo
        // que vino a corregir la propuesta. El deduplicado por 'loc' de abajo no
        // alcanza: son URLs distintas, no repetidas.
        $institutionalSlugs = collect(array_keys($institutional))
            ->map(fn ($key) => LocalizedPages::slugFor($key, LocalizedPages::BASE_LOCALE))
            ->all();

        foreach (Page::published()->where('show_in_sitemap', true)->whereNotIn('slug', $institutionalSlugs)->get() as $page) {
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

        // Deduplicado por URL (2026-07-26). /nosotros y /contacto estaban dos
        // veces: una como ruta estática y otra como Page del CMS con
        // show_in_sitemap. Se conserva la primera aparición, que es la estática
        // y trae mejor priority/changefreq. No se quitan de $staticRoutes por si
        // el editor despublica la Page desde el panel.
        $urls = collect($urls)->unique('loc')->values()->all();

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

            // ── Bots de IA / agentes: bienvenidos al contenido público ──
            // (agentic browsing + visibilidad en asistentes). Se mantiene el
            // bloqueo de /admin y /checkout. Cada agente respeta su propio
            // User-agent, por eso se listan explícitamente.
            $aiBots = [
                'GPTBot', 'ChatGPT-User', 'OAI-SearchBot',   // OpenAI
                'ClaudeBot', 'Claude-Web', 'anthropic-ai',    // Anthropic
                'PerplexityBot', 'Perplexity-User',           // Perplexity
                'Google-Extended',                            // Gemini/Vertex
                'Applebot-Extended',                          // Apple Intelligence
                'CCBot',                                      // Common Crawl
                'Bytespider', 'Amazonbot', 'Meta-ExternalAgent',
            ];
            foreach ($aiBots as $bot) {
                $lines[] = 'User-agent: ' . $bot;
                $lines[] = 'Allow: /';
                $lines[] = 'Disallow: /admin';
                $lines[] = 'Disallow: /admin/';
                $lines[] = 'Disallow: /checkout';
                $lines[] = 'Disallow: /es/checkout';
                $lines[] = 'Disallow: /en/checkout';
                $lines[] = '';
            }

            $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
            $lines[] = '';
            $lines[] = '# LLM-friendly site summary: ' . $base . '/llms.txt';
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    /**
     * /llms.txt — resumen del sitio legible por LLMs/agentes (formato
     * llmstxt.org, Markdown). Se genera dinámicamente desde los tours
     * publicados para mantenerse siempre actualizado.
     */
    public function llms(): Response
    {
        $base = rtrim(config('app.url'), '/');
        $phone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';
        $email = \App\Models\Setting::get('contact_email') ?: 'info@limaviewtours.com';
        $wa = preg_replace('/[^0-9]/', '', $phone);

        $L = [];
        $L[] = '# Lima View Tours';
        $L[] = '';
        $L[] = '> Agencia de turismo en Perú especializada en experiencias premium en Lima, Ica y Cusco: city tours, Machu Picchu, Huacachina, Islas Ballestas, Líneas de Nazca, Laguna Humantay, Montaña de 7 Colores y más. Reserva online con cancelación gratuita y guías bilingües (español/inglés).';
        $L[] = '';
        $L[] = 'Idiomas del sitio: Español (' . $base . '/es), English (' . $base . '/en), Português (' . $base . '/pt).';
        $L[] = '';

        $L[] = '## Tours';
        foreach (Tour::published()->ordered()->get() as $tour) {
            $url = $base . '/es/tours/detalle/' . $tour->slug;
            $price = $tour->price ? ('US$' . number_format((float) $tour->price, 0)) : null;
            $dur = $tour->duration ? (', ' . $tour->duration) : '';
            $desc = trim((string) ($tour->subtitle_es ?: strip_tags((string) $tour->description_es)));
            $desc = $desc !== '' ? \Illuminate\Support\Str::limit($desc, 140) : 'Tour en Perú';
            $suffix = $price ? (' — desde ' . $price . ' por persona' . $dur) : $dur;
            $L[] = '- [' . $tour->title . '](' . $url . '): ' . $desc . $suffix;
        }
        $L[] = '';

        $L[] = '## Páginas';
        $L[] = '- [Todos los tours](' . $base . '/es/tours): catálogo completo con filtros por región.';
        $L[] = '- [Nosotros](' . $base . '/es/nosotros): quiénes somos y por qué reservar con nosotros.';
        $L[] = '- [Reseñas](' . $base . '/es/resenas): opiniones verificadas de viajeros (Google, TripAdvisor y web).';
        $L[] = '- [Blog](' . $base . '/es/blog): guías de viaje y consejos sobre Perú.';
        $L[] = '- [Contacto](' . $base . '/es/contacto): WhatsApp ' . $phone . ' · Email ' . $email . '.';
        $L[] = '';

        $L[] = '## Reservar / contactar';
        $L[] = '- WhatsApp: https://wa.me/' . $wa;
        $L[] = '- Email: ' . $email;
        $L[] = '- Reserva online directa desde la página de cada tour (botón "Reservar ahora").';
        $L[] = '';

        $L[] = '## Recursos';
        $L[] = '- [Sitemap XML](' . $base . '/sitemap.xml)';

        return response(implode("\n", $L) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
