{{-- Tarjeta de reseña de la ficha de tour (datos reales: testimonio aprobado + activo). --}}
@php
    $revName  = trim((string) ($rev->name ?? '')) ?: 'Viajero';
    $parts    = preg_split('/\s+/u', $revName);
    $revInit  = mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8') . (count($parts) > 1 ? mb_substr(end($parts), 0, 1, 'UTF-8') : ''), 'UTF-8');
    $revStars = max(0, min(5, (int) round((float) $rev->rating)));
    $revMeta  = array_filter([trim((string) ($rev->country ?? '')), $rev->displayDate?->translatedFormat('F Y')]);
@endphp
<article class="fi-review">
    <div class="fi-review__head">
        <div class="fi-avatar" aria-hidden="true">{{ $revInit }}</div>
        <div>
            <strong>{{ $revName }}</strong>
            <small>{{ $revMeta ? implode(' · ', $revMeta) : __('ficha.reviews_published_in') }}</small>
        </div>
        <span class="fi-stars" role="img" aria-label="{{ __('ficha.rating_stars', ['rating' => $revStars]) }}">{{ str_repeat('★', $revStars) . str_repeat('☆', 5 - $revStars) }}</span>
    </div>
    @if ($rev->title)<p class="fi-review__text" style="margin-top:10px"><b>{{ $rev->title }}</b></p>@endif
    @if ($rev->quote)<p class="fi-review__text">“{{ $rev->quote }}”</p>@endif
</article>
