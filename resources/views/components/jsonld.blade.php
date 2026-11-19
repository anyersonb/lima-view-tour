@php
    $settings   = $siteSettings ?? [];
    $locale     = app()->getLocale();
    $siteName   = $settings['site_name']   ?? __('seo.site_name');
    $description= $settings['site_description_' . $locale]
                  ?? ($settings['site_description_es'] ?? __('seo.default_description'));
    $email      = $settings['contact_email']   ?? 'hola@limaviewtours.com';
    $phone      = $settings['contact_phone']   ?? '+51 925 886 725';
    $whatsapp   = $settings['whatsapp']        ?? null;
    $telephone  = $whatsapp ? '+' . $whatsapp : $phone;

    // GEO settings
    $geoName    = $settings['geo_business_name'] ?? $siteName;
    $geoStreet  = $settings['geo_street']        ?? ($settings['contact_address_' . $locale] ?? 'Lima');
    $geoCity    = $settings['geo_city']          ?? 'Lima';
    $geoRegion  = $settings['geo_region']        ?? 'Lima';
    $geoPostal  = $settings['geo_postal_code']   ?? null;
    $geoCountry = $settings['geo_country']       ?? 'PE';
    $geoLat     = $settings['geo_latitude']      ?? null;
    $geoLong    = $settings['geo_longitude']     ?? null;
    $geoPrice   = $settings['geo_price_range']   ?? '$$';

    // Opening hours: derived from contact_hours setting if available
    $rawHours   = $settings['contact_hours_' . $locale]
                  ?? ($settings['contact_hours_es'] ?? null);

    // Social links for sameAs.
    // OJO: no usar ltrim($url, 'https://') — recorta por juego de caracteres
    // y convierte "tiktok.com" en "iktok.com".
    $stripScheme = static fn (?string $url): ?string => $url
        ? 'https://' . preg_replace('#^https?://#i', '', trim($url))
        : null;
    $sameAs = array_values(array_filter([
        // OJO: `??` (no `?:`) — con `?:` un array sin esa key dispara
        // "Undefined array key" y tira 500 en TODA página (bug preexistente
        // encontrado al escribir tests de esta tarea, ver reporte).
        $stripScheme($settings['social_instagram'] ?? null),
        $stripScheme($settings['social_facebook'] ?? null),
        $stripScheme($settings['social_tiktok'] ?? null),
        $stripScheme($settings['social_youtube'] ?? null),
        $settings['social_google_reviews']   ?? null,
        $settings['social_tripadvisor']      ?? null,
        $settings['social_trivago']          ?? null,
    ]));

    // Build TravelAgency / LocalBusiness schema
    // Nota: sin aggregateRating aquí — Google considera "self-serving" las reseñas
    // propias marcadas sobre LocalBusiness/Organization y lo reporta como error en GSC.
    $organization = [
        '@context' => 'https://schema.org',
        '@type'    => ['TravelAgency', 'LocalBusiness'],
        'name'     => $geoName,
        'url'      => url('/' . $locale),
        'logo'     => asset('assets/logos/logo.png'),
        'image'    => asset('assets/banners/banner-hero.jpg'),
        'description' => $description,
        'email'    => $email,
        'telephone' => $telephone,
        'priceRange' => $geoPrice,
        'address'  => array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => $geoStreet,
            'addressLocality' => $geoCity,
            'addressRegion'   => $geoRegion,
            'postalCode'      => $geoPostal,
            'addressCountry'  => $geoCountry,
        ]),
        'areaServed' => [
            ['@type' => 'City', 'name' => 'Lima'],
            ['@type' => 'City', 'name' => 'Cusco'],
            ['@type' => 'City', 'name' => 'Ica'],
            ['@type' => 'City', 'name' => 'Paracas'],
        ],
        'inLanguage' => ['es-PE', 'en-US', 'pt-BR'],
    ];

    // Add geo coordinates only when available
    if ($geoLat && $geoLong) {
        $organization['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $geoLat,
            'longitude' => (float) $geoLong,
        ];
    }

    // Opening hours (raw string, e.g. "Lunes a Domingo 7am-10pm")
    if ($rawHours) {
        $organization['openingHours'] = $rawHours;
    }

    if (! empty($sameAs)) {
        $organization['sameAs'] = $sameAs;
    }

    $website = [
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => $siteName,
        'url'      => url('/'),
        'inLanguage' => $locale,
        'potentialAction' => [
            '@type'        => 'SearchAction',
            'target'       => url('/' . $locale . '/buscar?q={search_term_string}'),
            'query-input'  => 'required name=search_term_string',
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
<script type="application/ld+json">{!! json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
