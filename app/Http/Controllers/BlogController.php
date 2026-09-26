<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

class BlogController extends Controller
{
    /**
     * Display the paginated blog index.
     * Supports optional ?categoria= filter, ordered by published_at desc.
     */
    public function index(Request $request, string $locale): View
    {
        App::setLocale($locale);

        // 2026-09-24: EN/PT ya no muestran el español cuando la nota no está
        // traducida — availableIn() filtra el listado (y las categorías más
        // abajo) a lo que realmente existe en $locale. Ver BlogPost::isAvailableIn().
        $query = BlogPost::published()
            ->availableIn($locale)
            ->orderByDesc('published_at');

        if ($request->filled('categoria')) {
            $query->where('category', $request->input('categoria'));
        }

        $posts = $query->paginate(12)->withQueryString();

        // All distinct categories for the filter sidebar / pill nav — solo de
        // notas que existen en este idioma, para no ofrecer un filtro que
        // lleve a un listado vacío o a notas sin traducir.
        $categories = BlogPost::published()
            ->availableIn($locale)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('blog.index', compact('posts', 'categories'));
    }

    /**
     * Display a single published blog post.
     * Returns 404 if the post is not found, not yet published, or not
     * translated into $locale (2026-09-24: no fallback to Spanish content
     * under a non-Spanish URL — see BlogPost::isAvailableIn()).
     */
    public function show(string $locale, string $slug): View|RedirectResponse
    {
        App::setLocale($locale);

        // Resolve by the active locale's slug column (slug_en/slug_pt) with
        // fallback to the Spanish `slug`. 301 to the translated slug when the
        // visitor hit the Spanish slug under a locale that has its own.
        $resolution = BlogPost::resolveForLocale($locale, $slug, fn ($q) => $q->published());
        $post = $resolution['model'];

        abort_if(! $post, 404);

        // La nota existe (fue resuelta por su slug ES o EN/PT) pero no tiene
        // traducción real a $locale: 404 directo, SIN redirigir a ningún
        // lado y SIN caer al contenido español bajo esta URL.
        abort_if(! $post->isAvailableIn($locale), 404);

        if ($resolution['redirect_slug']) {
            return redirect()->route('blog.show', [
                'locale' => $locale,
                'slug' => $resolution['redirect_slug'],
            ], 301);
        }

        // Related posts: same category first, then recent — max 3.
        // availableIn($locale) evita recomendar una nota que en este idioma
        // daría 404.
        $related = BlogPost::published()
            ->availableIn($locale)
            ->where('id', '!=', $post->id)
            ->when($post->category, fn ($q) => $q->orderByRaw(
                'CASE WHEN category = ? THEN 0 ELSE 1 END',
                [$post->category]
            ))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('blog.show', compact('post', 'related'));
    }
}
