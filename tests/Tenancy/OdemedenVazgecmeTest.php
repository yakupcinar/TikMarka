<?php

use App\Domain\Order\CheckoutService;
use App\Domain\Settings\StorePublication;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Storefront\CartToken;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;

/*
| ÖDEMEDEN VAZGEÇME (4.6Z)
|
| ★ ÖLÇÜLEN BOŞLUK: ödeme ekranından çıkmanın TEMİZ bir yolu yoktu.
| Müşteri üst menüden başka sayfaya geçiyor, sipariş `pending` kalıyor ve
| bağlı stok 60 dakika kimseye satılamıyordu. "Hesabım"da iptal düğmesi
| vardı (4.5J) ama MİSAFİRİN oraya erişimi yok — misafir ödemesi açık
| olduğu için bu, müşterilerin bir bölümünün hiç çıkışı olmaması demekti.
|
| ⚠️ SAYFADAN AYRILINCA OTOMATİK İPTAL YAPILMIYOR. Müşteri meşru
| sebeplerle ayrılıyor (sözleşmeyi okumak, karta bakmak, banka SMS'i
| beklerken uygulama değiştirmek); otomatik iptal bunların hepsini
| sipariş kaybına çevirirdi. Terk edileni rezervasyon süresi topluyor.
*/

/** @return array{siparis: Order, varyant: ProductVariant} */
function vazgecmeSiparisi(): array
{
    $hazir = odemeAsamasiSiparisi('marka-a.test');
    app(StorePublication::class)->yayinla();

    return $hazir;
}

it('★★★ MISAFIR odemeden vazgecebiliyor — urunler sepete DONUYOR', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = vazgecmeSiparisi();
    $eskiSepet = Cart::firstOrFail();

    $this->withUnencryptedCookie(CartToken::CEREZ, (string) $eskiSepet->session_token)
        ->post("http://marka-a.test/odeme/ode/{$siparis->uuid}/iptal")
        ->assertRedirect('http://marka-a.test/sepet');

    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Cancelled);

    // ★ Mesaj değil SONUÇ ölçülüyor: ürün gerçekten sepette mi.
    $yeni = Cart::where('status', 'active')->latest('id')->firstOrFail();

    expect($yeni->items()->count())->toBe(1)
        ->and($yeni->items()->first()?->quantity)->toBe(2);
});

it('★★★ IPTAL STOGU SERBEST BIRAKIYOR — 60 dakika bekleyen kilit kalkiyor', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = vazgecmeSiparisi();

    /*
    | ⚠️ Bloğun asıl sebebi bu. Müşteri ekrandan çıkıp gittiğinde stok
    | rezervasyon süresi dolana kadar (ödeme başladıysa 60 dk) kimseye
    | satılamıyordu.
    */
    $sepet = Cart::firstOrFail();

    $this->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->post("http://marka-a.test/odeme/ode/{$siparis->uuid}/iptal")
        ->assertRedirect();

    /*
    | ⚠️ AKTİF durumların TAMAMI sorgulanıyor (`held` + `paying`), yalnızca
    | biri değil — `CheckoutService::rezervasyonlari()` ile aynı liste.
    | Tek durum yazılsaydı test, serbest bırakılmayan öteki durumu görmezdi.
    */
    $aktifRezervasyon = StockReservation::where('order_id', $siparis->id)
        ->whereIn('status', ReservationStatus::aktifDegerler())
        ->count();

    expect($aktifRezervasyon)->toBe(0)
        ->and($varyant->refresh()->stock)->toBe(5);
});

