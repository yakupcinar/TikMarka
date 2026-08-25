<?php

use App\Domain\Catalog\DuplicateSkuException;
use App\Domain\Catalog\DuplicateVariantException;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

/*
| VARYANT BENZERSİZLİĞİ (4.6X) — gerçek kullanımda bulunan kusur.
|
| ★ Marka bir varyant açtı, sildi, aynı SKU ile yenisini açmak istedi ve
| ham `UniqueConstraintViolationException` gördü. Ölçünce üç ayrı boşluk
| çıktı:
|   1. `sku` ve `(product_id, options)` kısıtları `deleted_at`'e
|      BAKMIYORDU — silinen varyant kimliğini sonsuza kadar işgal ediyordu.
|   2. `ekle()` SKU'yu HİÇ kontrol etmiyordu.
|   3. `guncelle()` İKİSİNİ DE kontrol etmiyordu.
|
| ⚠️ 4.5L'de `(product_id, options)` için "kısıt tek başına arayüz
| değildir" dersi çıkarılmıştı; kontrol yazıldı ama YALNIZCA ekleme
| yoluna ve YALNIZCA canlı satırlar için. Kısıt ise silinmişleri de
| sayıyordu — yani Domain ile veritabanı AYNI KURALI FARKLI anlıyordu.
*/

function benzersizlikUrunu(): Product
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Deri Cüzdan', 'brand' => 'Demo']);
    $eksen = eksenliDeger('Renk', ['Kırmızı', 'Mavi', 'Yeşil']);
    app(ProductService::class)->eksenleriAyarla($urun, [$eksen]);

    return $urun->refresh();
}

function varyantEkle(Product $urun, string $sku, string $renk): ProductVariant
{
    return app(VariantService::class)->ekle(
        $urun, ['sku' => $sku, 'price' => 100, 'stock' => 5], ['renk' => $renk]
    );
}

it('★★★ SILINEN varyantin SKU su SERBEST kaliyor — kullanicinin yasadigi hata', function () {
    $urun = benzersizlikUrunu();

    $ilk = varyantEkle($urun, 'CZ-1', 'kirmizi');
    app(VariantService::class)->sil($ilk);

    // ⚠️ Öncesinde burası ham UniqueConstraintViolationException veriyordu.
    $yeni = varyantEkle($urun, 'CZ-1', 'mavi');

    expect($yeni->sku)->toBe('CZ-1')
        ->and(ProductVariant::withTrashed()->where('sku', 'CZ-1')->count())->toBe(2);
});

it('★★★ SILINEN varyantin SECENEGI de SERBEST — 4.5L eksik kalmis', function () {
    $urun = benzersizlikUrunu();

    $ilk = varyantEkle($urun, 'CZ-1', 'yesil');
    app(VariantService::class)->sil($ilk);

    /*
    | ⚠️ Bu yol 4.5L'de kapatıldı SANILIYORDU. Domain kontrolü silinmişi
    | görmüyordu (doğru), ama veritabanı kısıtı görüyordu (yanlış) —
    | ikisi uyuşmadığı için hata Domain'i atlayıp veritabanından geliyordu.
    */
    $yeni = varyantEkle($urun, 'CZ-2', 'yesil');

    expect($yeni->options)->toBe(['renk' => 'yesil']);
});

it('★★★ CANLI iki varyantta ayni SKU REDDEDILIYOR — ham hata DEGIL', function () {
    $urun = benzersizlikUrunu();
    varyantEkle($urun, 'CZ-1', 'kirmizi');

    /*
    | ⚠️ İddia yalnızca "reddediliyor" değil, HANGİ istisnayla: ham
    | `UniqueConstraintViolationException` panelde 500 demek. Alan
    | hatasına çevrilebilen bir Domain istisnası olmalı.
    */
    expect(fn () => varyantEkle($urun, 'CZ-1', 'mavi'))
        ->toThrow(DuplicateSkuException::class);
});

