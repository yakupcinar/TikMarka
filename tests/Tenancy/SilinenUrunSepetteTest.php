<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Enums\ProductStatus;
use App\Http\Storefront\CartToken;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;

/*
| SİLİNEN ÜRÜN SEPETTE KALINCA (4.6AJ)
|
| ★ BİLDİRİLEN: "Vitrinde bir ürünü sepete aldım sonra panelde ürünü
| sildim; sepette varyant uuid alanı zorunludur hatası aldım, ürün üstü
| silik ve isimsiz duruyordu."
|
| ★ ÖLÇÜLEN BEDEL BUNDAN AĞIR: müşteri o satırı sepetinden ÇIKARAMIYORDU.
| İki bariyer birden vardı —
|
|   1. Ekran `value="{{ $satir->variant?->uuid }}"` basıyordu; varyant
|      yumuşak silindiği için ilişki `null` dönüyor ve alan BOŞ gidiyordu.
|   2. `satiriBul()` `whereHas('variant')` ile arıyor; silinmiş varyant
|      o sorguya hiç girmiyordu.
|
| Yani ürünü silen marka, müşterinin sepetini çalışamaz hâle getiriyordu.
|
| ★ KARAR — ve stratejinin kendisi DEĞİŞMEDİ. Proje zaten "sessizce silme,
| işaretle" diyordu (`kullanilabilirMi()` + ölü satır uyarısı) ve o doğru:
| müşterinin sepetinden bir şeyi habersiz çıkarmak "ürünüm nerede"
| sorusunu doğurur. Kırık olan strateji değil, işaretlenen satırın
| YÖNETİLEBİLİR olmamasıydı.
|
| ⚠️ Uygulanan kural projenin kendi kuralı (1E.6): bir kaydı KAPATAN yol
| (sepetten çıkarma) silinmişi de görmeli; AÇAN yol (sepete ekleme,
| ödemeye geçme) görmemeli.
*/

