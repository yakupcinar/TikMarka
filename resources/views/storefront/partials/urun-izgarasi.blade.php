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
                {{--
                    ⚠️ Adres modelin `url()`'inden: `tenant_asset()` orada (M-2.7).

                    ★ TEMBEL YÜKLEME (B2) — ama İLK SATIR HARİÇ.

                    ⚠️ Ekranın üstündeki görsele `lazy` vermek onu
                    GECİKTİRİR: tarayıcı önce yerleşimi hesaplayıp sonra
                    indirmeye başlıyor. Yani "her şeye lazy" demek, en
                    çok görülen görselleri yavaşlatmak demek. İlk satır
                    `eager`, gerisi `lazy`.

                    ⚠️ `decoding="async"` çözümlemeyi ana iş parçacığından
                    çıkarıyor; çok kartlı ızgarada kaydırma takılmıyor.

                    ⚠️ Yer tutucu ölçü `aspect-ratio: 1` ile CSS'te
                    (`layout.blade.php`); olmasaydı görseller yüklendikçe
                    sayfa zıplardı.
                --}}
                <img src="{{ $urun->images->first()->url() }}"
                     alt="{{ $urun->title }}"
                     loading="{{ $loop->index < ($istekliSayisi ?? 4) ? 'eager' : 'lazy' }}"
                     decoding="async">
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
