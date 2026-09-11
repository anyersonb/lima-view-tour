{{--
    Único punto de salida para bloques <script type="application/ld+json">
    en todo el sitio. Sustituye los once `{!! $var !!}` crudos que
    materializaban HTML/JS embebido dentro de un string de un JSON-LD
    aparentemente válido (ver seguridad, 2026-08-31).

    Por qué no basta con validar al guardar: los settings/columnas también
    se escriben por SQL directo (deploy-*.sql) y ya hay valores guardados de
    antes de la validación nueva. El control real vive AQUÍ, en el único
    lugar donde el JSON-LD se imprime.

    Mecanismo: se decodifica el JSON guardado y se vuelve a codificar con
    JSON_HEX_TAG (además de JSON_HEX_APOS/AMP/QUOT). JSON_HEX_TAG convierte
    cada `<` y `>` en `<` / `>` — un escape válido DENTRO de la
    gramática JSON, no del HTML — así que ningún `</script>` (ni ningún
    otro tag) puede volver a materializarse en el DOM sin importar lo que
    haya en el string original. El parser del navegador ya no tiene ningún
    `<` literal que interpretar como inicio de etiqueta.

    Si el valor no es JSON válido (o llega null/vacío) no se imprime nada:
    un JSON-LD roto en el <head> es peor que no tener schema, y evita que
    basura llegada por SQL directo se cuele cruda.
--}}
@php
    $decoded = json_decode((string) ($json ?? ''));
@endphp
@if (json_last_error() === JSON_ERROR_NONE && $decoded !== null)
<script type="application/ld+json">{!! json_encode(
    $decoded,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) !!}</script>
@endif
