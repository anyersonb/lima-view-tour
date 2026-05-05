@php
    $settings = $siteSettings ?? [];
    $siteName = $settings['site_name'] ?? __('seo.site_name');
    $description = $settings['site_description_' . app()->getLocale()] ?? ($settings['site_description_es'] ?? __('seo.default_description'));
    $email = $settings['contact_email'] ?? 'hola@limaviewtours.com';
    $phone = $settings['contact_phone'] ?? '+51 935 542 384';
    $address = $settings['contact_address_' . app()->getLocale()] ?? ($settings['contact_address_es'] ?? 'Lima, Perú');

    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'TravelAgency',
        'name' => $siteName,
        'url' => url('/' . app()->getLocale()),
        'logo' => asset('assets/logos/logo.png'),
        'image' => asset('assets/banners/banner-hero.jpg'),
        'description' => $description,
        'email' => $email,
        'telephone' => $phone,
        'priceRange' => '$$',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressLocality' => 'Lima',
            'addressRegion' => 'Lima',
            'addressCountry' => 'PE',
        ],
        'areaServed' => [
            ['@type' => 'City', 'name' => 'Lima'],
            ['@type' => 'City', 'name' => 'Cusco'],
            ['@type' => 'City', 'name' => 'Ica'],
            ['@type' => 'City', 'name' => 'Paracas'],
        ],
        'sameAs' => array_values(array_filter([
            $settings['social_instagram'] ?? null,
            $settings['social_facebook'] ?? null,
            $settings['social_tiktok'] ?? null,
            $settings['social_youtube'] ?? null,
        ])),
        'inLanguage' => ['es-PE', 'en-US'],
    ];

    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteName,
        'url' => url('/'),
        'inLanguage' => app()->getLocale(),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => url('/' . app()->getLocale() . '/buscar?q={search_term_string}'),
            'query-input' => 'required name=search_term_string',
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
