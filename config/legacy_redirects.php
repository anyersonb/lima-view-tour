<?php

/*
|--------------------------------------------------------------------------
| Redirecciones de la era WordPress / WooCommerce (2026-07-26)
|--------------------------------------------------------------------------
|
| Origen: exports de Search Console del 2026-07-26 (informes "No encontrada
| (404)", "Rastreada: actualmente sin indexar" y "Página con redirección").
| 40 URLs indexadas que terminaban en 404.
|
| Se consume desde el Route::fallback() de routes/web.php.
|
| Claves SIN barra inicial ni final, ya normalizadas en minúsculas.
| Valores: path absoluto de destino (con prefijo de idioma).
|
*/

return [

    /*
    | 301 — la URL vieja tiene equivalente vivo.
    | Conserva el posicionamiento acumulado.
    */
    'map' => [

        // ── Tours ES: slug raíz de WordPress → ficha actual ──────────────
        'centro-historico-de-lima-parque-de-las-aguas' => '/es/tours/detalle/centro-historico-de-lima-parque-de-las-aguas',
        'full-day-lima-ancestral-colonial-y-moderna'   => '/es/tours/detalle/full-day-lima-ancestral-colonial-y-moderna',
        'laguna-humantay'                              => '/es/tours/detalle/laguna-humantay',
        'maras-moray-minas-de-sal'                     => '/es/tours/detalle/maras-moray-minas-de-sal',
        'maras-moray-y-minas-de-sal-cuatrimotos'       => '/es/tours/detalle/maras-moray-y-minas-de-sal-cuatrimotos',
        'montana-arcoiris-de-7-colores'                => '/es/tours/detalle/montana-arcoiris-de-7-colores',

        // Slug renombrado: singular "noche" → plural "noches"
        'machu-picchu-2-dias-1-noche' => '/es/tours/detalle/machu-picchu-2-dias-1-noches',

        // Huacachina: el slug actual lleva sufijo "-2"
        'tour-de-dia-completo-al-oasis-de-huacachina-islas-ballestas-en-paracas' => '/es/tours/detalle/tour-de-dia-completo-al-oasis-de-huacachina-islas-ballestas-en-paracas-2',

        // Huacachina buggy privado Can-Am: el tour se renombró por completo
        'tour-de-dia-completo-al-oasis-de-huacachina-con-buggie-privado-canam-islas-ballestas-en-paracas' => '/es/tours/detalle/tour-privado-huacachina-islas-ballestas-atardecer-buggy-can-am',

        // Nazca: el tour vigente une Nazca + Huacachina
        'full-day-a-las-lineas-de-nazca' => '/es/tours/detalle/las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina',

        // ── Categorías ES ────────────────────────────────────────────────
        'tours-en-cusco'                    => '/es/tours/categoria/cusco',
        'tours-en-lima'                     => '/es/tours/categoria/lima',
        'categoria-producto/tours-en-cusco' => '/es/tours/categoria/cusco',

        // ── Páginas ES ───────────────────────────────────────────────────
        'terminos-y-condiciones' => '/es/terminos',

        // ── Inglés: la estructura vieja usaba slugs traducidos bajo /en/,
        //    la nueva usa el mismo slug español en las 3 versiones ────────
        'en/home'          => '/en',
        'en/us'            => '/en/nosotros',
        'en/contact'       => '/en/contacto',
        'en/tours-in-cusco' => '/en/tours/categoria/cusco',

        'en/humantay-laguna'                    => '/en/tours/detalle/laguna-humantay',
        'en/the-incas-sacred-valley'            => '/en/tours/detalle/valle-sagrado-de-los-incas',
        'en/maras-moray-and-salt-mines-quad-bike' => '/en/tours/detalle/maras-moray-y-minas-de-sal-cuatrimotos',
        'en/get-to-know-machu-picchu-if-you-dont-have-a-ticket' => '/en/tours/detalle/conoce-machu-picchu-si-no-tienes-entrada',
        'en/the-enigmatic-nazca-lines-the-oasis-of-huacachina-and-the-ballestas-islands' => '/en/tours/detalle/las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina',
        'en/full-day-to-the-nazca-lines' => '/en/tours/detalle/las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina',

        /*
        | ⚠ SUPUESTOS PENDIENTES DE CONFIRMAR CON EL CLIENTE
        | El tour viejo "Parque de las Aguas, Barranco y Miraflores" se asume
        | absorbido por "Centro Histórico de Lima + Parque de las Aguas".
        | Si Leo dice que no es el mismo producto, mandar a la categoría Lima.
        */
        'parque-de-las-aguas-barranco-y-miraflores'    => '/es/tours/detalle/centro-historico-de-lima-parque-de-las-aguas',
        'en/water-park-barranco-and-miraflores'        => '/en/tours/detalle/centro-historico-de-lima-parque-de-las-aguas',

        /*
        | ⚠ "machu-picchu" a secas es ambiguo: hay dos tours vigentes
        | (2 días/1 noche y "conoce Machu Picchu si no tienes entrada").
        | Se manda a la categoría Cusco en vez de adivinar un producto —
        | redirigir al tour equivocado Google lo trata como soft 404.
        */
        'machu-picchu' => '/es/tours/categoria/cusco',
    ],

    /*
    | 410 Gone — producto descontinuado o basura de WordPress.
    | No existe equivalente: decirle a Google que se borró es más rápido y
    | más honesto que un 404, y no arrastra la URL durante meses.
    */
    'gone' => [
        'city-tour-lima-clases-de-pisco-sour',          // tour descontinuado
        'product/city-tour-lima-clases-de-pisco-sour',
        'tour-de-museos',                               // tour descontinuado
        'en/museum-tour',
    ],
];
