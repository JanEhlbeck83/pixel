@php
    echo '<?xml version="1.0" encoding="UTF-8"?>';
@endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
    @foreach ($page['urls'] as $url)
    <url>
        <loc>{{ $url }}</loc>
        @foreach ($page['alternates'] as $locale => $alternate)
        <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $alternate }}" />
        @endforeach
    </url>
    @endforeach
@endforeach
</urlset>
