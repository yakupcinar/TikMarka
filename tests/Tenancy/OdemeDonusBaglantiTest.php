<?php

use App\Domain\Order\CheckoutService;
use App\Domain\Settings\StorePublication;
use App\Enums\PaymentStatus;
use App\Http\Storefront\CartToken;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
| ÖDEME DÖNÜŞ EKRANI — DURUMA GÖRE BAĞLANTILAR (4.6Y)
|
| ★ Ekran 4.5R'de yazıldı ve durumu üçe ayırıyordu (paid/failed/processing)
| ama üç dalda da TEK bir "Alışverişe devam et" bağlantısı vardı:
|   · ödemesi başarılı müşteri siparişini göremiyordu,
|   · başarısız olan ise elinde HİÇBİR ŞEY kalmadan mağazaya atılıyordu.
|
| ⚠️ "Sepete dön" bağlantısı KOYULAMAZDI — ölçüldü: `baslat()` sepeti
| `converted` yapıyor, `odemeBasarisiz()` geri almıyor ve `CartService`
| yalnızca `active` sepet arıyor. Yani sepet sayfası BOŞ görünüyor.
| Siparişi yeniden ödemek de mümkün değil: `ode()` ve
| `PaymentService::baslat()` ikisi de yalnızca `pending` kabul ediyor,
| üstelik stok serbest bırakılmış. Bu yüzden ürünler GERİ KONULUYOR.
*/

function donusSiparisi(): Order
{
    ['siparis' => $siparis] = odemeAsamasiSiparisi('marka-a.test');
    app(StorePublication::class)->yayinla();

    return $siparis;
}

it('★★★ BASARISIZ odemede sepet BOS kaliyor — baglantinin varlik sebebi', function () {
    $siparis = donusSiparisi();
    $sepet = Cart::firstOrFail();

    app(CheckoutService::class)->odemeBasarisiz($siparis);

    /*
    | ⚠️ Bu test bir ÖZELLİĞİ değil bir GEREKÇEYİ koruyor. Biri "sepete dön
    | bağlantısı koyalım" derse burası ona sepetin boş olduğunu gösterir.
    */
    expect($sepet->refresh()->status->value)->toBe('converted');

    $html = (string) $this->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->get('http://marka-a.test/sepet')->getContent();

    expect($html)->not->toContain('Tişört');
});

it('★★★ BASARISIZ dalda URUNLERI GERI KOY formu var ve adresi POST kabul ediyor', function () {
    $siparis = donusSiparisi();
    app(CheckoutService::class)->odemeBasarisiz($siparis);

    $html = (string) $this->get(sonucAdresi($siparis))->assertOk()->getContent();

    /*
    | ⚠️ Adres SAYFADAN okunuyor — 4.6V'de form `route()` ile GET rotasını
    | üretmiş ve müşteri 405 almıştı; testler görmemişti çünkü doğrudan
    | doğru adrese POST ediyorlardı.
    |
    | ⚠️ `method="post"` ile daraltıldı: düzenin başlığındaki arama formu
    | (`method="get"`) sayfada ÖNCE geliyor.
    */
    preg_match('/<form[^>]+method="post"[^>]+action="([^"]+)"/', $html, $eslesme);
    $adres = $eslesme[1] ?? '';

    expect($adres)->toContain('sepete-geri');

    $this->post(html_entity_decode($adres))
        ->assertRedirect('http://marka-a.test/sepet')
        ->assertSessionHasNoErrors();

    // ★ Ürün gerçekten sepette mi — mesaj değil SONUÇ ölçülüyor.
    $yeni = Cart::where('status', 'active')->latest('id')->firstOrFail();
    expect($yeni->items()->count())->toBe(1)
        ->and($yeni->items()->first()?->quantity)->toBe(2);
});

it('★★★ SEPETE GERI KOY IMZASIZ calismiyor — misafir odemesinde tek koruma bu', function () {
    $siparis = donusSiparisi();
    app(CheckoutService::class)->odemeBasarisiz($siparis);

    /*
    | ⚠️ Kimlik yok (misafir ödemesi açık), sahipliği kanıtlayan tek şey
    | imza. İmzasız kalsaydı uuid'i bilen biri başkasının siparişini
    | istediği zaman sepete doldurabilirdi.
    */
    $this->post("http://marka-a.test/odeme/sonuc/{$siparis->uuid}/sepete-geri")
        ->assertForbidden();

    expect(Cart::where('status', 'active')->count())->toBe(0);
});

