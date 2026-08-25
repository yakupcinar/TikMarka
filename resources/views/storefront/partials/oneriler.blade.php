{{--
    ÖNERİLER (4.6E) — ORTAK PARÇA

    ⚠️ İki düzen de (`sade`, `vitrinli`) bunu kullanıyor. 4.6A'da varyant
    seçicisi tek düzene uygulanıp öteki unutulmuştu; o ders burada
    baştan uygulanıyor.

    ⚠️ İKİ BÖLÜM AYRI ve başlıkları FARKLI ŞEY SÖYLÜYOR: biri "buna
    benzer", öteki "en çok satılan". Tek bölümde birleştirilseydi müşteri
    hangi sebeple önerildiğini bilemezdi.
--}}
@if ($benzerler->isNotEmpty())
    <section class="oneri">
        <h2>Benzer ürünler</h2>

        {{-- ⚠️ Aynı ızgara parçası: kart biçimi tek yerde (4.6B). --}}
        @include('storefront.partials.urun-izgarasi', ['urunler' => $benzerler])
    </section>
@endif

@if ($cokSatanlar->isNotEmpty())
    <section class="oneri">
        {{--
            ⚠️ BAŞLIK "BEĞENİLENLER" DEĞİL. Veri kaynağı SATIŞ; beğeni
            sayacı için gereken olaylar 4.6F'de yazılacak. "Beğenilenler"
            demek, elimizde olmayan bir sayıyı varmış gibi sunmak olurdu.
        --}}
        <h2>Çok satanlar</h2>

        @include('storefront.partials.urun-izgarasi', ['urunler' => $cokSatanlar])
    </section>
@endif
