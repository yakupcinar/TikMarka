<?php

use App\Domain\Settings\StorePublication;

/*
| GÜVENLİK BAŞLIKLARI (4.6U) — güvenlik taramasında bulunan boşluk.
|
| ★ Ölçüldü: `X-Frame-Options`, `Content-Security-Policy`,
| `X-Content-Type-Options`, `Referrer-Policy`, `Strict-Transport-Security`
| hiçbiri ne Laravel'de ne Caddy'de vardı.
|
| ⚠️ CSP DAR: yalnızca `frame-ancestors`. Ödeme iframe'inin (4.5-K1)
| kırılmadığı AYRICA ölçülüyor — geniş bir CSP yazılsaydı bu riski
| taşırdı.
*/

it('★★★ VITRIN sayfasi TUM guvenlik basliklarini taşıyor', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $cevap = $this->get('http://marka-a.test/');

    $cevap->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Strict-Transport-Security', 'max-age=15552000');
});

it('★★★ PANEL (Inertia) sayfasi da AYNI basliklari taşıyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->withoutVite()
        ->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
});

it('★★★ API (JSON) cevabi da AYNI basliklari taşıyor', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    /*
    | ⚠️ Yalnızca `web` grubuna eklenseydi bu uç korumasız kalırdı —
    | kırma denemesiyle ölçüldü.
    */
    $this->getJson('http://marka-a.test/api/products')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('★★★ KONTROL DUZLEMI (merkez) de AYNI basliklari taşıyor', function () {
    $this->withoutVite()
        ->get('http://localhost/yonetim/giris')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Strict-Transport-Security', 'max-age=15552000');
});

it('★★★ ODEME IFRAME SAYFASI kirilmiyor — CSP dar tutuldu', function () {
    /*
    | ★ ASIL RİSK BUYDU: geniş bir CSP, iyzico'nun dinamik
    | `paymentPageUrl`'ini `frame-src`'ye almadığı için ödeme çerçevesini
    | boş bırakabilirdi. Bu test o riskin GERÇEKTEN alınmadığını ölçüyor:
    | sayfa "frame-src" YAZMIYOR, yani tarayıcı iyzico'nun adresini
    | yüklemekte serbest.
    */
    $siparis = bildirimeHazirSiparis('marka-a.test')['siparis'];

    $cevap = $this->get("http://marka-a.test/odeme/ode/{$siparis->uuid}");

    $csp = $cevap->headers->get('Content-Security-Policy');

    expect($csp)->toBe("frame-ancestors 'self'")
        ->and($csp)->not->toContain('frame-src')
        ->and($csp)->not->toContain('default-src');
});
