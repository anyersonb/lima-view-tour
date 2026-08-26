@props([
    'id' => 'phone_prefix',
    'name' => null,
    'selected' => null,
])

@php
    // Los 242 países viven en config/phone_codes.php. Antes eran 16 <option>
    // escritos a mano en checkout.blade.php y cualquier cliente de fuera de esa
    // lista se quedaba sin poder dejar su teléfono.
    $phoneConfig = config('phone_codes');
    $countries   = $phoneConfig['countries'] ?? [];
    $locale      = in_array(app()->getLocale(), ['es', 'en', 'pt'], true) ? app()->getLocale() : 'es';

    // El valor es el prefijo, no el ISO: es lo que se concatena al número. Como
    // varios países comparten prefijo (+1, +7, +44...), el <option> se
    // identifica por ISO en data-iso y se preselecciona por ISO, no por valor,
    // o el navegador marcaría el primero que coincida.
    $selectedIso = $selected ?: ($phoneConfig['default'] ?? 'PE');

    $featured = array_values(array_filter(
        $phoneConfig['featured'] ?? [],
        fn (string $iso): bool => isset($countries[$iso])
    ));

    // El archivo viene ordenado por el nombre en español. En inglés y portugués
    // ese orden no es alfabético, así que se reordena por el nombre del idioma
    // activo. Se compara sobre la versión sin tildes (Str::ascii no necesita
    // intl) para que "Åland" no termine después de "Zimbabue".
    uasort($countries, fn (array $a, array $b): int => strcasecmp(
        \Illuminate\Support\Str::ascii($a['names'][$locale] ?? ''),
        \Illuminate\Support\Str::ascii($b['names'][$locale] ?? '')
    ));

    $optionLabel = fn (string $iso): string =>
        $countries[$iso]['flag'].' +'.$countries[$iso]['dial'].' · '.($countries[$iso]['names'][$locale] ?? $iso);
@endphp

<select id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        aria-label="{{ __('ui.country_code') }}"
        {{ $attributes }}>
    @if ($featured !== [])
        <optgroup label="{{ __('ui.frequent_countries') }}">
            @foreach ($featured as $iso)
                <option value="+{{ $countries[$iso]['dial'] }}"
                        data-iso="{{ $iso }}"
                        @selected($iso === $selectedIso)>{{ $optionLabel($iso) }}</option>
            @endforeach
        </optgroup>
    @endif

    <optgroup label="{{ __('ui.all_countries') }}">
        @foreach ($countries as $iso => $country)
            <option value="+{{ $country['dial'] }}"
                    data-iso="{{ $iso }}"
                    @selected($iso === $selectedIso && ! in_array($iso, $featured, true))>{{ $optionLabel($iso) }}</option>
        @endforeach
    </optgroup>
</select>
