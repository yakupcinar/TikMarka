<?php

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\ProductStatus;
use App\Enums\SettingGroup;
use App\Models\Category;
use App\Models\Product;

/*
| VİTRİNDE KATEGORİ GEZİNME (4.6B)
|
| ★ ÖLÇÜLEN EKSİK: marka kategori ağacı kuruyordu (1B) ve ürünleri
| kategoriye bağlıyordu, ama müşteri kategoriye HİÇBİR YERDEN
| ulaşamıyordu. Ekmek kırıntısı API cevabında vardı; tıklanacak sayfa
| yoktu. 4.5H'nin kapsam testinde bilerek `null` bırakılmıştı.
|
| ⚠️ Vitrin SUNUCUDA render ediliyor (4-K1) → metin aramak doğru yöntem.
*/

/** @return array{ust: Category, alt: Category, urun: Product} */
function kategoriliMagaza(): array
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $ust = app(CategoryService::class)->olustur('Giyim');
    $alt = app(CategoryService::class)->olustur('Tişört', $ust);

    $urun = app(ProductService::class)->olustur(['title' => 'Basic Tişört', 'brand' => 'Demo']);
    $urun->category()->associate($alt);
    $urun->save();

    app(VariantService::class)->ekle($urun, ['sku' => 'BT-1', 'price' => 200, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return ['ust' => $ust, 'alt' => $alt, 'urun' => $urun->refresh()];
}

it('★★★ UST KATEGORI ALT AGACTAKI urunu gosteriyor — bos sayfa DEGIL', function () {
    ['ust' => $ust] = kategoriliMagaza();

    /*
    | ⚠️ BLOĞUN EN KRİTİK İDDİASI. "Giyim"de doğrudan ürün YOK, ürün
    | "Giyim / Tişört" altında. Alt ağaç sayılmasaydı üst kategoriye
    | tıklayan müşteri boş sayfa görür ve mağazayı bozuk sanardı.
    */
    $this->get("http://marka-a.test/k/{$ust->slug}")
        ->assertOk()
        ->assertSee('Basic Tişört');
});

it('★★★ EKMEK KIRINTISI kokten yaprağa — ve TIKLANABILIR', function () {
    ['ust' => $ust, 'alt' => $alt] = kategoriliMagaza();

    $html = (string) $this->get("http://marka-a.test/k/{$alt->slug}")->assertOk()->getContent();

    /*
    | ⚠️ Zincir MODELDEN (`Category::zincir()`) — API kırıntısıyla AYNI
    | formül. Ayrı hesaplansaydı aynı kategori iki yüzeyde farklı yol
    | gösterebilirdi.
    */
    expect($html)->toContain('Giyim')
        ->and($html)->toContain("/k/{$ust->slug}");

    // ★ Üst halka GERÇEKTEN çalışıyor — `assertSee` bağlantının işlediğini ölçmez.
    $this->get("http://marka-a.test/k/{$ust->slug}")->assertOk();
});

it('★★★ ALT KATEGORILER gosteriliyor — agacin ortasi cikmaz sokak DEGIL', function () {
    ['ust' => $ust, 'alt' => $alt] = kategoriliMagaza();

    /*
    | ⚠️ Yalnızca ürün listelenseydi yaprak olmayan kategoriler çıkmaz
    | sokak olurdu: müşteri "Giyim"e girer ve daha derine inemezdi.
    */
    $html = (string) $this->get("http://marka-a.test/k/{$ust->slug}")->assertOk()->getContent();

    expect($html)->toContain("/k/{$alt->slug}")
        ->and($html)->toContain('Tişört');
});

it('★★★ YAYINLANMAMIS urun kategoride GORUNMUYOR', function () {
    ['ust' => $ust, 'urun' => $urun] = kategoriliMagaza();

    app(ProductService::class)->durumDegistir($urun, ProductStatus::Draft);

    /*
    | ⚠️ `forStorefront()` olmasaydı taslak ürün kategoride görünür ve
    | tıklanınca 404 verirdi (1B-K10).
    */
    $this->get("http://marka-a.test/k/{$ust->slug}")
        ->assertOk()
        ->assertDontSee('Basic Tişört')
        ->assertSee('şu anda ürün yok');
});

it('★★★ BOS kategori LISTEDE yok ama ADRESI calisiyor', function () {
    kategoriliMagaza();

    $bos = app(CategoryService::class)->olustur('Ayakkabı');

    /*
    | ⚠️ Listede gizleniyor: tıklanacak ama hiçbir şey göstermeyen bir
    | bağlantı mağazayı bozuk gösterir (4.5H'de koleksiyon için verilen
    | kararın aynısı).
    */
    $html = (string) $this->get('http://marka-a.test/kategoriler')->assertOk()->getContent();

    expect($html)->not->toContain('Ayakkabı')
        ->and($html)->toContain('Giyim');

    /*
    | ⚠️ Ama ADRES 404 DEĞİL: eski bağlantıdan ya da arama motorundan
    | gelen müşteri buraya düşebilir ve ona "yok" demek yanlış olurdu.
    */
    $this->get("http://marka-a.test/k/{$bos->slug}")
        ->assertOk()
        ->assertSee('şu anda ürün yok');
});

it('★★★ URUNU OLAN kategorinin ATASI da listede — agacin govdesi kaybolmuyor', function () {
    ['ust' => $ust, 'alt' => $alt] = kategoriliMagaza();

    /*
    | ⚠️ Ürün YALNIZCA yaprakta ("Tişört"). Yalnızca dolu kategoriler
    | listelenseydi "Giyim" kaybolur ve ağacın gövdesi olmadan yaprağa
    | ulaşmak anlamsızlaşırdı.
    */
    $html = (string) $this->get('http://marka-a.test/kategoriler')->assertOk()->getContent();

    expect($html)->toContain("/k/{$ust->slug}")
        ->and($html)->toContain("/k/{$alt->slug}");
});

it('★★★ URUN SAYFASINDA kategori yolu VAR — cikmaz sokak DEGIL', function () {
    ['ust' => $ust, 'alt' => $alt, 'urun' => $urun] = kategoriliMagaza();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->toContain("/k/{$ust->slug}")
        ->and($html)->toContain("/k/{$alt->slug}");
});

it('★★ KATEGORISIZ urunde kirinti CIZILMIYOR', function () {
    ['urun' => $urun] = kategoriliMagaza();

    $urun->category()->disassociate();
    $urun->save();

    /*
    | ⚠️ Koşulsuz çizilseydi kategorisiz üründe boş bir "Kategoriler /"
    | satırı kalırdı.
    */
    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('aria-label="Kategori yolu"');
});

it('★★★ MENUDEKI baglanti KOSULLU — kategorisiz magazada YOK', function () {
    markaKur('marka-b.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    /*
    | ⚠️ "Var" demek ÜRÜNÜ OLAN kategori demek. Kategori kaydı olup ürünü
    | olmayan mağazada menü boş bir ağaca götürürdü — 4.5H'de koleksiyon
    | için verilen kararın aynısı.
    */
    app(CategoryService::class)->olustur('Bos Kategori');

    $html = (string) $this->get('http://marka-b.test/')->assertOk()->getContent();

    expect($html)->not->toContain(route('vitrin.kategoriler'));
});

it('★★ IKI DUZEN de kategori yolunu gosteriyor', function () {
    ['ust' => $ust, 'urun' => $urun] = kategoriliMagaza();

    app(SettingsService::class)
        ->yaz(SettingGroup::Theme, 'layout', 'vitrinli');

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->toContain("/k/{$ust->slug}");
});
