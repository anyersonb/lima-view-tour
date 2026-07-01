@props([
    'uniq' => '217',
    'locationId' => '28007820',
])
@php
    // El widget CDS de TripAdvisor inyecta su hoja de estilos (styleguide.css)
    // en el <head> del documento anfitrión, y esa hoja trae un `.hidden`
    // global que pisa utilidades de Tailwind (rompía `hidden md:block` en el
    // detalle de tour). Para aislarlo por completo lo montamos dentro de un
    // iframe `srcdoc`: el CSS de TripAdvisor cae en el <head> del iframe, no
    // en la página. El iframe hereda la CSP del documento (jscache/tacdn ya
    // están permitidos en SecurityHeaders).
    $doc = '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<base target="_blank">'
        . '<style>html,body{margin:0;padding:0;background:transparent;overflow:hidden}</style>'
        . '</head><body>'
        . '<div id="TA_cdswritereviewlgvi' . $uniq . '" class="TA_cdswritereviewlgvi">'
        . '<ul id="TA_links' . $uniq . '" class="TA_links"><li>'
        . '<a href="https://www.tripadvisor.com/UserReviewEdit-d' . $locationId . '">'
        . '<img src="https://static.tacdn.com/img2/brand_refresh/Tripadvisor_lockup_horizontal_secondary_registered.svg" alt="TripAdvisor" style="max-width:200px;height:auto"></a>'
        . '</li></ul></div>'
        . '<script async src="https://www.jscache.com/wejs?wtype=cdswritereviewlgvi&uniq=' . $uniq . '&locationId=' . $locationId . '&lang=en_US&display_version=2" data-loadtrk></' . 'script>'
        . '</body></html>';
@endphp
<iframe
    srcdoc="{{ $doc }}"
    title="Escribir reseña en TripAdvisor"
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade"
    {{ $attributes->merge(['class' => 'ta-write-review', 'style' => 'width:320px;max-width:100%;height:260px;border:0;overflow:hidden;display:block']) }}></iframe>