/** @return array{sepet: Cart, urun: Product, varyant: ProductVariant} */
function silinecekUrunluSepet(): array
{
    ['siparis' => $siparis, 'varyant' => $varyant] = ['siparis' => null, 'varyant' => null];

    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Kırmızı Tişört']);
    $varyant = app(VariantService::class)
        ->ekle($urun, ['sku' => 'KT-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    return ['sepet' => $sepet, 'urun' => $urun, 'varyant' => $varyant];
}

it('★★★ SILINEN urun sepetten CIKARILABILIYOR — sepet kilitlenmiyor', function () {
    ['sepet' => $sepet, 'urun' => $urun, 'varyant' => $varyant] = silinecekUrunluSepet();

    // marka ürünü panelden siliyor
    app(ProductService::class)->sil($urun->refresh());

    expect($varyant->refresh()->trashed())->toBeTrue();

    /*
    | ⚠️ Çerez elle gönderiliyor: misafir sepetinin kimliği orada
    | (`CartToken`). `getJson`/`postJson` çerezi düşürüyor (4A · 4.6T),
    | o yüzden `withCredentials()` + düz çerez.
    */
    $cevap = $this->withCredentials()
        ->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->post('http://marka-a.test/sepet/sil', ['variant_uuid' => $varyant->uuid]);

    $cevap->assertRedirect();

    expect(CartItem::where('cart_id', $sepet->id)->count())->toBe(0);
});

it('★★★ EKRAN uuid BASIYOR ve urun ADINI gosteriyor', function () {
    ['sepet' => $sepet, 'urun' => $urun, 'varyant' => $varyant] = silinecekUrunluSepet();

    app(ProductService::class)->sil($urun->refresh());

    $html = (string) $this->withCredentials()
        ->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->get('http://marka-a.test/sepet')
        ->assertOk()
        ->getContent();

    /*
    | ⚠️ ASIL İDDİA: alan BOŞ olmamalı. Boş `value=""` bastığı için
    | müşteri *"variant uuid alanı zorunludur"* alıyordu.
    */
    expect($html)->toContain('name="variant_uuid" value="'.$varyant->uuid.'"')
        ->and($html)->not->toContain('name="variant_uuid" value=""');

    // müşteri neyi çıkardığını görmeli
    expect($html)->toContain('Kırmızı Tişört');
});

it('★★★ SILINEN urun SATILAMAZ — withTrashed satilabilirligi ACMADI', function () {
    ['sepet' => $sepet, 'urun' => $urun] = silinecekUrunluSepet();

    app(ProductService::class)->sil($urun->refresh());

    $satir = CartItem::where('cart_id', $sepet->id)->firstOrFail();

    /*
    | ★ BU BLOĞUN EN TEHLİKELİ YERİ. İlişki artık silinmişi GÖRÜYOR;
    | `kullanilabilirMi()` açıkça `trashed()` bakmasaydı satır
    | "satılabilir" görünür, ödemeye geçiş onu geçirir ve katalogdan
    | kaldırılmış ürün satılırdı — hata vermeden.
    */
    expect($satir->kullanilabilirMi())->toBeFalse();

    $engeller = app(CartService::class)->engeller($sepet->refresh());

    expect($engeller)->toHaveCount(1);

    /*
    | ⚠️ SKU artık '?' değil GERÇEK değer: ilişki silinmişi gördüğü için
    | engel mesajı hangi ürünü kastettiğini söyleyebiliyor.
    */
    expect($engeller[0]['sku'])->toBe('KT-1');
});

it('★★ SILINEN varyant sepete YENIDEN EKLENEMIYOR — acan yol kapali', function () {
    ['urun' => $urun, 'varyant' => $varyant] = silinecekUrunluSepet();

    app(ProductService::class)->sil($urun->refresh());

    /*
    | ⚠️ "Kapatan yol silinmişi görür, AÇAN yol görmez" kuralının öteki
    | yarısı. Bu test olmasaydı `withTrashed()`'in sepete ekleme yoluna
    | sızıp sızmadığı ölçülmemiş olurdu.
    */
    $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
        'quantity' => 1,
    ])->assertNotFound();
});

it('★★★ YALNIZCA VARYANT silinince de SATILAMAZ — asil koruma burada', function () {
    /*
    | ★ BU TEST BİR KIRMA DENEMESİNİN AÇTIĞI BOŞLUKTAN DOĞDU.
    |
    | `trashed()` kontrolünü kaldıran deneme HİÇBİR testi düşürmedi.
    | Sebep: ürün SİLİNDİĞİNDE koruma başka yerden geliyor —
    | `ProductVariant::product()` silinmişi görmediği için
    | `product?->status === Active` zaten `false` oluyor.
    |
    | ⚠️ AMA MARKA TEK BİR VARYANTI DA SİLEBİLİYOR (`VariantService::sil`,
    | panelde varyant silme düğmesi). O durumda ÜRÜN HAYATTA: durumu hâlâ
    | `Active` ve `satinAlinabilirMi()` de geçebilir. `trashed()` kontrolü
    | olmasaydı SİLİNMİŞ BİR VARYANT SATILABİLİRDİ — ve bu hata vermezdi.
    |
    | Yani kontrol gereksiz değil; testler onu ölçmüyordu.
    */
    ['sepet' => $sepet, 'varyant' => $varyant] = silinecekUrunluSepet();

    // ⚠️ Ürün DEĞİL, yalnızca varyant siliniyor
    app(VariantService::class)->sil($varyant->refresh());

    $satir = CartItem::where('cart_id', $sepet->id)->firstOrFail();
    $satir->refresh()->load('variant.product');

    // ürün hâlâ ayakta — koruma ondan gelemez
    expect($satir->variant?->product?->status)->toBe(ProductStatus::Active);

    expect($satir->kullanilabilirMi())->toBeFalse();

    expect(app(CartService::class)->engeller($sepet->refresh()))->toHaveCount(1);
});
