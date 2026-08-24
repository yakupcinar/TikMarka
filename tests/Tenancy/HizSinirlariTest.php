<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Http\Storefront\CartToken;

/*
| HIZ SINIRLAYICILARI: KUPON · YORUM · İADE (4.6T) — gerçek kullanımda
| taranan güvenlik boşlukları.
|
| ★ Ölçüldü: bu üç uç `giris`/`kayit`'ın aksine HİÇ sınırlanmamıştı.
|
|   kupon → misafire de açık, kod tahmin etmeye çalışan bir betiği
|            durduran tek şey bu limitti
|   yorum → yalnızca satın alan yazabiliyor ama bunu engelleyen şey
|            SATIN ALMA KAYDI, hız değil — throttle olmadan saniyede
|            onlarca yorum/spam mümkündü
|   iade  → OverReturnException fazla adedi engelliyor ama İSTEK
|            SUNUCUYA ULAŞTIKTAN SONRA; throttle olmadan aynı siparişe
|            saniyede onlarca istek atılabiliyordu
*/

function hizMagazasi(): void
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
}

it('★★★ KUPON denemesi ONBIRINCI istekte SINIRLANIYOR', function () {
    hizMagazasi();

    $varyant = app(VariantService::class)->ekle(
        app(ProductService::class)->olustur(['title' => 'X', 'brand' => 'D']),
        ['sku' => 'X-1', 'price' => 100, 'stock' => 5],
    );

    $ekleCevap = $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1]);
    $isimler = array_map(fn ($c) => $c->getName(), $ekleCevap->headers->getCookies());
    dump(['ekle_cerez_isimleri' => $isimler]);

    /*
    | ⚠️ 10/dakika sınırı — 11. istekte 429 beklenir. Test süresini
    | kısa tutmak için sınırı GEÇİCİ düşürmüyoruz: gerçek yapılandırmayı
    | ölçmek istiyoruz, test kolaylığı için sahte bir sayı değil.
    */
    for ($i = 0; $i < 10; $i++) {
        // ⚠️ Sayfa katmanında alan adı `kod` — API'deki `code` değil.
        $cevap = $this->post('http://marka-a.test/sepet/kupon', ['kod' => 'OLMAYAN-KOD']);
        dump(['durum' => $cevap->getStatusCode(), 'hedef' => $cevap->headers->get('Location')]);
    }

    $this->post('http://marka-a.test/sepet/kupon', ['kod' => 'OLMAYAN-KOD'])
        ->assertStatus(429);
});

it('★★★ IADE denemesi ONBIRINCI istekte SINIRLANIYOR', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = yorumaHazir('marka-a.test');

    /*
    | ⚠️ `yorumaHazir()` mağazayı YAYINLAMIYOR — `sevkiyatlikSiparis()`'in
    | temel kurulumu bu kadarıyla yetiniyor. Yayınlanmamış mağazada
    | `/giris` 503 döner (`RequirePublishedStore`); vitrinde iade
    | denemesi de vitrin isteği olduğu için aynı kapıdan geçiyor.
    */
    app(StorePublication::class)->yayinla();

    iadeciGirisi($musteri);

    $satir = $siparis->items()->firstOrFail();

    for ($i = 0; $i < 10; $i++) {
        $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iade", [
            'adetler' => [$satir->id => 0], // boş adet → sunucuda reddedilir ama İSTEK GEÇER
            'tur' => 'cayma',
        ])->assertSessionHas('hata');
    }

    $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iade", [
        'adetler' => [$satir->id => 0],
        'tur' => 'cayma',
    ])->assertStatus(429);
});

