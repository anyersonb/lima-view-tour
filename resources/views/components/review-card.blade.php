@props([
    'review',            // App\Models\Testimonial (published, de este tour)
    'colorIndex' => 0,   // índice de posición en la lista, para variar el color del avatar de iniciales
    'variant' => 'd',    // 'm' | 'd' — determina el fondo de la tarjeta según el fondo real de cada árbol
])

@php
    \Illuminate\Support\Carbon::setLocale(app()->getLocale());

    // ->avatar es la ruta cruda de la BD (columna), no una URL resuelta — a
    // diferencia de ->photoUrls, que Testimonial ya expone resuelto. Hay que
    // pasarla por ImagePath::url() igual que hace Tour::gallery_urls/
    // Testimonial::getPhotoUrlsAttribute(), o el <img> sale con una URL
    // relativa rota (se vio en pruebas: resolvía contra la URL de la página).
    $avatarUrl = \App\Support\ImagePath::url($review->avatar ?? null);

    $cardBg = $variant === 'm' ? 'bg-white' : 'bg-cream-100';

    $name = trim((string) ($review->name ?? '')) ?: 'Viajero';

    $initials = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    if (str_contains($name, ' ')) {
        $parts = explode(' ', $name);
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8'), 'UTF-8');
    }

    $avatarColors = ['bg-teal-700', 'bg-orange-500', 'bg-teal-600', 'bg-amber-600', 'bg-cyan-700'];
    $avatarColor = $avatarColors[$colorIndex % count($avatarColors)];

    // Bandera SOLO con coincidencia exacta de texto libre (trim + case-insensitive).
    // Sin match => se muestra el país sin bandera: una bandera equivocada junto
    // al nombre de una persona real es peor que ninguna.
    $country = trim((string) ($review->country ?? ''));
    $flags = [
        'espana' => '🇪🇸', 'spain' => '🇪🇸',
        'colombia' => '🇨🇴',
        'mexico' => '🇲🇽',
        'usa' => '🇺🇸', 'estados unidos' => '🇺🇸', 'united states' => '🇺🇸', 'eeuu' => '🇺🇸',
        'peru' => '🇵🇪',
        'argentina' => '🇦🇷',
        'chile' => '🇨🇱',
        'brasil' => '🇧🇷', 'brazil' => '🇧🇷',
        'francia' => '🇫🇷', 'france' => '🇫🇷',
        'alemania' => '🇩🇪', 'germany' => '🇩🇪',
        'reino unido' => '🇬🇧', 'united kingdom' => '🇬🇧', 'uk' => '🇬🇧',
        'canada' => '🇨🇦',
        'italia' => '🇮🇹', 'italy' => '🇮🇹',
        'ecuador' => '🇪🇨',
        'bolivia' => '🇧🇴',
        'uruguay' => '🇺🇾',
        'paraguay' => '🇵🇾',
        'venezuela' => '🇻🇪',
        'panama' => '🇵🇦',
        'costa rica' => '🇨🇷',
        'australia' => '🇦🇺',
        'japon' => '🇯🇵', 'japan' => '🇯🇵',
        'paises bajos' => '🇳🇱', 'netherlands' => '🇳🇱', 'holanda' => '🇳🇱',
        'portugal' => '🇵🇹',
        'suiza' => '🇨🇭', 'switzerland' => '🇨🇭',
    ];
    $countryKey = \Illuminate\Support\Str::of($country)->lower()->ascii()->trim()->toString();
    $flag = $flags[$countryKey] ?? null;

    $rating = (float) $review->rating;
    $fullStars = (int) floor($rating);
    $hasHalfStar = round($rating - $fullStars, 2) > 0;
    $emptyStars = max(0, 5 - $fullStars - ($hasHalfStar ? 1 : 0));
    $starPath = 'M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z';

    $date = $review->displayDate?->diffForHumans();

    $travelerLabels = [
        'solo'     => __('ui.traveler_type_solo'),
        'pareja'   => __('ui.traveler_type_pareja'),
        'familia'  => __('ui.traveler_type_familia'),
        'amigos'   => __('ui.traveler_type_amigos'),
        'negocios' => __('ui.traveler_type_negocios'),
    ];
    $travelerLabel = $review->traveler_type ? ($travelerLabels[$review->traveler_type] ?? null) : null;

    $photos = $review->photoUrls ?? [];
    $maxThumbs = 4;
    $photoShown = array_slice($photos, 0, $maxThumbs);
    $photoExtra = count($photos) - count($photoShown);
