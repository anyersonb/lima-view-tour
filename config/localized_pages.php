<?php

/**
 * URL por idioma de las páginas institucionales (propuesta ESPASEO v3,
 * 16-ago-2026, sección 4 y 5).
 *
 * A diferencia de Tours / Blog / Páginas del CMS —donde el slug traducido vive
 * en la BD (columnas slug_en / slug_pt editables desde el panel)— estas cinco
 * páginas tienen su path FIJO en routes/web.php: no son contenido que el
 * editor cree o borre, son secciones del sitio. Su slug por idioma se declara
 * acá, en código, que es justo lo que pidió ESPASEO para "Nosotros"
 * ("requiere campo de slug por idioma en el código").
 *
 * POR QUÉ NO SE LEE DE LA BD: el patrón de estas rutas se arma al registrar las
 * rutas. Si el patrón saliera de una consulta, `php artisan route:cache`
 * (que producción sí usa) congelaría los slugs del día del deploy y cambiarlos
 * en el panel devolvería 404 hasta limpiar la caché de rutas. Con config es
 * determinista y cacheable. Cambiar un slug de acá = cambio de código + 301
 * automático del viejo (lo emite CanonicalLocalizedPage).
 *
 * REGLAS:
 *  - La clave del array es el NOMBRE de la ruta (route name), no el path.
 *  - El slug 'es' es el canónico histórico y NO se renombra nunca: hay enlaces
 *    externos, redirects legacy (config/legacy_redirects.php) e indexación
 *    apoyados en él.
 *  - Los slugs deben ser únicos entre páginas: si dos comparten un slug para
 *    el mismo idioma, gana la ruta registrada primero y la otra queda
 *    inalcanzable. El test InstitutionalPagesLocalizedSlugTest lo verifica.
 *  - Minúsculas, con guiones, sin tildes ni caracteres especiales, máx. 60.
 */

return [

    'about' => [
        'es' => 'nosotros',
        'en' => 'about-us',
        'pt' => 'sobre-nos',
    ],

    'contact' => [
        'es' => 'contacto',
        'en' => 'contact-us',
        'pt' => 'contato',
    ],

    // ESPASEO reportó /en/resenas indexada en Search Console (slug español bajo
    // locale inglés). Estos dos slugs los propone AnyersonDev siguiendo el
    // mismo criterio de la tabla de la sección 4; pendiente que ESPASEO los
    // confirme o los cambie.
    'reviews' => [
        'es' => 'resenas',
        'en' => 'reviews',
        'pt' => 'avaliacoes',
    ],

    'legal.terms' => [
        'es' => 'terminos',
        'en' => 'terms',
        'pt' => 'termos',
    ],

    'legal.privacy' => [
        'es' => 'privacidad',
        'en' => 'privacy',
        'pt' => 'privacidade',
    ],

];