it('★★★ API KUPON UCU da AYNI limitten geçiyor — iki yol tek sayaç', function () {
    hizMagazasi();

    $varyant = app(VariantService::class)->ekle(
        app(ProductService::class)->olustur(['title' => 'X', 'brand' => 'D']),
        ['sku' => 'X-1', 'price' => 100, 'stock' => 5],
    );

    /*
    | ⚠️ API sepeti de KURULMALI — sepet yoksa uç `code` doğrulamasına
    | varmadan 404 döner ("sepet bulunamadı"). Sayfa katmanındaki
    | `/sepet/ekle` ile açılan sepet, aynı çerez üzerinden API'de de
    | görünüyor: CartResolver TEK YOL (4B/4.5J).
    */
    $token = $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1])
        ->getCookie(CartToken::CEREZ, false)?->getValue();

    /*
    | ⚠️ `postJson` ÇEREZLERİ VARSAYILAN GÖNDERMİYOR — `withCredentials()`
    | çağrılmadığı sürece `prepareCookiesForJsonRequest()` boş dizi
    | döndürüyor (`getJson`'ın çerezi düşürmesiyle aynı aile, 4A). Çerez
    | de elle veriliyor: yoksa sepet bulunamaz, 404 alınır ve throttle'ın
    | kendisi hiç ölçülmez.
    |
    | ⚠️ `throttle:kupon` hem sayfa hem API ucuna takıldı. Yalnızca birine
    | takılsaydı saldırgan diğerinden devam ederdi.
    */
    for ($i = 0; $i < 10; $i++) {
        $this->withCredentials()
            ->withUnencryptedCookie(CartToken::CEREZ, $token)
            ->postJson('http://marka-a.test/api/cart/coupon', ['code' => 'X'])
            ->assertStatus(422);
    }

    $this->withCredentials()
        ->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->postJson('http://marka-a.test/api/cart/coupon', ['code' => 'X'])
        ->assertStatus(429);
});

it('★★ FARKLI IP LER birbirini KILITLEMIYOR — kupon anahtari IP', function () {
    hizMagazasi();

    $varyant = app(VariantService::class)->ekle(
        app(ProductService::class)->olustur(['title' => 'X', 'brand' => 'D']),
        ['sku' => 'X-1', 'price' => 100, 'stock' => 5],
    );

    $token1 = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1])
        ->getCookie(CartToken::CEREZ, false)?->getValue();

    for ($i = 0; $i < 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withUnencryptedCookie(CartToken::CEREZ, $token1)
            ->post('http://marka-a.test/sepet/kupon', ['kod' => 'X'])
            ->assertRedirect('http://marka-a.test/sepet');
    }

    /*
    | ⚠️ Aynı anda BAŞKA bir IP'den gelen istek etkilenmemeli — yoksa
    | ortak ağdaki (kurumsal NAT) müşteriler birbirini kilitlerdi.
    | Kendi sepeti (kendi çerezi) üzerinden, on birinci değil İLK
    | isteğinde bile 429 ALMAMALI.
    */
    $token2 = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1])
        ->getCookie(CartToken::CEREZ, false)?->getValue();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->withUnencryptedCookie(CartToken::CEREZ, $token2)
        ->post('http://marka-a.test/sepet/kupon', ['kod' => 'X'])
        ->assertRedirect('http://marka-a.test/sepet');
});

/*
| ⚠️ YORUM: yalnızca TESLİM ALAN müşteri yazabiliyor (2E-K1) — kimlik
| zaten sanctum token'ıyla garanti. `teslimAlmisMusteri()` bu üç
| bloğun kesiştiği hazır durum (2E'de yazıldı).
*/
it('★★★ YORUM denemesi ALTINCI istekte SINIRLANIYOR', function () {
    $d = teslimAlmisMusteri('yor-hiz.test');
    app(StorePublication::class)->yayinla();

    $token = $d['musteri']->createToken('test')->plainTextToken;
    $slug = $d['urun']->slug;

    /*
    | ⚠️ 5/saat sınırı — 6. istekte 429. İlk istek gerçek bir yorum
    | yazıp `DuplicateReviewException`'a düşmemek için ayrı tutulmuyor:
    | limit istisnadan ÖNCE, middleware katmanında çalışıyor — ikinci
    | denemeden itibaren "zaten yorum yazdınız" (409) dönse de sayaç
    | işliyor.
    */
    for ($i = 0; $i < 5; $i++) {
        $this->withToken($token)
            ->postJson("http://yor-hiz.test/api/products/{$slug}/reviews", [
                'rating' => 5, 'body' => 'Deneme '.$i,
            ]);
    }

    $this->withToken($token)
        ->postJson("http://yor-hiz.test/api/products/{$slug}/reviews", ['rating' => 5, 'body' => 'Son deneme'])
        ->assertStatus(429);
});
