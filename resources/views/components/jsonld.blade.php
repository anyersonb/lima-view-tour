@php
    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'TravelAgency',
        'name' => __('seo.site_name'),
        'url' => url('/' . app()->getLocale()),
        'logo' => asset('images/logo.png'),
        'description' => __('seo.default_description'),
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Lima',
            'addressRegion' => 'Lima',
            'addressCountry' => 'PE',
        ],
        'areaServed' => [
            '@type' => 'City',
            'name' => 'Lima',
        ],
        'inLanguage' => ['es', 'en'],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
