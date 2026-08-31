<?php

use App\Domain\Notification\Notifier;
use App\Domain\Settings\StorePublication;
use App\Mail\OrderPaidMail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\DeliverableEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/*
| SEO ETİKETLERİ (B3) VE AYRILMIŞ UZANTIYA POSTA (B4)
|
| ★ 4.6G taraması bunu bir "rakip özelliği" olarak değil, KENDİ
| KARARIMIZIN YARIM KALMIŞ HÂLİ olarak çıkardı. Proje SEO için ÜÇ karar
| verdi — 4-K1 (sunucuda render), 4-K2 (SSR reddi), B2 (gerçek bağlantı)
| — ama ölçüldü:
|
|   sitemap.xml            404
|   rel="canonical"          0     ← B2'nin ?sayfa= adresleri kopya üretiyordu
|   property="og:*"          0     ← paylaşılan bağlantıda önizleme BOŞ
|   application/ld+json      0
|   robots.txt        varsayılan — panele ve ödemeye bile izin veriyor
|
| Yani SSR'ın bedeli ödeniyor, karşılığı alınmıyordu.
*/

function seoHazir(): Product
{
    $urun = seciciUrunu();
    app(StorePublication::class)->yayinla();

    return $urun;
}

it('★★★ CANONICAL var ve sayfa KENDINI isaret ediyor', function () {
    $urun = seoHazir();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->toContain('<link rel="canonical" href="http://marka-a.test/urun/'.$urun->slug.'">');
});

it('★★★ SAYFALI listede canonical KENDI sayfasini isaret ediyor', function () {
    seoHazir();
    bolumUrunleri(30);

    $html = (string) $this->get('http://marka-a.test/?sayfa=2')->assertOk()->getContent();

    /*
    | ⚠️ Hepsini 1. sayfaya işaret etmek YANLIŞ olurdu: 2. sayfadaki
    | ürünler o zaman hiçbir adreste "asıl" sayılmaz ve dizinden düşer.
    */
    expect($html)->toContain('rel="canonical" href="http://marka-a.test?sayfa=2"');
});

it('★★★ ARAMA sonucu DIZINE GIRMIYOR ama baglantilari izleniyor', function () {
    seoHazir();

    $html = (string) $this->get('http://marka-a.test/?q=Tişört')->assertOk()->getContent();

    /*
    | ⚠️ `follow` KALIYOR: sayfa dizine girmesin ama içindeki ürün
    | bağlantıları izlensin — aksi hâlde arama üzerinden gelen ürünler
    | de taranmaz olurdu.
    */
    expect($html)->toContain('content="noindex, follow"');

    // ve canonical'a `q` SIZMIYOR
    expect($html)->not->toContain('canonical" href="http://marka-a.test?q=');
});

it('★★★ OPEN GRAPH etiketleri var — paylasilan baglantida onizleme', function () {
    $urun = seoHazir();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->getContent();

    /*
    | ⚠️ D2C'de ürün Instagram/WhatsApp'ta paylaşılıyor. Bu etiketler
    | yokken önizleme TAMAMEN BOŞ çıkıyordu — niş bir özellik değil,
    | ana satış kanalı.
    */
    foreach (['og:site_name', 'og:type', 'og:url', 'og:title', 'og:description'] as $etiket) {
        expect($html)->toContain('property="'.$etiket.'"');
    }

    expect($html)->toContain('content="product"');
});

it('★★★ YAPISAL VERI gecerli JSON ve schema.org anahtarlari BOZULMAMIS', function () {
    $urun = seoHazir();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    expect($m[1] ?? '')->not->toBe('');

    $veri = json_decode((string) ($m[1] ?? ''), true);

    /*
    | ★ BU İDDİA BİR HATADAN DOĞDU. Yapısal veri ilk hâlinde Blade'de
    | üretiliyordu ve Blade `@context` anahtarını KENDİ YÖNERGESİ sanıp
    | PHP koduna çevirdi:
    |
    |     "<?php $__contextArgs = []; if (context()->has(...
    |
    | JSON geçersiz çıktı. Üretim PHP'ye taşındı.
    */
    expect($veri)->toBeArray()
        ->and($veri['@context'] ?? null)->toBe('https://schema.org')
        ->and($veri['@type'] ?? null)->toBe('Product');

    expect($veri['offers']['priceCurrency'] ?? null)->toBe('TRY');
});

it('★★★ FIYAT SATILABILIR varyanttan — tukenmis ucuz varyant YAZILMIYOR', function () {
    $urun = seoHazir();

    /*
    | ★ TÜKENMİŞ VARYANT DAHA UCUZ YAPILIYOR — ve bu satır bir kırma
    | denemesinin açtığı boşluktan geldi.
    |
    | ⚠️ `seciciUrunu()` bütün varyantları AYNI fiyatta (100) açıyor;
    | o hâlde "tüm varyantların min'i" ile "satılabilir varyantların
    | min'i" aynı çıkıyor ve fiyatı yanlış kaynaktan alan deneme hiçbir
    | testi düşürmüyordu.
    |
    | Stoksuz varyantı 40'a indirince ayrım ölçülebilir hâle geliyor:
    | yanlış kaynak 40, doğru kaynak 100 verir.
    */
    $stoksuz = $urun->variants->first(fn ($v) => ! $v->satinAlinabilirMi());

    expect($stoksuz)->not->toBeNull();

    assert($stoksuz instanceof ProductVariant);

    DB::table('product_variants')->where('id', $stoksuz->id)->update(['price' => 40]);

    $urun->refresh()->load('variants');

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    expect($m[1] ?? '')->not->toBe('');

    $veri = json_decode((string) ($m[1] ?? ''), true);

    $satilabilirEnDusuk = $urun->variants
        ->filter(fn ($v) => $v->satinAlinabilirMi())
        ->min('price');

    expect($veri['offers']['price'])->toBe(number_format((float) $satilabilirEnDusuk, 2, '.', ''))
        ->and($veri['offers']['availability'])->toBe('https://schema.org/InStock');
});

