{{--
    SEO ETİKETLERİ (B3)

    4.6G taraması bunu bir "rakip özelliği" olarak değil, KENDİ
    KARARIMIZIN YARIM KALMIŞ HÂLİ olarak çıkardı. Proje SEO için üç ayrı
    karar verdi — vitrin sunucuda render ediliyor (4-K1), Inertia SSR
    reddedildi (4-K2), B2'de sonsuz kaydırma yerine gerçek bağlantı
    seçildi — ama ölçüldü: canonical yok, Open Graph yok, JSON-LD yok,
    sitemap yok. Yani SSR'ın bedeli ödeniyor, karşılığı alınmıyordu.
--}}

{{--
    ★ ARAMA SONUCU DİZİNE GİRMEZ.

    ⚠️ `?q=` ile üretilen sayfa sonsuz sayıda adres demek ve içeriği
    zaten katalogda var. Dizine girerse hem tarama bütçesi harcanır hem
    de aynı ürün onlarca adresle yarışır.

    ⚠️ `follow` KALIYOR: sayfa dizine girmesin ama içindeki ürün
    bağlantıları izlensin.
--}}
@if (request()->filled('q'))
    <meta name="robots" content="noindex, follow">
@endif

{{--
    ★ CANONICAL — sayfa KENDİNİ işaret ediyor.

    ⚠️ Sayfalı listede hepsini 1. sayfaya işaret etmek YANLIŞ olurdu:
    2. sayfadaki ürünler o zaman hiçbir adreste "asıl" sayılmaz ve
    dizinden düşer. B2 `?sayfa=` adreslerini yeni ekledi, yani bu karar
    bugün gerekli hâle geldi.

    ⚠️ `q` ve diğer parametreler DIŞARIDA: yalnızca `sayfa` taşınıyor.
    Aksi hâlde her UTM etiketi ayrı bir "asıl adres" üretirdi.
--}}
<link rel="canonical" href="{{ $seoCanonical }}">

{{-- ★ Sosyal önizleme. D2C'de ürün Instagram/WhatsApp'ta paylaşılıyor;
     bu etiketler yokken önizleme TAMAMEN BOŞ çıkıyordu. --}}
<meta property="og:site_name" content="{{ $tema['ad'] }}">
<meta property="og:type" content="@yield('og_tur', 'website')">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:title" content="@yield('baslik', $tema['ad'])">
<meta property="og:description" content="@yield('aciklama', $tema['ad'])">
<meta property="og:locale" content="tr_TR">

@hasSection('og_gorsel')
    <meta property="og:image" content="@yield('og_gorsel')">

    {{-- ⚠️ Görsel VARSA büyük kart, yoksa küçük: görselsiz `summary_large_image`
         boş bir dikdörtgen çizdiriyor. --}}
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif

@stack('yapisal_veri')