it('★★★ ODENMIS siparis sepete geri KONULAMIYOR', function () {
    $siparis = donusSiparisi();
    app(CheckoutService::class)->odemeBasarili($siparis);

    /*
    | ⚠️ Ödenmiş siparişte çalışsaydı müşteri ödediği ürünleri yeniden
    | satın almaya yönlendirilirdi. `pending`'de ise stok iki kez
    | bağlanırdı: rezervasyon duruyor, üstüne sepet.
    */
    $this->post(sepeteGeriAdresi($siparis))->assertRedirect('http://marka-a.test');

    expect(Cart::where('status', 'active')->count())->toBe(0);
});

it('★★★ ALINAMAYAN urun SESSIZCE atlanmiyor — musteriye soyleniyor', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi('marka-a.test');
    app(StorePublication::class)->yayinla();
    app(CheckoutService::class)->odemeBasarisiz($siparis);

    // Ürün satıştan kalktı — sepete konamaz.
    $varyant->is_active = false;
    $varyant->save();

    $this->post(sepeteGeriAdresi($siparis))->assertRedirect('http://marka-a.test/sepet');

    /*
    | ⚠️ "Sepetiniz geri geldi" deyip eksik sepet göstermek müşteriyi
    | ödeme adımında İKİNCİ KEZ şaşırtırdı.
    */
    expect((string) session('mesaj'))->toContain('eklenemedi')
        ->and((string) session('mesaj'))->toContain('Tişört');
});

it('★★★ BASARILI dalda siparis baglantisi SADECE SAHIBINE gorunuyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('marka-a.test');
    app(StorePublication::class)->yayinla();
    app(CheckoutService::class)->odemeBasarili($siparis);

    /*
    | ⚠️ MİSAFİR ÖDEMESİ AÇIK. Koşulsuz bağlantı konsaydı misafir önce
    | giriş ekranına, oradan da 404'e giderdi — çünkü sipariş detayı
    | `customer_id` eşleşmesi arıyor.
    */
    expect($siparis->customer_id)->toBeNull();

    $html = (string) $this->get(sonucAdresi($siparis))->assertOk()->getContent();

    expect($html)->not->toContain('Siparişimi görüntüle')
        ->and($html)->toContain($siparis->order_number);
});

it('★★★ SAHIBI GIRIS YAPMISSA siparis baglantisi GORUNUYOR ve CALISIYOR', function () {
    /*
    | ⚠️ Müşteri MARKA KURULDUKTAN SONRA yaratılıyor: `Customer` marka
    | şemasında yaşıyor, kiracı başlatılmadan `customers` tablosu yok.
    */
    markaKur('marka-a.test');
    magazayiHazirla();

    $musteri = Customer::create([
        'name' => 'Ayşe', 'email' => 'ayse@ornek.test', 'password' => bcrypt('sifre12345'),
    ]);

    ['siparis' => $siparis] = odemeAsamasiSiparisiMusteriyle('marka-a.test', $musteri);
    app(StorePublication::class)->yayinla();
    app(CheckoutService::class)->odemeBasarili($siparis);

    $this->post('http://marka-a.test/giris', [
        'email' => 'ayse@ornek.test', 'password' => 'sifre12345',
    ])->assertRedirect();

    $html = (string) $this->get(sonucAdresi($siparis))->assertOk()->getContent();

    expect($html)->toContain('Siparişimi görüntüle');

    // ★ Bağlantı ÇALIŞIYOR mu — `assertRedirect()` hedefsiz çağrılmıyor (4.5 dersi).
    preg_match('/href="([^"]*hesabim\/siparis[^"]*)"/', $html, $eslesme);
    $baglanti = $eslesme[1] ?? '';

    expect($baglanti)->not->toBe('');

    $this->get(html_entity_decode($baglanti))->assertOk();
});

