<?php

use App\Domain\Catalog\DuplicateSkuException;
use App\Domain\Catalog\DuplicateVariantException;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| VARYANT BENZERSİZLİĞİ (4.6X) — gerçek kullanımda bulunan kusur.
|
| ★ Marka bir varyant açtı, sildi, aynı SKU ile yenisini açmak istedi ve
| ham `UniqueConstraintViolationException` gördü. Ölçünce üç boşluk çıktı:
|   1. `ekle()` SKU'yu HİÇ kontrol etmiyordu.
|   2. `guncelle()` İKİSİNİ DE kontrol etmiyordu.
|   3. `(product_id, options)` kısıtı silinmişleri de sayıyordu.
|
| ⚠️ SKU İLE SEÇENEK FARKLI DAVRANIYOR — bilerek:
|   · `sku` SİLİNMİŞLERİ DE kapsıyor. Kod markanın dış dünyayla ortak
|     dili (depo, kargo, muhasebe); yeniden kullanılırsa aynı kod iki
|     farklı fiziksel ürüne işaret eder, yani eski ürün yok sayılır.
|   · `(product_id, options)` KISMİ. O bir dış kimlik değil, "hangi
|     birleşim" sorusunun cevabı; sonsuza kadar rezerve edilseydi marka
|     "Kırmızı / M"yi silip bir daha ASLA açamazdı.
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

it('★★★ SILINEN varyantin SKU su REZERVE KALIYOR — ham hata DEGIL, anlasilir uyari', function () {
    $urun = benzersizlikUrunu();

    $ilk = varyantEkle($urun, 'CZ-1', 'kirmizi');
    app(VariantService::class)->sil($ilk);

    /*
    | ⚠️ İddia "reddediliyor" DEĞİL, "HANGİ İSTİSNAYLA reddediliyor":
    | ham `UniqueConstraintViolationException` panelde 500 demek. Kullanıcının
    | bugün gördüğü şey tam olarak oydu.
    */
    expect(fn () => varyantEkle($urun, 'CZ-1', 'mavi'))
        ->toThrow(DuplicateSkuException::class);

    expect(ProductVariant::withTrashed()->where('sku', 'CZ-1')->count())->toBe(1);
});

it('★★★ SILINMIS cakismanin MESAJI FARKLI — marka o SKU yu ekranda ARAYAMAZ', function () {
    $urun = benzersizlikUrunu();
    app(VariantService::class)->sil(varyantEkle($urun, 'CZ-1', 'kirmizi'));

    try {
        varyantEkle($urun, 'CZ-1', 'mavi');
        $this->fail('istisna beklenmişti');
    } catch (DuplicateSkuException $hata) {
        /*
        | ⚠️ Bu ayrım kozmetik DEĞİL. Çakışma silinmiş bir varyantlaysa
        | kayıt katalogda görünmüyor; "başka bir varyantta kullanılıyor"
        | denseydi marka olmayan bir şeyi arar, bulamaz ve hatayı sistem
        | arızası sanardı — gerçek kullanımda tam bu yaşandı.
        */
        expect($hata->silinmisVaryantta)->toBeTrue()
            ->and($hata->getMessage())->toContain('silinmiş');
    }
});

it('★★★ SILINEN varyantin SECENEGI SERBEST — SKU dan FARKLI, bilerek', function () {
    $urun = benzersizlikUrunu();

    $ilk = varyantEkle($urun, 'CZ-1', 'yesil');
    app(VariantService::class)->sil($ilk);

    /*
    | ⚠️ Bu yol 4.5L'de kapatıldı SANILIYORDU. Domain kontrolü silinmişi
    | görmüyordu (doğru), ama veritabanı kısıtı görüyordu (yanlış) —
    | ikisi uyuşmadığı için hata Domain'i atlayıp veritabanından geliyordu.
    |
    | ⚠️ SKU'dan FARKLI davranıyor ve bu bilinçli: seçenek birleşimi bir
    | DIŞ KİMLİK değil. Rezerve edilseydi marka "Yeşil" varyantını silip
    | bir daha asla açamazdı.
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

it('★★★ PANELDE silinen SKU uyarisi ALAN HATASI olarak gorunuyor', function () {
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
        ->assertSessionHasErrors('sku');

    // ⚠️ Ekranda "silinmiş" geçmeli: marka kodu katalogda arayamaz.
    expect(session('errors')?->first('sku'))->toContain('silinmiş');

    expect(ProductVariant::withTrashed()->where('sku', 'CZ-1')->count())->toBe(1);
});

it('★★★ VERITABANI KISITI da silinmisleri sayiyor — DOMAIN ATLANARAK olculuyor', function () {
    /*
    | ⚠️ BU TEST OLMADAN KISIT ÖLÇÜLMÜYORDU. Ölçüldü: migration'ı geri
    | almak (SKU'yu kısmi indekste bırakmak) HİÇBİR testi düşürmüyordu,
    | çünkü Domain kontrolü isteği veritabanına hiç ulaştırmıyor.
    |
    | Kısıt Domain'in yedeği değil SON SAVUNMASI: yarış durumunda iki
    | eşzamanlı istek de kontrolü geçip aynı anda yazmaya çalışabilir,
    | ayrıca tohumlayıcı/komut satırı Domain'i hiç kullanmayabilir.
    | Bu yüzden burada servis DEĞİL, doğrudan tablo kullanılıyor.
    */
    $urun = benzersizlikUrunu();
    $ilk = varyantEkle($urun, 'CZ-1', 'kirmizi');
    app(VariantService::class)->sil($ilk);

    expect(fn () => DB::table('product_variants')->insert([
        'uuid' => (string) Str::uuid(),
        'product_id' => $urun->id,
        'sku' => 'CZ-1',
        'options' => json_encode(['renk' => 'mavi']),
        'price' => 100,
        'stock' => 5,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('★★ VERITABANI KISITI secenekte silinmisi SAYMIYOR — SKU dan farkli, DOMAIN ATLANARAK', function () {
    /*
    | ⚠️ İki kuralın gerçekten FARKLI davrandığı burada kanıtlanıyor.
    | Aynı ölçüm Domain üzerinden yapılsaydı ikisinin de "geçtiğini"
    | görürdük ama SEBEBİNİ görmezdik.
    */
    $urun = benzersizlikUrunu();
    $ilk = varyantEkle($urun, 'CZ-1', 'yesil');
    app(VariantService::class)->sil($ilk);

    DB::table('product_variants')->insert([
        'uuid' => (string) Str::uuid(),
        'product_id' => $urun->id,
        'sku' => 'CZ-9',
        'options' => json_encode(['renk' => 'yesil']),
        'price' => 100,
        'stock' => 5,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(ProductVariant::where('sku', 'CZ-9')->exists())->toBeTrue();
});