it('★★★ BASKASININ siparisi iptal EDILEMIYOR — 404', function () {
    ['siparis' => $siparis] = vazgecmeSiparisi();

    $musteri = Customer::create([
        'name' => 'Ayşe', 'email' => 'ayse@ornek.test', 'password' => bcrypt('sifre12345'),
    ]);

    /*
    | ⚠️ Sipariş MİSAFİRE ait (`customer_id` null). Giriş yapmış biri onu
    | iptal edememeli; `siparisiDogrula` kuralı ödeme sayfasıyla AYNI.
    */
    $this->post('http://marka-a.test/giris', [
        'email' => 'ayse@ornek.test', 'password' => 'sifre12345',
    ])->assertRedirect();

    $this->post("http://marka-a.test/odeme/ode/{$siparis->uuid}/iptal")->assertNotFound();

    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('★★★ ODENMIS siparis iptal EDILEMIYOR — ve mesaj YANILTMIYOR', function () {
    ['siparis' => $siparis] = vazgecmeSiparisi();
    $sepet = Cart::firstOrFail();

    app(CheckoutService::class)->odemeBasarili($siparis);

    $this->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->post("http://marka-a.test/odeme/ode/{$siparis->uuid}/iptal")
        ->assertRedirect('http://marka-a.test');

    /*
    | ⚠️ "İptal edildi" denip hiçbir şey yapmamak müşteriyi parasının geri
    | geldiğine inandırırdı. Mesaj iade yolunu gösteriyor.
    */
    expect((string) session('hata'))->toContain('iade')
        ->and($siparis->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('★★★ EKRANDAKI formun ADRESI POST kabul ediyor — tarayici gibi', function () {
    ['siparis' => $siparis] = vazgecmeSiparisi();
    $sepet = Cart::firstOrFail();

    $html = (string) $this->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->get("http://marka-a.test/odeme/ode/{$siparis->uuid}")
        ->assertOk()->getContent();

    expect($html)->toContain('Ödemeden vazgeç');

    /*
    | ⚠️ Adres SAYFADAN okunuyor — 4.6V'de form `route()` ile GET rotasını
    | üretmiş ve müşteri 405 almıştı. `method="post"` ile daraltıldı:
    | başlıktaki arama formu (`method="get"`) sayfada ÖNCE geliyor.
    */
    preg_match_all('/<form[^>]+method="post"[^>]+action="([^"]+)"/', $html, $eslesmeler);

    $iptal = array_values(array_filter(
        $eslesmeler[1],
        fn (string $adres): bool => str_contains($adres, '/iptal'),
    ));

    expect($iptal)->toHaveCount(1);

    $this->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->post(html_entity_decode($iptal[0]))
        ->assertRedirect('http://marka-a.test/sepet');
});

it('★★ SATISTAN KALKAN urun sessizce atlanmiyor', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = vazgecmeSiparisi();
    $sepet = Cart::firstOrFail();

    $varyant->is_active = false;
    $varyant->save();

    $this->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->post("http://marka-a.test/odeme/ode/{$siparis->uuid}/iptal")
        ->assertRedirect('http://marka-a.test/sepet');

    expect((string) session('mesaj'))->toContain('konulamadı');
});

it('★★★ IPTAL SONRASI odeme basarili gelirse marka UYARILIYOR — yaris bilinen risk', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = vazgecmeSiparisi();
    $sepet = Cart::firstOrFail();

    $this->withUnencryptedCookie(CartToken::CEREZ, (string) $sepet->session_token)
        ->post("http://marka-a.test/odeme/ode/{$siparis->uuid}/iptal")
        ->assertRedirect();

    /*
    | ⚠️ BU TEST BİR RİSKİ BELGELİYOR, bir özelliği değil. Müşteri iptal
    | ederken ödeme sağlayıcıda tamamlanmış olabilir. Ölçüldü: sipariş
    | `paid` oluyor ama stok DÜŞMÜYOR — ve `stock_shortfall` bayrağı
    | kalkıyor, yani marka panelde uyarı görüyor.
    |
    | Bu 1E-K5'te verilmiş kararın aynısı: sipariş reddedilmiyor (müşteriyi
    | 3-5 gün parasız bırakmamak için) ama SESSİZ de kalınmıyor.
    */
    app(CheckoutService::class)->odemeBasarili($siparis);

    expect($siparis->refresh()->stock_shortfall)->toBeTrue()
        ->and($varyant->refresh()->stock)->toBe(5);
});
