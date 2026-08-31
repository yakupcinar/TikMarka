<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($adresler as $adres)
    <url>
        <loc>{{ $adres['loc'] }}</loc>
@if ($adres['lastmod'])
        <lastmod>{{ $adres['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $adres['changefreq'] }}</changefreq>
    </url>
@endforeach
</urlset>
