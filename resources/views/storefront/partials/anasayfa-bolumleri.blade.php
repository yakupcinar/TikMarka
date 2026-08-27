{{--
    ANA SAYFA BÖLÜMLERİ — ORTAK PARÇA (B1)

    ⚠️ İKİ DÜZEN DE KULLANIYOR (`sade` ve `vitrinli`). 4.6A'da varyant
    seçicisi yalnızca bir düzene eklenmiş ve öteki düzeni seçen markanın
    müşterisinde HİÇ görünmemişti; altı test bunu göremedi çünkü hepsi
    VARSAYILAN düzende koşuyordu. Kopya yerine ortak parça.

    ⚠️ Boş bölüm buraya HİÇ GELMİYOR — eleme `HomeSections`'ta ve
    gerekçesi orada: verisi olmayan bölüm müşteriye yanlış bilgi verir
    ("tek tıklamayla popüler ürün", "yeni gelenler = katalogun tamamı").
--}}
@foreach ($bolumler as $bolum)
    <section class="bolum" id="bolum-{{ $bolum['anahtar'] }}">
        <h2 class="bolum-baslik">{{ $bolum['baslik'] }}</h2>

        @include('storefront.partials.urun-izgarasi', ['urunler' => $bolum['urunler']])
    </section>
@endforeach
