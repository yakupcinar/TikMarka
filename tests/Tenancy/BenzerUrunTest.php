<?php

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\SimilarProductQuery;
use App\Domain\Catalog\VariantService;
use App\Domain\Order\CheckoutService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\ProductStatus;
use App\Enums\SettingGroup;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

/*
| ÖNERİLER: BENZER ÜRÜNLER VE ÇOK SATANLAR (4.6E)
|
| ⚠️ İKİ AYRI SORU: "buna benzer ne var" ve "en çok ne satılıyor".
| Birleştirilseydi ekran hangisini gösterdiğini söyleyemez ve müşteri
| "benzer" başlığı altında alakasız ama çok satan bir ürün görürdü.
|
| ⚠️ "Beğenilenler" DEĞİL "çok satanlar": beğeni sayacı için gereken
| olaylar 4.6F'de yazılacak. Uydurma bir sayı sunmamak için başlık da
| veri kaynağını söylüyor.
*/

function oneriUrunu(string $baslik, ?Category $kategori = null, ?string $marka = null, int $stok = 5): Product
{
    $urun = app(ProductService::class)->olustur(array_filter([
        'title' => $baslik,
        'brand' => $marka,
    ]));

    if ($kategori !== null) {
        $urun->category()->associate($kategori);
        $urun->save();
    }

    app(VariantService::class)->ekle($urun, [
        'sku' => 'SKU-'.Str::random(6),
        'price' => 100,
        'stock' => $stok,
    ]);

    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return $urun->refresh();
}

/**
 * Bir ürünün listedeki sırası.
 *
 * ⚠️ `array_search` `false` da dönebiliyor ve statik analiz onu
 * karşılaştırmaya sokmuyor. Bulunamama hâli `-1` ile ayrılıyor: her
 * gerçek sıradan küçük olduğu için "yok" ile "başta" karışmıyor.
 *
 * @param  array<array-key, mixed>  $liste  `pluck()->all()` çıktısı — tipi gevşek
 */
function oneriSirasi(Product $urun, array $liste): int
{
    $sira = array_search($urun->id, $liste, strict: true);

    return $sira === false ? -1 : (int) $sira;
}

function oneriMagazasi(): void
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
}

it('★★★ BENZERLER once AYNI KATEGORI ALT AGACINDAN', function () {
    oneriMagazasi();

    $ust = app(CategoryService::class)->olustur('Giyim');
    $alt = app(CategoryService::class)->olustur('Tişört', $ust);

    $urun = oneriUrunu('Basic Tişört', $alt);
    $kardes = oneriUrunu('Oversize Tişört', $alt);
    $amca = oneriUrunu('Kot Pantolon', $ust);
    $alakasiz = oneriUrunu('Kablosuz Kulaklık');

    $benzerler = app(SimilarProductQuery::class)->benzerler($urun);

    /*
    | ⚠️ ALT AĞAÇ dâhil: üst kategorideki ürün de benzer sayılıyor
    | (4.6B'deki `kategoriyeGore`). Yalnızca birebir kategori
    | alınsaydı "Tişört"te tek ürün olan mağazada bölüm boş kalırdı.
    */
    expect($benzerler->pluck('id')->all())->toContain($kardes->id, $amca->id);

    // ★ Kendisi listede YOK.
    expect($benzerler->pluck('id')->all())->not->toContain($urun->id);

    /*
    | ⚠️ Alakasız ürün de var ama SONDA: kategori kademesi dolduğu için
    | ancak "en yeniler" kademesinde giriyor. Sıra ölçülüyor.
    */
    $sira = $benzerler->pluck('id')->all();

    expect(oneriSirasi($alakasiz, $sira))->toBeGreaterThan(oneriSirasi($kardes, $sira));
});

it('★★★ KATEGORI YETMEYINCE AYNI MARKA, sonra EN YENILER', function () {
    oneriMagazasi();

    $kategori = app(CategoryService::class)->olustur('Aksesuar');

    // Kategorisinde TEK ürün — kategori kademesi hiçbir şey veremiyor.
    $urun = oneriUrunu('Deri Cüzdan', $kategori, 'Nova');
    $ayniMarka = oneriUrunu('Nova Kemer', null, 'Nova');
    $baskaMarka = oneriUrunu('Zeta Şapka', null, 'Zeta');

    $benzerler = app(SimilarProductQuery::class)->benzerler($urun);

    /*
    | ⚠️ KADEMELER BİRBİRİNİ TAMAMLIYOR, biri ötekini elemiyor. Tek
    | kademeli olsaydı bu sayfada bölüm BOŞ kalırdı — ve boş bir
    | "Benzer ürünler" başlığı mağazayı bozuk gösterir.
    */
    $sira = $benzerler->pluck('id')->all();

    expect($sira)->toContain($ayniMarka->id, $baskaMarka->id);

    expect(oneriSirasi($ayniMarka, $sira))->toBeLessThan(oneriSirasi($baskaMarka, $sira));
});

