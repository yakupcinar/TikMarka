{{--
    "sade" düzeni — ana sayfa. (4A)

    ⚠️ Klasör adı `settings.theme.layout` değerinden geliyor ([ThemeSettings]).
    Bugün tek düzen var; 4G'de ikincisi eklendiğinde yapılacak tek şey yeni
    bir klasör açmak ve listeye adını yazmak olacak.
--}}
@extends('storefront.layout')

@section('baslik', $arama ? $arama.' — '.$tema['ad'] : $tema['ad'])

@section('icerik')

    @if ($arama)
        <p style="padding-top:24px">
            <strong>“{{ $arama }}”</strong> için {{ $urunler->total() }} sonuç
            · <a href="{{ route('vitrin.anasayfa') }}">aramayı temizle</a>
        </p>
    @endif

    {{--
        ★ BÖLÜMLER (B1) — tam katalogdan ÖNCE.

        ⚠️ Bölümler seçilmiş; altındaki liste her şey. Sıra ters olsaydı
        müşteri önce 24 ürünlük düz listeyi görür, seçilmiş bölümler
        sayfanın dibinde kalırdı.

        ⚠️ Arama varsa `$bolumler` boş geliyor (controller'da): müşteri
        bir şey aradıysa ekranın cevabı o olmalı.
    --}}
    @if (! empty($bolumler))
        @include('storefront.partials.anasayfa-bolumleri')

        {{-- ⚠️ Alttaki listenin NE OLDUĞU söyleniyor: başlıksız bırakılsaydı
             müşteri bölümlerin devamı sanırdı. --}}
        <h2 class="bolum-baslik">Tüm ürünler</h2>
    @endif

    @if ($urunler->isEmpty())
        <p class="bos">
            @if ($arama)
                Aradığınız ürün bulunamadı.
            @else
                {{-- ⚠️ Mağaza AÇIK ama ürünü yok: müşteriye hata gibi görünmemeli. --}}
                Bu mağazada henüz ürün yok.
            @endif
        </p>
    @else
        {{-- 
            ⚠️ ORTAK PARÇA (B2). Bu ızgara iki ana sayfada da KOPYAYDI ve
            işaretlemesi birebir aynıydı; `partials/urun-izgarasi` zaten
            kategori, koleksiyon ve ana sayfa bölümlerinde kullanılıyordu.
        
            Kopya kaldığı sürece tembel yükleme gibi her düzeltme ÜÇ yere
            yazılacaktı — ve 4.6AL'de tam bu yüzden bir düzen geride kaldı.
        --}}
        {{-- ⚠️ `istekliSayisi` bölümlerin varlığına bağlı: bölüm çizildiyse bu
     ızgara ekranın altında kalıyor ve hiçbir görseli istekli olmamalı. --}}
@include('storefront.partials.urun-izgarasi', ['istekliSayisi' => empty($bolumler) ? 4 : 0])

        @include('storefront.partials.daha-fazla')
    @endif

@endsection
