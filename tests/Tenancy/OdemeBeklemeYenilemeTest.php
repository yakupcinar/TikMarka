<?php

use App\Domain\Order\CheckoutService;
use App\Domain\Settings\StorePublication;
use App\Models\Order;

/*
| ÖDEME BEKLEME EKRANI OTOMATİK YENİLENİYOR (4.6AK)
|
| ★ BİLDİRİLEN: "ödemeyi yapıp siparişi veriyorum, çıkan ekranda
| bekliyorum, sonra sayfayı yeniledim o zaman ödeme başarılı dedi."
|
| ★ SEBEP: sağlayıcı bildirimi 10-15 saniye sürüyor (1E.7.3'te ölçüldü);
| müşteri sonuç sayfasına 3 saniyede varabiliyor. O aralıkta ekran ÖLÜ
| kalıyordu — hiçbir şey olmuyor, müşteri ödemesinin akıbetini bilmiyor
| ve ancak elle yenileyince öğreniyordu.
|
| ⚠️ `processing` = "bildirim HENÜZ GELMEDİ", "başarısız" DEĞİL. Bu ayrım
| 4.6Y'de kurulmuştu ve korunuyor.
*/

function beklemedekiSiparis(): Order
{
    ['siparis' => $siparis] = odemeAsamasiSiparisi('marka-a.test');
    app(StorePublication::class)->yayinla();

    return $siparis;
}

it('★★★ BEKLEME ekrani KENDINI YENILIYOR', function () {
    $siparis = beklemedekiSiparis();

    $html = (string) $this->get(sonucAdresi($siparis))->assertOk()->getContent();

    expect($html)->toContain('location.reload()')
        ->and($html)->toContain('sayfa kendini yenileyecek');
});

it('★★★ YENILEME SINIRLI — sonsuz dongu yok', function () {
    $siparis = beklemedekiSiparis();

    $html = (string) $this->get(sonucAdresi($siparis))->getContent();

    /*
    | ⚠️ SINIR ŞART. Ödeme kalıcı olarak `pending` kalabiliyor (sağlayıcı
    | bildirimi hiç gelmezse); sınırsız yenileme müşterinin sekmesini
    | sonsuza kadar döndürür ve sunucuya boşuna yük bindirir.
    */
    expect($html)->toContain('EN_COK')
        ->and($html)->toMatch('/EN_COK\s*=\s*\d+/');

    // süre dolunca gösterilecek metin sayfada HAZIR olmalı
    expect($html)->toContain('Onay hâlâ gelmedi');
});

it('★★★ SAYAC ADRESE KONMUYOR — imza sorgu dizesini de kapsiyor', function () {
    $siparis = beklemedekiSiparis();

    $kod = sonucKodu($siparis);

    /*
    | ⚠️ BU TESTİN VARLIK SEBEBİ BİR TUZAK: sayaç bir sorgu
    | parametresiyle taşınsaydı İMZA GEÇERSİZ olur ve müşteri ödemesinin
    | sonucu yerine 403 görürdü.
    */
    expect($kod)->toContain('sessionStorage')
        ->and($kod)->not->toMatch('/location\.href\s*\+=|[?&]deneme=/');
});

it('★★★ DEPO YOKSA yenileme HIC calismiyor — sonsuz dongu riski', function () {
    $siparis = beklemedekiSiparis();

    $html = (string) $this->get(sonucAdresi($siparis))->getContent();

    /*
    | ⚠️ Gizli sekmede `sessionStorage` istisna atabiliyor. Sayaç
    | tutulamayınca sayfa SONSUZA KADAR kendini yenilerdi. O durumda
    | otomatik yenileme hiç başlamıyor, elle yenileme notu çıkıyor.
    */
    expect($html)->toContain('depo === null');
});

it('★★★ TERMINAL durumda yenileme YOK ve sayac TEMIZLENIYOR', function () {
    $siparis = beklemedekiSiparis();

    app(CheckoutService::class)->odemeBasarili($siparis);

    $html = (string) $this->get(sonucAdresi($siparis->refresh()))->assertOk()->getContent();

    // ödeme bitti — yenilemeye gerek yok
    expect($html)->not->toContain('location.reload()');

    /*
    | ⚠️ Sayaç temizlenmezse müşteri aynı tarayıcı oturumunda İKİNCİ bir
    | ödeme yaptığında sayaç dolu başlar ve o sipariş için otomatik
    | yenileme HİÇ çalışmaz.
    */
    expect($html)->toContain('removeItem');
});