it('★★★ TASLAK urun BENZER olarak sizmiyor', function () {
    oneriMagazasi();

    $kategori = app(CategoryService::class)->olustur('Giyim');
    $urun = oneriUrunu('Basic Tişört', $kategori);
    $taslak = oneriUrunu('Gizli Tişört', $kategori);

    app(ProductService::class)->durumDegistir($taslak, ProductStatus::Draft);

    /*
    | ⚠️ `forStorefront()` olmasaydı taslak ürün "benzer ürün" olarak
    | sızardı — 4.5H'de koleksiyon için ölçülen kusurun aynısı.
    */
    expect(app(SimilarProductQuery::class)->benzerler($urun)->pluck('id')->all())
        ->not->toContain($taslak->id);
});

it('★★★ COK SATANLAR ODENMIS siparisten sayiliyor — bekleyen SAYILMIYOR', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi('marka-a.test');
    app(StorePublication::class)->yayinla();

    /*
    | ⚠️ `pending` sayılsaydı ödemesi hiç tamamlanmayan sepetler "çok
    | satan" üretirdi — ve o listeyi üretmenin yolu ödeme sayfasına kadar
    | gidip vazgeçmek olurdu.
    */
    expect(app(SimilarProductQuery::class)->cokSatanlar())->toHaveCount(0);

    app(CheckoutService::class)->odemeBasarili($siparis);

    $cok = app(SimilarProductQuery::class)->cokSatanlar();

    expect($cok->pluck('id')->all())->toContain($varyant->product_id);
});

it('★★★ COK SATANLAR SATIS SIRASINA gore — adi yalan olmasin', function () {
    oneriMagazasi();

    $az = oneriUrunu('Az Satan', null, null, 50);
    $cok = oneriUrunu('Çok Satan', null, null, 50);

    // 2 adet vs 7 adet — sıra buna göre olmalı.
    satisYap($az, 2);
    satisYap($cok, 7);

    $liste = app(SimilarProductQuery::class)->cokSatanlar()->pluck('id')->all();

    /*
    | ⚠️ `whereIn` KENDİ sırasını uyguluyor (id sırası). Sıra elle
    | korunmasaydı "çok satanlar" başlığı yalan olurdu: liste satış
    | sırasına göre değil id sırasına göre çıkardı.
    */
    expect(oneriSirasi($cok, $liste))->toBeLessThan(oneriSirasi($az, $liste));
});

it('★★★ SAYFADAKI urun COK SATANLAR listesinden CIKARILIYOR', function () {
    oneriMagazasi();

    $urun = oneriUrunu('Çok Satan', null, null, 50);
    satisYap($urun, 5);

    // ⚠️ Müşteri zaten onun sayfasında; listede kendini görmek yer israfı.
    expect(app(SimilarProductQuery::class)->cokSatanlar(haric: $urun)->pluck('id')->all())
        ->not->toContain($urun->id);
});

it('★★★ IKI BOLUM de EKRANDA ve BASLIKLARI FARKLI SEY soyluyor', function () {
    oneriMagazasi();

    $kategori = app(CategoryService::class)->olustur('Giyim');
    $urun = oneriUrunu('Basic Tişört', $kategori, null, 50);
    $kardes = oneriUrunu('Oversize Tişört', $kategori, null, 50);

    satisYap($kardes, 3);

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->toContain('Benzer ürünler')
        ->and($html)->toContain('Çok satanlar')

        /*
        | ⚠️ "Beğenilenler" YAZMAMALI: veri kaynağı satış. Beğeni sayacı
        | 4.6F'de gelecek; şimdi öyle demek uydurma bir sayı sunmak olurdu.
        */
        ->and($html)->not->toContain('Beğenilenler');
});

it('★★ ONERI YOKSA BASLIK da YOK — bos bolum cizilmiyor', function () {
    oneriMagazasi();

    // Mağazada TEK ürün: benzer de yok, satış da yok.
    $urun = oneriUrunu('Tek Ürün');

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    /*
    | ⚠️ Boş bir "Benzer ürünler" başlığı mağazayı bozuk gösterir; yeni
    | mağazada bu durum NORMAL.
    */
    expect($html)->not->toContain('Benzer ürünler')
        ->and($html)->not->toContain('Çok satanlar');
});

it('★★ IKI DUZEN de onerileri gosteriyor', function () {
    oneriMagazasi();

    $kategori = app(CategoryService::class)->olustur('Giyim');
    $urun = oneriUrunu('Basic Tişört', $kategori);
    oneriUrunu('Oversize Tişört', $kategori);

    /*
    | ⚠️ 4.6A'nın dersi: özellik tek düzene uygulanıp öteki unutulabiliyor.
    */
    foreach (['sade', 'vitrinli'] as $duzen) {
        app(SettingsService::class)
            ->yaz(SettingGroup::Theme, 'layout', $duzen);

        $this->get("http://marka-a.test/urun/{$urun->slug}")
            ->assertOk()
            ->assertSee('Benzer ürünler');
    }
});