it('★★★ SKU hatasi ALAN HATASI olarak donuyor — panel bunu gosterebilsin', function () {
    $urun = benzersizlikUrunu();
    varyantEkle($urun, 'CZ-1', 'kirmizi');

    try {
        varyantEkle($urun, 'CZ-1', 'mavi');
        $this->fail('istisna beklenmişti');
    } catch (DuplicateSkuException $hata) {
        // ⚠️ Anahtar `sku` OLMALI: panel hatayı ilgili kutunun altında
        // gösteriyor. Yanlış anahtar → marka hatayı hiç görmez.
        expect($hata->alanHatalari())->toHaveKey('sku')
            ->and($hata->alanHatalari()['sku'][0])->toContain('CZ-1');
    }
});

it('★★★ SKU kapsami MARKA geneli — baska urunde de cakisiyor', function () {
    $urun = benzersizlikUrunu();
    varyantEkle($urun, 'CZ-1', 'kirmizi');

    $ikinci = app(ProductService::class)->olustur(['title' => 'Kemer', 'brand' => 'Demo']);

    /*
    | ⚠️ Kontrol yalnızca ÜRÜN İÇİNDE arasaydı burası geçer, veritabanı
    | yine patlardı — kural iki yerde farklı olurdu.
    */
    expect(fn () => app(VariantService::class)->ekle($ikinci, ['sku' => 'CZ-1', 'price' => 50, 'stock' => 1], []))
        ->toThrow(DuplicateSkuException::class);
});

it('★★★ GUNCELLEME de kontrol ediyor — SKU ve SECENEK, ikisi de eksikti', function () {
    $urun = benzersizlikUrunu();
    varyantEkle($urun, 'CZ-1', 'kirmizi');
    $ikinci = varyantEkle($urun, 'CZ-2', 'mavi');

    expect(fn () => app(VariantService::class)->guncelle($ikinci, ['sku' => 'CZ-1']))
        ->toThrow(DuplicateSkuException::class);

    expect(fn () => app(VariantService::class)->guncelle($ikinci, ['sku' => 'CZ-2'], ['renk' => 'kirmizi']))
        ->toThrow(DuplicateVariantException::class);
});

it('★★ VARYANT KENDINI cakisma saymiyor — kendi degerleriyle kaydedilebiliyor', function () {
    $urun = benzersizlikUrunu();
    $v = varyantEkle($urun, 'CZ-1', 'kirmizi');

    /*
    | ⚠️ `haric` parametresi olmasaydı marka, fiyatı değiştirmek için
    | kaydettiğinde "bu SKU kullanılıyor" uyarısı alırdı — kendi SKU'su
    | yüzünden.
    */
    $guncel = app(VariantService::class)->guncelle($v, ['sku' => 'CZ-1', 'price' => 150], ['renk' => 'kirmizi']);

    expect((string) $guncel->price)->toBe('150.00');
});

it('★★★ PANELDE ham 500 DEGIL, SKU kutusunun altinda uyari — asil olculecek sey', function () {
    /*
    | ⚠️ Domain testi bunu GÖREMEZ: istisnanın panelde neye dönüştüğünü
    | ölçmüyor. 4.5L'de tam bu yüzden ham `duplicate key value violates
    | unique constraint` ekranda görünüyordu.
    */
    $marka = benzersizlikUrunu();
    $sahip = User::where('email', 'sahip@marka-a.test')->firstOrFail();

    varyantEkle($marka, 'CZ-1', 'kirmizi');

    $cevap = $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$marka->uuid}/varyantlar", [
            'sku' => 'CZ-1',
            'price' => 100,
            'stock' => 5,
            'options' => ['renk' => 'mavi'],
        ]);

    $cevap->assertRedirect()->assertSessionHasErrors('sku');

    expect(session('errors')?->first('sku'))->toContain('CZ-1');
});

it('★★★ PANELDEN silinen varyantin SKU su yeniden KULLANILABILIYOR', function () {
    $urun = benzersizlikUrunu();
    $sahip = User::where('email', 'sahip@marka-a.test')->firstOrFail();

    $ilk = varyantEkle($urun, 'CZ-1', 'kirmizi');
    app(VariantService::class)->sil($ilk);

    // ⚠️ Kullanıcının gerçekten yaptığı şey: panelden aynı SKU ile yeniden ekleme.
    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/varyantlar", [
            'sku' => 'CZ-1',
            'price' => 100,
            'stock' => 5,
            'options' => ['renk' => 'mavi'],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ProductVariant::where('sku', 'CZ-1')->count())->toBe(1);
});
