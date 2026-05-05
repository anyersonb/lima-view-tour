<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        <lastmod>{{ $url['lastmod'] }}</lastmod>
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ $url['priority'] }}</priority>
        @if (!empty($url['alternates']))
            @foreach ($url['alternates'] as $hreflang => $href)
                <xhtml:link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}"/>
            @endforeach
            <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['alternates']['es'] ?? array_values($url['alternates'])[0] }}"/>
        @endif
        @if (!empty($url['image']))
            <image:image>
                <image:loc>{{ $url['image'] }}</image:loc>
            </image:image>
        @endif
    </url>
@endforeach
</urlset>
