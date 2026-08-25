@extends('storefront.layout')

@section('baslik', 'Favorilerim — '.$tema['ad'])

@section('icerik')
    <div class="hesap">
        <h1>Favorilerim</h1>

        <p><a href="{{ route('vitrin.hesap') }}">← Hesabım</a></p>

        {{--
            ⚠️ Boş liste bir HATA DEĞİL: yeni müşteri için normal. Sessiz
            bırakılsaydı sayfa bozuk görünürdü.
        --}}
        @if ($favoriler->isEmpty())
            <p class="ipucu">Henüz favori ürününüz yok.</p>
        @else
            <ul class="favori-listesi">
                @foreach ($favoriler as $favori)
                    @php($urun = $favori->product)

                    <li class="favori-satir">
                        @php($gorsel = $urun->images->first())

                        @if ($gorsel)
                            {{--
                                ⚠️ Adres `tenant_asset()` ile kuruluyor
                                (M-2.7): Domain yolu döndürüyor, adresi
                                HTTP katmanı kuruyor. Ham `src` yazılsaydı
                                4G'den sonra kırık görsel çıkardı.
                            --}}
                            <img src="{{ tenant_asset($gorsel->path) }}" alt="{{ $gorsel->alt ?? $urun->title }}" class="favori-gorsel">
                        @endif

                        <div class="favori-bilgi">
                            <a href="{{ route('vitrin.urun', ['slug' => $urun->slug]) }}">{{ $urun->title }}</a>
                        </div>

                        {{-- ⚠️ Aynı ortak parça: liste ve ürün sayfası aynı düğmeyi kullanıyor. --}}
                        @include('storefront.partials.favori-dugmesi', [
                            'urun' => $urun,
                            'favorideMi' => true,
                            'musteriGirisli' => true,
                        ])
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
