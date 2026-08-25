{{--
    ÜRÜN IZGARASI — ORTAK PARÇA (4.6B)

    ⚠️ Koleksiyon sayfası (4.5H) ve kategori sayfası aynı kartı çiziyor.
    Kopyalansaydı biri güncellenip öteki unutulurdu; 4.6C ve 4.6D'de aynı
    gerekçeyle ortak parça kullanıldı.
--}}
<div class="izgara">
    @foreach ($urunler as $urun)
        <a class="kart" href="{{ route('vitrin.urun', $urun->slug) }}">
            @if ($urun->images->first())
                {{-- ⚠️ Adres modelin `url()`'inden: `tenant_asset()` orada (M-2.7). --}}
                <img src="{{ $urun->images->first()->url() }}" alt="{{ $urun->title }}">
            @else
                <div class="yok">Görsel yok</div>
            @endif

            <div class="govde">
                <span class="ad">{{ $urun->title }}</span>

                @if ($urun->variants->isNotEmpty())
                    {{--
                        ⚠️ EN DÜŞÜK fiyat gösteriliyor: bir üründe farklı
                        fiyatlı varyantlar olabiliyor ve listede tek sayı
                        yazılacaksa "şu fiyattan başlayan" doğru olanı.
                    --}}
                    <span class="fiyat">
                        {{ number_format((float) $urun->variants->min('price'), 2, ',', '.') }} TL
                    </span>
                @endif
            </div>
        </a>
    @endforeach
</div>