it('★★ ISLENIYOR dalinda ek baglanti YOK — durum belli degilken yonlendirme yapilmaz', function () {
    $siparis = donusSiparisi();

    expect($siparis->payment_status)->toBe(PaymentStatus::Pending);

    $html = (string) $this->get(sonucAdresi($siparis))->assertOk()->getContent();

    /*
    | ⚠️ `processing` = "bildirim HENÜZ GELMEDİ", başarısız DEĞİL (4.5R).
    | "Ürünleri sepete geri koy" gösterilseydi ödemesi yolda olan müşteri
    | stoğu ikinci kez bağlar ve iki kez ödemeye çalışırdı.
    */
    expect($html)->not->toContain('sepete geri koy')
        ->and($html)->not->toContain('Siparişimi görüntüle');
});

it('★★★ SEPETE GERI KOY rotasi OTURUMLU grupta — flash mesaji kaybolmasin', function () {
    /*
    | ⚠️ BU TEST GERÇEK CURL'ÜN BULDUĞU BİR KUSURDAN DOĞDU.
    |
    | Rota önce `api` grubundaydı (sonuç sayfasıyla aynı yerde). Ürün
    | sepete geliyordu ama "şunlar eklenemedi" uyarısı MÜŞTERİYE HİÇ
    | ULAŞMIYORDU: `api` grubunda `StartSession` yok, flash mesajı
    | yazıldığı anda kayboluyor.
    |
    | ⚠️ DAVRANIŞ TESTİ BUNU GÖREMEZ: test istemcisi oturumu ayakta
    | tutuyor ve `session('mesaj')` yeşil dönüyordu. `getJson`'ın çerezi
    | düşürmesiyle (4A) aynı aile — test ortamı ölçmek istediğin şeyi
    | ortadan kaldırıyor. Bu yüzden iddia DAVRANIŞA değil ROTANIN
    | MIDDLEWARE LİSTESİNE bakıyor.
    */
    markaKur('marka-a.test');

    $rotalar = array_values(Route::getRoutes()->getRoutes());

    $rota = null;

    foreach ($rotalar as $aday) {
        if ($aday->getName() === 'vitrin.odeme.sepeteGeri') {
            $rota = $aday;
            break;
        }
    }

    expect($rota)->not->toBeNull();

    $middleware = $rota?->gatherMiddleware() ?? [];

    /*
    | ⚠️ `gatherMiddleware()` grup adını GENİŞLETMİYOR — `web` olarak
    | döndürüyor, `StartSession` diye aramak boşa çıkıyor. Aranan şey
    | rotanın hangi GRUPTA olduğu; oturum o gruptan geliyor.
    */
    expect($middleware)->toContain('web')
        ->and($middleware)->toContain('signed')
        ->and($middleware)->not->toContain('api');
});

it('★★★ SEPETE GERI KOY rotasi CSRF ten MUAF — formu render eden sayfa jeton uretemiyor', function () {
    /*
    | ⚠️ Muafiyet bir zafiyet DEĞİL, korumanın YER DEĞİŞTİRMESİ: sayfa
    | `api` grubunda render ediliyor (sağlayıcı POST ediyor, 4.5R) ve
    | oturumu olmadığı için CSRF jetonu üretemiyor. Yerine `signed` var ve
    | o daha güçlü — isteğin bizden geldiğini DEĞİL, isteği yapanın O
    | SİPARİŞE ait bağlantıyı bildiğini kanıtlıyor.
    |
    | ⚠️ İstisnanın DAR kaldığını da ölçüyoruz: genişlerse sepet ve ödeme
    | uçları korumasız kalırdı.
    */
    markaKur('marka-a.test');

    $muaflar = app(ValidateCsrfToken::class);

    /*
    | ⚠️ Liste `except` DEĞİL `neverVerify` özelliğinde: `validateCsrfTokens()`
    | oraya yazıyor. `except`'e bakan bir test BOŞ dizi görür ve muafiyeti
    | hiç ölçmeden yeşil kalırdı.
    */
    $ozellik = (new ReflectionClass($muaflar))->getProperty('neverVerify');
    $ozellik->setAccessible(true);

    /** @var list<string> $liste */
    $liste = $ozellik->getValue($muaflar);

    expect($liste)->toContain('odeme/sonuc/*/sepete-geri')
        ->and($liste)->toHaveCount(1);
});