it('★★★ SITEMAP gecerli XML ve YALNIZCA bu markanin adresleri', function () {
    $urun = seoHazir();

    $cevap = $this->get('http://marka-a.test/sitemap.xml')->assertOk();

    expect($cevap->headers->get('content-type'))->toContain('application/xml');

    $xml = simplexml_load_string((string) $cevap->getContent());

    expect($xml)->not->toBeFalse();

    assert($xml instanceof SimpleXMLElement);

    $adresler = [];

    foreach ($xml->url as $u) {
        $adresler[] = (string) $u->loc;
    }

    expect($adresler)->not->toBeEmpty();

    // ⚠️ Her adres BU markanın alan adında olmalı
    foreach ($adresler as $adres) {
        expect($adres)->toStartWith('http://marka-a.test');
    }

    expect($adresler)->toContain("http://marka-a.test/urun/{$urun->slug}");
});

it('★★★ ROBOTS panel ve odemeyi DISARIDA birakiyor, sitemap gosteriyor', function () {
    seoHazir();

    $govde = (string) $this->get('http://marka-a.test/robots.txt')->assertOk()->getContent();

    /*
    | ⚠️ Varsayılan `robots.txt` HER ŞEYE izin veriyordu — panel giriş
    | ekranı dâhil. `Disallow` bir güvenlik aracı DEĞİL (korumayı
    | middleware yapıyor); amaç motorun boşa gezmemesi.
    */
    foreach (['/yonetim', '/sepet', '/odeme', '/hesabim'] as $yol) {
        expect($govde)->toContain('Disallow: '.$yol);
    }

    expect($govde)->toContain('Sitemap: http://marka-a.test/sitemap.xml');
});

it('★★★ STATIK robots.txt KALDIRILDI — yoksa rota hic calismaz', function () {
    /*
    | ⚠️ Caddy `public/` altındaki statik dosyayı rotadan ÖNCE sunuyor.
    | Dosya dursaydı bütün markalar aynı `robots.txt`i görür ve sitemap
    | adresi yanlış alan adını taşırdı.
    */
    expect(file_exists(base_path('public/robots.txt')))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────
// B4 · ayrılmış uzantıya posta çıkmıyor
// ─────────────────────────────────────────────────────────────────────

it('★★★ COZULEMEZ alan adina POSTA GONDERILMIYOR', function () {
    /*
    | ⚠️ GERÇEK sipariş kuruluyor: `Order` fabrikası yok ve `email`
    | `$fillable`da değil (sipariş kendi kopyasını taşıyor, 1D). Test
    | o korumayı delmek yerine tabloya yazıyor.
    */
    ['siparis' => $siparis] = odemeAsamasiSiparisi('marka-a.test');

    DB::table('orders')->where('id', $siparis->id)
        ->update(['email' => 'vazgec@marka-a.localhost']);

    Mail::fake();

    app(Notifier::class)->siparisOnayi($siparis->refresh());

    /*
    | ★ Kullanıcının "Address not found" iadesinden çıktı. RFC 6761
    | `.localhost`u ASLA çözülmemek üzere ayırmış; posta göndermek tanımı
    | gereği boşa gidiyor ve her deneme gerçek hesapta bir iade
    | biriktiriyor.
    */
    Mail::assertNothingQueued();
});

it('★★★ RFC 2606 ADLARI DA ELENIYOR — uzanti taramasi bunlari GORMUYOR', function () {
    /*
    | ★ BU TEST BİR BOŞLUKTAN DOĞDU. İlk hâlde eleme yalnızca UZANTIYA
    | bakıyordu; `example.com` `.com` uzantısında olduğu için geçiyordu.
    | Oysa RFC 2606 bu üç adı belgeleme için ayırmış — MX kaydı yok,
    | posta teslim edilmiyor. Kuralı yazıp bunları atlamak kuralı yarım
    | uygulamak olurdu.
    |
    | ⚠️ Test verisinin ÇOĞU `@example.com` kullanıyor; yani bu eleme
    | olmasaydı geliştirmede her sipariş bir teslim edilemez posta
    | denemesi üretirdi.
    */
    ['siparis' => $siparis] = odemeAsamasiSiparisi('marka-a.test');

    DB::table('orders')->where('id', $siparis->id)
        ->update(['email' => 'musteri@example.com']);

    Mail::fake();

    app(Notifier::class)->siparisOnayi($siparis->refresh());

    Mail::assertNothingQueued();
});

it('★★★ GERCEK alan adina posta GONDERILIYOR — eleme fazla genis degil', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('marka-a.test');

    DB::table('orders')->where('id', $siparis->id)
        ->update(['email' => 'musteri@gmail.com']);

    Mail::fake();

    app(Notifier::class)->siparisOnayi($siparis->refresh());

    Mail::assertQueued(OrderPaidMail::class);
});

it('★★ DOGRULAMA KURALI SIKILASTIRILMADI — test verisi kirilmasin', function () {
    /*
    | ⚠️ Karar: eleme DOĞRULAMADA değil GÖNDERİMDE. Bütün test verisi
    | `@ornek.test` kullanıyor; kural sıkılaştırılsaydı süit kırılırdı
    | ve gerçek müşteri de yazım hatası yaptığında sipariş veremezdi.
    */
    $dogrulayici = Validator::make(
        ['email' => 'a@ornek.com'],
        ['email' => ['email', new DeliverableEmail]],
    );

    expect($dogrulayici->passes())->toBeTrue();
});