@endphp

<article class="{{ $cardBg }} rounded-2xl ring-1 ring-teal-800/10 p-4 sm:p-5">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $name }}" loading="lazy" width="44" height="44"
                     class="w-11 h-11 rounded-full object-cover shrink-0">
            @else
                <div class="w-11 h-11 rounded-full {{ $avatarColor }} text-white grid place-items-center text-sm font-bold shrink-0" aria-hidden="true">{{ $initials }}</div>
            @endif
            <div class="min-w-0">
                <p class="text-sm font-bold text-teal-800 flex items-center gap-1.5 truncate">
                    {{ $name }}
                    <span class="inline-flex items-center gap-0.5 bg-state-success/10 text-state-success text-[9px] font-semibold px-1.5 py-0.5 rounded-full shrink-0" aria-label="{{ __('ui.verified') }}">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    </span>
                </p>
                @if ($country)
                    <p class="text-xs text-teal-800/55 flex items-center gap-1 mt-0.5">
                        @if ($flag)<span aria-hidden="true">{{ $flag }}</span>@endif
                        <span class="truncate">{{ $country }}</span>
                    </p>
                @endif
                @if ($travelerLabel)
                    <p class="text-xs text-teal-800/55 flex items-center gap-1 mt-0.5">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        {{ $travelerLabel }}
                    </p>
                @endif
            </div>
        </div>
        <div class="text-right shrink-0">
            <div class="flex gap-0.5 justify-end" aria-label="{{ __('ui.rated_with') }} {{ number_format($rating, 1) }} {{ __('ui.of_5') }}">
                @for ($s = 0; $s < $fullStars; $s++)
                    <svg class="w-3 h-3 text-orange-400 fill-current" viewBox="0 0 20 20"><path d="{{ $starPath }}"/></svg>
                @endfor
                @if ($hasHalfStar)
                    <span class="relative inline-block w-3 h-3">
                        <svg class="absolute inset-0 w-3 h-3 text-orange-200 fill-current" viewBox="0 0 20 20"><path d="{{ $starPath }}"/></svg>
                        <span class="absolute inset-0 overflow-hidden" style="width:50%">
                            <svg class="w-3 h-3 text-orange-400 fill-current" viewBox="0 0 20 20"><path d="{{ $starPath }}"/></svg>
                        </span>
                    </span>
                @endif
                @for ($s = 0; $s < $emptyStars; $s++)
                    <svg class="w-3 h-3 text-orange-200 fill-current" viewBox="0 0 20 20"><path d="{{ $starPath }}"/></svg>
                @endfor
            </div>
            @if ($date)
                <p class="text-[11px] text-teal-800/45 mt-1 whitespace-nowrap">{{ $date }}</p>
            @endif
        </div>
    </div>

    @if ($review->title)
        <p class="mt-3 text-sm font-bold text-teal-800">{{ $review->title }}</p>
    @endif
    @if ($review->quote)
        <p class="{{ $review->title ? 'mt-1' : 'mt-3' }} text-sm text-teal-800/75 leading-relaxed whitespace-pre-line">{{ $review->quote }}</p>
    @endif

    @if (count($photoShown) > 0)
        <div class="mt-3 flex gap-2">
            @foreach ($photoShown as $photoUrl)
                <div class="relative w-14 h-14 rounded-lg overflow-hidden shrink-0 bg-cream-100">
                    <img src="{{ $photoUrl }}" alt="" loading="lazy" width="56" height="56" class="w-full h-full object-cover">
                    @if ($loop->last && $photoExtra > 0)
                        <span class="absolute inset-0 bg-teal-950/55 text-white text-xs font-bold grid place-items-center">+{{ $photoExtra }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</article>
