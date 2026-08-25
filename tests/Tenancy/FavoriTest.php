<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Favorite\FavoriteService;
use App\Domain\Privacy\Anonymizer;
use App\Domain\Privacy\DataExporter;
use App\Domain\Settings\StorePublication;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;

/*
| FAVORİLER (4.6D)
|
| ⚠️ Favori KİŞİSEL VERİDİR — "bu kişi neyi beğendi". Bu yüzden blok
| yalnızca ekran değil, KVKK yollarını da kapsıyor: anonimleştirme siliyor,
| veri dökümü listeliyor. Kapsanmasaydı müşteri başına veri tutan ama
| KVKK'ya girmeyen bir alan doğardı.
*/

/** @return array{musteri: Customer, urun: Product} */
function favoriHazir(string $eposta = 'favori@ornek.test'): array
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Deri Cüzdan', 'brand' => 'Demo']);
    app(VariantService::class)->ekle($urun, ['sku' => 'CZ-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $musteri = Customer::create(['name' => 'Ayşe Yılmaz', 'email' => $eposta, 'password' => bcrypt('sifre12345')]);
    $musteri->forceFill(['email_verified_at' => now()])->save();

    return ['musteri' => $musteri, 'urun' => $urun->refresh()];
}

it('★★★ TEK UC iki yonlu calisiyor — ekle, cikar', function () {
    ['musteri' => $musteri, 'urun' => $urun] = favoriHazir();
    favoriGirisi();

    $adres = "http://marka-a.test/urun/{$urun->slug}/favori";

    $this->post($adres)->assertRedirect();
    expect(Favorite::where('customer_id', $musteri->id)->count())->toBe(1);

    /*
    | ⚠️ Ayrı ekle/çıkar uçları olsaydı ekran hangisine gideceğini bilmek
    | için önce durumu okumak zorunda kalırdı ve iki istek arasında durum
    | değişebilirdi (iki sekme).
    */
    $this->post($adres)->assertRedirect();
    expect(Favorite::where('customer_id', $musteri->id)->count())->toBe(0);
});

it('★★★ MISAFIR favorileyemiyor — giris ekranina yonlendiriliyor', function () {
    ['urun' => $urun] = favoriHazir();

    /*
    | ⚠️ Middleware ŞART: controller'daki kontrol tek savunma bırakılsaydı,
    | rotanın bir gün başka bir controller'a bağlanması korumayı düşürürdü.
    */
    /*
    | ⚠️ Hedef `/giris` DEĞİL anasayfa: `redirectGuestsTo` vitrinde
    | bilinmeyen her kimliksiz isteği `url('/')`'e gönderiyor ve bu
    | mevcut davranış (hesap sayfaları da öyle). ⚠️ `assertRedirect()`
    | HEDEFSİZ çağrılmıyor — 4.5'te iki kez ısırdı: hedefsiz iddia,
    | yönlendirmenin NEREYE gittiğini hiç ölçmüyor.
    */
    $this->post("http://marka-a.test/urun/{$urun->slug}/favori")
        ->assertRedirect('http://marka-a.test');

    expect(Favorite::count())->toBe(0);
});

it('★★★ BASKASININ favorisi ETKILENMIYOR', function () {
    ['musteri' => $ilk, 'urun' => $urun] = favoriHazir('ilk@ornek.test');

    $ikinci = Customer::create(['name' => 'Zeynep', 'email' => 'ikinci@ornek.test', 'password' => bcrypt('sifre12345')]);
    $ikinci->forceFill(['email_verified_at' => now()])->save();

    app(FavoriteService::class)->degistir($ilk, $urun);
    app(FavoriteService::class)->degistir($ikinci, $urun);

    expect(Favorite::count())->toBe(2);

    // ⚠️ İkincinin çıkarması ilkininkini SİLMEMELİ.
    app(FavoriteService::class)->degistir($ikinci, $urun);

    expect(Favorite::where('customer_id', $ilk->id)->count())->toBe(1)
        ->and(Favorite::where('customer_id', $ikinci->id)->count())->toBe(0);
});

it('★★★ YAYINLANMAMIS urun favorilenemiyor — varligi da dogrulanmiyor', function () {
    ['urun' => $urun] = favoriHazir();
    favoriGirisi();

    app(ProductService::class)->durumDegistir($urun, ProductStatus::Draft);

    /*
    | ⚠️ Ham `Product::where('slug')` yazılsaydı adresi bilen biri taslak
    | ürünü favorileyebilir ve VARLIĞINI doğrulamış olurdu (1B-K10).
    */
    $this->post("http://marka-a.test/urun/{$urun->slug}/favori")->assertNotFound();

    expect(Favorite::count())->toBe(0);
});

it('★★★ SILINMIS urun LISTEDE gorunmuyor — ama VERI DOKUMUNDE var', function () {
    ['musteri' => $musteri, 'urun' => $urun] = favoriHazir();

    app(FavoriteService::class)->degistir($musteri, $urun);
    $urun->delete();

    /*
    | ⚠️ Liste bir AÇAN yol: silinmişi göstermemeli, yoksa tıklanınca 404
    | veren ölü kartlar çıkardı.
    */
    expect(app(FavoriteService::class)->listele($musteri))->toHaveCount(0);

    /*
    | ⚠️ Veri dökümünde TERSİ: soru "ne gösterelim" değil "elimizde ne
    | var". Gizlemek, KVKK'da veriyi eksik bildirmek olurdu.
    */
    $dokum = app(DataExporter::class)->musteriDokumü($musteri);

    expect($dokum['favoriler'])->toHaveCount(1);
});

it('★★★ ANONIMLESTIRME favorileri SILIYOR — maskelemiyor', function () {
    ['musteri' => $musteri, 'urun' => $urun] = favoriHazir();

    app(FavoriteService::class)->degistir($musteri, $urun);

    expect(Favorite::where('customer_id', $musteri->id)->count())->toBe(1);

    /*
    | ⚠️ Favorinin anonimleştirilecek bir ALANI yok: iki kolonu da kimlik.
    | Kişisel veri olan şey BAĞIN KENDİSİ — maskelenemez, silinmeli.
    |
    | ⚠️ Yabancı anahtardaki `cascadeOnDelete` burada DEVREYE GİRMİYOR:
    | anonimleştirme müşteriyi silmiyor, maskeliyor.
    */
    app(Anonymizer::class)->musteriyiAnonimlestir($musteri);

    expect(Favorite::where('customer_id', $musteri->id)->count())->toBe(0);
});

it('★★★ VERITABANI KISITI ayni urunu IKI KEZ yazdirmiyor — DOMAIN ATLANARAK', function () {
    ['musteri' => $musteri, 'urun' => $urun] = favoriHazir();

    app(FavoriteService::class)->degistir($musteri, $urun);

    /*
    | ⚠️ Servis DEĞİL doğrudan tablo kullanılıyor: kısıt Domain'in yedeği
    | değil SON SAVUNMASI ve Domain kontrolü onu maskeliyor (4.6X.1'de
    | ölçülmüştü). Yarış durumunda iki istek de kontrolü geçebilir.
    */
    expect(fn () => DB::table('favorites')->insert([
        'customer_id' => $musteri->id, 'product_id' => $urun->id,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('★★★ EKRANDAKI formun ADRESI POST kabul ediyor ve DURUM degisiyor', function () {
    ['urun' => $urun] = favoriHazir();
    favoriGirisi();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->toContain('Favorilere ekle')
        ->and($html)->toContain('aria-pressed="false"');

    /*
    | ⚠️ Adres SAYFADAN okunuyor — 4.6V'de form `route()` ile GET rotasını
    | üretmiş ve müşteri 405 almıştı. `method="post"` ile daraltıldı:
    | başlıktaki arama formu (`method="get"`) sayfada ÖNCE geliyor.
    */
    preg_match_all('/<form[^>]+method="post"[^>]+action="([^"]+)"/', $html, $eslesmeler);

    $favoriFormu = array_values(array_filter(
        $eslesmeler[1],
        fn (string $adres): bool => str_contains($adres, '/favori'),
    ));

    expect($favoriFormu)->toHaveCount(1);

    $this->post(html_entity_decode($favoriFormu[0]))->assertRedirect();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    // ★ Düğme DURUM DEĞİŞTİRDİ — mesaj değil EKRAN ölçülüyor.
    expect($html)->toContain('Favorilerimde')
        ->and($html)->toContain('aria-pressed="true"');
});

it('★★★ MISAFIRE dugme YOK, GIRIS baglantisi VAR', function () {
    ['urun' => $urun] = favoriHazir();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('Favorilere ekle')
        ->and($html)->toContain('favorilerinize ekleyin');
});

it('★★ FAVORI LISTESI misafire KAPALI, musteriye ACIK', function () {
    ['musteri' => $musteri, 'urun' => $urun] = favoriHazir();

    // ⚠️ Hesap sayfalarının tamamıyla aynı davranış: anasayfaya.
    $this->get('http://marka-a.test/hesabim/favoriler')->assertRedirect('http://marka-a.test');

    favoriGirisi();

    $this->get('http://marka-a.test/hesabim/favoriler')->assertOk()
        ->assertSee('Henüz favori ürününüz yok');

    app(FavoriteService::class)->degistir($musteri, $urun);

    $this->get('http://marka-a.test/hesabim/favoriler')->assertOk()->assertSee('Deri Cüzdan');
});

it('★★★ FAVORI ROTALARI auth middleware ARKASINDA — derinlemesine savunma', function () {
    /*
    | ⚠️ BU TEST BİR KIRMA DENEMESİNİN BULDUĞU BOŞLUKTAN DOĞDU.
    |
    | Rotadan `auth:customer-web`'i kaldırdım ve HİÇBİR test düşmedi:
    | controller'daki `instanceof Customer` kontrolü de misafiri durduruyor
    | ve ikisi de aynı yere yönlendiriyor (`back()` → referer yoksa
    | anasayfa). Yani davranış testi "korunuyor" diyordu ama NEYİN
    | koruduğunu ölçmüyordu.
    |
    | ⚠️ Middleware burada BİRİNCİL savunma değil, İKİNCİSİ — ve kalması
    | gerekiyor: controller bir gün değişirse ya da rota başka bir
    | controller'a bağlanırsa tek savunma düşerdi. Ölçülmezse sessizce
    | kaybolur.
    */
    markaKur('marka-a.test');

    $rotalar = array_values(Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

    foreach (['vitrin.urun.favori', 'vitrin.favoriler'] as $ad) {
        $rota = null;

        foreach ($rotalar as $aday) {
            if ($aday->getName() === $ad) {
                $rota = $aday;
                break;
            }
        }

        expect($rota)->toBeInstanceOf(Route::class);
        expect($rota?->gatherMiddleware() ?? [])->toContain('auth:customer-web');
    }
});
