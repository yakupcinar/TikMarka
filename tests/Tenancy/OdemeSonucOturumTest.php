<?php

use App\Domain\Order\CheckoutService;
use App\Domain\Settings\StorePublication;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
| ÖDEME SONUÇ SAYFASI, KARGO POSTASI VE SAYFALAMA (4.6AI)
|
| ★ Üçü de kullanıcının bildirdiği kusurlar. İkisi göründüğünden ağır
| çıktı:
|
| 1. SONUÇ SAYFASI `api` GRUBUNDAYDI — oturum yok. Bildirilen belirti
|    "üst barda Hesabım yerine Giriş yazıyor"du; ölçülünce asıl bedel
|    çıktı: 4.6Y'de eklenen "Siparişimi görüntüle" düğmesinin koşulu
|    (`auth('customer-web')->id() === $siparis->customer_id`) ASLA
|    doğru olamıyordu, yani düğme HİÇ KİMSEYE çıkmıyordu — ve bu hata
|    vermiyordu.
|
| 2. KARGO POSTASI "Afiyet olsun!" diyordu — yemek uygulamasından kalma
|    bir cümle. TıkMarka genel bir e-ticaret altyapısı.
|
| 3. SAYFALAMA ileri/geri metinleri gösteriyordu; istenen sayılardı.
*/

function sonucSiparisi(): Order
{
    ['siparis' => $siparis] = odemeAsamasiSiparisi('marka-a.test');
    app(StorePublication::class)->yayinla();

    return $siparis;
}

// ─────────────────────────────────────────────────────────────────────
// 1 · Sonuç sayfası artık OTURUM görüyor
// ─────────────────────────────────────────────────────────────────────

it('★★★ SONUC SAYFASI web grubunda — oturum OLMADAN dugme cikmiyordu', function () {
    $rota = Route::getRoutes()->getByName('vitrin.odeme.sonuc');

    expect($rota)->not->toBeNull();

    // ⚠️ Statik analiz `getByName()` sonucunun null olabileceğini görüyor;
    //    yukarıdaki iddia çalışma anında yeterli ama analiz için daraltma şart.
    assert($rota instanceof Illuminate\Routing\Route);

    /*
    | ⚠️ `web` grubu OTURUMU getiriyor. `api`'de `StartSession` hiç yok
    | ve bu davranış testiyle ölçülemiyordu: test istemcisi oturumu
    | kendi taşıdığı için "giriş yapmış müşteri" senaryosu YEŞİL
    | görünüyordu (4A ve 4.6Y'de aynı aile).
    */
    expect($rota->gatherMiddleware())->toContain('web')
        ->and($rota->gatherMiddleware())->toContain('signed');

    /*
    | ⚠️ `magaza-acik` DIŞLANMIŞ olmalı: parasını ödemiş müşteri, marka
    | o sırada mağazasını kapattıysa "siparişiniz alındı" yerine 503
    | görürdü. Ödemenin sonucunu görmek mağazanın açık olmasına bağlı
    | olamaz.
    */
    expect($rota->excludedMiddleware())->toContain('magaza-acik');
});

it('★★★ GIRIS YAPMIS musteri "Siparisimi goruntule" dugmesini GORUYOR', function () {
    /*
    | ⚠️ MİSAFİR SİPARİŞİ İŞE YARAMAZ: `odemeAsamasiSiparisi` misafir
    | üretiyor ve düğmenin koşulu zaten `customer_id`'ye bağlı. Ölçülmek
    | istenen şey "oturum çözülüyor mu", o yüzden sipariş kayıtlı bir
    | müşteriye ait olmalı.
    */
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $musteri = Customer::factory()->create([
        'email' => 'alici@ornek.test',
        'password' => 'sifre1234',
    ]);

    ['siparis' => $siparis] = odemeAsamasiSiparisiMusteriyle('marka-a.test', $musteri);

    app(CheckoutService::class)->odemeBasarili($siparis);

    /*
    | ⚠️ Gerçek giriş isteği atılıyor, `actingAs` DEĞİL: `actingAs`
    | varsayılan guard'ı da değiştirdiği için kimliğin HANGİ guard'dan
    | çözüldüğünü gizler (4.5I'de iki kez ısırdı) — ve ölçülmek istenen
    | şey tam olarak o.
    */
    $this->post('http://marka-a.test/giris', [
        'email' => $musteri->email,
        'password' => 'sifre1234',
    ]);

    $this->get(sonucAdresi($siparis))
        ->assertOk()
        ->assertSee('Siparişimi görüntüle');
});

it('★★★ MAGAZA KAPALIYKEN de sonuc sayfasi aciliyor — 503 DEGIL', function () {
    $siparis = sonucSiparisi();

    app(CheckoutService::class)->odemeBasarili($siparis);

    // marka mağazasını kapatıyor
    app(StorePublication::class)->kapat();

    $this->get(sonucAdresi($siparis))
        ->assertOk()
        ->assertSee($siparis->order_number);
});

// ─────────────────────────────────────────────────────────────────────
// 2 · Kargo postası
// ─────────────────────────────────────────────────────────────────────

it('★★★ KARGO POSTASI yemek uygulamasi dili KULLANMIYOR', function () {
    $sablon = (string) File::get(base_path('resources/views/mail/shipment.blade.php'));

    // ⚠️ Yorumlar ayıklanıyor: kuralı ANLATAN yorum kuralın kendisiyle
    // aynı metni içeriyor (4.6AE'de iki kırma denemesi bu yüzden tutmadı).
    $kod = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $sablon);

    foreach (['Afiyet', 'afiyet', 'lezzet', 'sofra'] as $kelime) {
        expect($kod)->not->toContain($kelime);
    }

    // teslim dalı hâlâ bir şey SÖYLÜYOR olmalı — boşaltmak düzeltme değil
    expect($kod)->toContain('teslim edildi');
});

// ─────────────────────────────────────────────────────────────────────
// 3 · Sayfalama
// ─────────────────────────────────────────────────────────────────────

it('★★★ SAYFALAMA yalnizca SAYI gosteriyor ve ORTAK PARCA', function () {
    $bilesen = (string) File::get(base_path('resources/js/Panel/Components/Sayfalama.vue'));

    $kod = (string) preg_replace('/<!--.*?-->/s', '', $bilesen);
    $kod = (string) preg_replace('!/\*.*?\*/!s', '', $kod);

    // sayıyı seçen süzgeç
    expect($kod)->toMatch('/\^\\\\d\+\$/');

    /*
    | ⚠️ `v-html` KULLANILMAMALI. Eski kod Laravel'in HTML varlıklı
    | etiketleri (`&laquo;`) yüzünden onu yazmıştı; sayı ve "..." düz
    | metin olduğu için gerek yok ve sunucudan gelen metni HTML olarak
    | basmamak her hâlükârda daha iyi.
    */
    expect($kod)->not->toContain('v-html');

    /*
    | ⚠️ ORTAK PARÇA: aynı sayfalama DÖRT sayfada tekrarlanıyordu ve
    | ikisi farklı sınıflar kullanıyordu. 4.6A'nın dersi — kopya, aynı
    | hatanın bir sonraki tekrarını hazırlar.
    */
    $kopya = [];

    foreach (panelSayfalari() as $yol) {
        $icerik = (string) File::get($yol);

        if (str_contains($icerik, '.links"') && ! str_contains($icerik, '<Sayfalama')) {
            $kopya[] = basename(dirname($yol)).'/'.basename($yol);
        }
    }

    expect($kopya)->toBe([]);
});
