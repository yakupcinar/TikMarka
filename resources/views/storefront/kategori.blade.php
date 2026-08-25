@extends('storefront.layout')

@section('baslik', $kategori->name.' — '.$tema['ad'])

@section('aciklama')
    {{ $kategori->name }} kategorisindeki ürünler.
@endsection

@section('icerik')

    {{--
        EKMEK KIRINTISI

        ⚠️ Zincir MODELDEN geliyor (`Category::zincir()`) — API cevabındaki
        kırıntıyla AYNI formül. Ayrı hesaplansaydı aynı kategori iki
        yüzeyde farklı yol gösterebilirdi.
    --}}
    <nav class="kirinti" aria-label="Kategori yolu">
        <a href="{{ route('vitrin.kategoriler') }}">Kategoriler</a>

        @foreach ($zincir as $halka)
            <span aria-hidden="true">/</span>

            @if ($loop->last)
                <span aria-current="page">{{ $halka->name }}</span>
            @else
                <a href="{{ route('vitrin.kategori', ['slug' => $halka->slug]) }}">{{ $halka->name }}</a>
            @endif
        @endforeach
    </nav>

    <h1>{{ $kategori->name }}</h1>

    {{--
        ⚠️ ALT KATEGORİLER de gösteriliyor: ağacın ortasındaki bir
        kategoride müşteri ancak böyle DERİNE inebilir. Yalnızca ürün
        listelenseydi yaprak olmayan kategoriler çıkmaz sokak olurdu.
    --}}
    @if ($altlar->isNotEmpty())
        <ul class="alt-kategoriler">
            @foreach ($altlar as $alt)
                <li><a href="{{ route('vitrin.kategori', ['slug' => $alt->slug]) }}">{{ $alt->name }}</a></li>
            @endforeach
        </ul>
    @endif

    @if ($urunler->isEmpty())
        {{--
            ⚠️ Boş kategori bir HATA DEĞİL. Liste sayfası boşları
            gizliyor ama doğrudan adrese gelen müşteri buraya düşebilir
            (eski bağlantı, arama motoru).
        --}}
        <p class="bos">Bu kategoride şu anda ürün yok.</p>
    @else
        @include('storefront.partials.urun-izgarasi')
    @endif

@endsection
