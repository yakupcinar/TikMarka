<?php

use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\EventType;
use App\Enums\SettingGroup;
use App\Http\Storefront\HomeController;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| ANA SAYFA: TEMBEL YÜKLEME VE SAYFALAMA (B2)
|
| ★ İSTENEN: "ana sayfada çok fazla ürün listeleneceği için aşağı
| kaydırdıkça ürünlerin yüklenmesi".
|
| ★ ÖLÇÜM İSTEĞİ İKİYE AYIRDI:
|
|   1. HIZ — `loading="lazy"` HİÇ YOKTU. Ölçüldü: 30 görsel, ~284 KB,
|      hepsi istekli iniyordu. Bu ölçekte sayfa yavaş değil (57 KB HTML,
|      0,5 sn) ama eksik olan şey ölçekle büyüyor.
|
|   2. EKSİKLİK — ana sayfa `limit(24)` ile SESSİZCE kesiliyordu.
|      25. ürün ana sayfadan hiç görünmüyordu ve bunu söyleyen bir şey
|      de yoktu. Asıl kusur hız değil BUYDU.
|
| ⚠️ SAF SONSUZ KAYDIRMA YAZILMADI: vitrin sunucuda render ediliyor
| (4-K1) ve tek sebebi SEO. Ürünler yalnızca JavaScript'le gelseydi
| 25. üründen sonrası taranamaz olurdu.
*/

function tembelHazir(int $urunSayisi): void
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
    Cache::flush();

    $urunler = bolumUrunleri($urunSayisi);

    /*
    | ⚠️ ÜRÜNLERE GÖRSEL VERİLİYOR — ve bu satır bir testin düşmesinden
    | doğdu. Görselsiz üründe kart "Görsel yok" dalına düşüyor ve HİÇ
    | `<img>` çizilmiyor; tembel yüklemeyi ölçen test o hâlde hiçbir şey
    | ölçmezdi.
    |
    | ⚠️ `path` doğrudan yazılıyor: `$fillable` yalnızca `alt` ve
    | `position` içeriyor (yol bir sahiplik/konum alanı, dışarıdan
    | alınmaz). Test o korumayı delmek yerine tabloya yazıyor.
    */
    foreach ($urunler as $sira => $urun) {
        DB::table('product_images')->insert([
            'uuid' => (string) Str::uuid(),
            'product_id' => $urun->id,
            'path' => "products/{$urun->id}/kapak.webp",
            'alt' => $urun->title,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

it('★★★ EKRAN USTUNDEKI gorsel ISTEKLI, gerisi TEMBEL', function () {
    tembelHazir(10);

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ "Her şeye lazy" YANLIŞ olurdu: ekranın üstündeki görsele `lazy`
    | vermek onu GECİKTİRİYOR — tarayıcı önce yerleşimi hesaplayıp sonra
    | indirmeye başlıyor. Yani en çok görülen görselleri yavaşlatırdı.
    */
    expect(substr_count($html, 'loading="eager"'))->toBe(4);
    expect(substr_count($html, 'loading="lazy"'))->toBeGreaterThan(0);

    // çözümleme ana iş parçacığını tıkamasın
    expect($html)->toContain('decoding="async"');
});

it('★★★ SAYFANIN ILK IZGARASI disinda ISTEKLI gorsel YOK', function () {
    tembelHazir(10);

    /*
    | ★ BU KURULUM BİR KIRMA DENEMESİNİN AÇTIĞI BOŞLUKTAN DOĞDU.
    |
    | ⚠️ İlk hâlinde sayfada TEK ızgara vardı (B1 bölümleri verisi
    | olmadığı için hiç çizilmiyor). Tek ızgarayla "her bölüm kendi ilk
    | satırını istekli yüklesin" denemesi hiçbir şeyi bozmuyordu —
    | test iddiasını ölçmüyordu.
    |
    | Aşağıdaki olaylar "Şu sıralar popüler" bölümünü açıyor; artık
    | sayfada İKİ ızgara var ve toplam istekli sayısı gerçekten
    | sınanabiliyor.
    */
    $urunler = Product::query()->limit(4)->get();

    foreach ($urunler as $urun) {
        foreach (range(1, 15) as $i) {
            DB::table('events')->insert([
                'type' => EventType::ProductViewed->value,
                'payload' => json_encode(['product_id' => $urun->id]),
                'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /*
    | ⚠️ İKİ BÖLÜM ŞART, bir tane yetmiyor: `$loop->first` ayrımı ancak
    | ikinci bölüm varken anlam kazanıyor. İlk denemede yalnızca
    | "popüler" çiziliyordu ve "her bölüm istekli olsun" değişikliği
    | hiçbir şeyi bozmuyordu — deneme yine tutmadı.
    |
    | Satışlar "Çok satanlar" bölümünü açıyor.
    */
    foreach (Product::query()->limit(4)->get() as $urun) {
        satisYap($urun, 1);
    }

    Cache::flush();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    // ÜÇ ızgara bekleniyor: popüler + çok satanlar + tüm ürünler
    expect(substr_count($html, 'class="izgara"'))->toBeGreaterThan(2);

    /*
    | ⚠️ Her ızgara kendi ilk satırını istekli yükleseydi, ekranın
    | ALTINDAKİ bölümler de görselleri hemen indirir ve tembel
    | yüklemenin kazancı büyük ölçüde giderdi.
    */
    expect(substr_count($html, 'loading="eager"'))->toBe(4);
});

it('★★★ 24. URUNDEN SONRASI artik KAYBOLMUYOR', function () {
    tembelHazir(HomeController::LIMIT + 6);

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ ASIL KUSUR BUYDU: `limit(24)` ile 25. ürün ana sayfadan hiç
    | görünmüyordu ve sayfa bunu söylemiyordu bile.
    */
    expect($html)->toContain('data-sonraki')
        ->and($html)->toContain('sayfa=2');
});

it('★★★ SONRAKI SAYFA BAGLANTISI GERCEK — JavaScript kapaliyken de calisiyor', function () {
    tembelHazir(HomeController::LIMIT + 6);

    $html = (string) $this->get('http://marka-a.test/')->getContent();

    /*
    | ★ İLERLEMELİ ZENGİNLEŞTİRME. Bağlantı gerçek bir `<a href>`;
    | betik yalnızca onu üstleniyor. Bu hem JavaScript kapalı
    | kullanıcı hem de ARAMA MOTORU için tek yol — vitrinin sunucuda
    | render edilmesinin (4-K1) sebebi zaten buydu.
    */
    preg_match('/<a[^>]*data-sonraki[^>]*>/', $html, $m);

    expect($m[0] ?? '')->toContain('href=');

    // ve o adres gerçekten ikinci sayfayı veriyor
    $ikinci = (string) $this->get('http://marka-a.test/?sayfa=2')
        ->assertOk()
        ->getContent();

    expect(substr_count($ikinci, 'class="kart"'))->toBeGreaterThan(0);
});

it('★★★ ARAMA yapilmisken sayfa 2 aramayi KAYBETMIYOR', function () {
    tembelHazir(HomeController::LIMIT + 6);

    $html = (string) $this->get('http://marka-a.test/?q=Ürün')->assertOk()->getContent();

    /*
    | ⚠️ `withQueryString()` olmadan bağlantı `?sayfa=2` üretir ve
    | müşteri sayfa 2'de aramasını KAYBEDERDİ — bütün katalog geri
    | gelirdi.
    */
    /*
    | ⚠️ REGEX ÖZNİTELİK SIRASINA BAĞLI OLMAMALI. İlk hâli
    | `data-sonraki`yi `href`ten ÖNCE arıyordu; işaretlemede sıra tersti
    | ve eşleşme boş döndü — test kodu değil KENDİNİ ölçtü.
    */
    preg_match('/<a\s[^>]*data-sonraki[^>]*>/', $html, $etiket);

    expect($etiket[0] ?? '')->not->toBe('');

    preg_match('/href="([^"]*)"/', $etiket[0] ?? '', $m);

    expect($m[1] ?? '')->toContain('q=')
        ->and($m[1] ?? '')->toContain('sayfa=2');

    // sonuç sayısı SAYFANIN değil TOPLAMIN sayısı olmalı
    expect($html)->toMatch('/için\s*\d+\s*sonuç/u');
});

it('★★★ IKI DUZEN de ayni ORTAK PARCAYI kullaniyor', function () {
    tembelHazir(10);

    /*
    | ⚠️ Kart işaretlemesi iki ana sayfada da KOPYAYDI ve birebir aynıydı.
    | Kopya kaldığı sürece tembel yükleme gibi her düzeltme ÜÇ yere
    | yazılacaktı — 4.6AL'de bir düzen tam bu yüzden geride kalmıştı.
    */
    foreach (['sade', 'vitrinli'] as $duzen) {
        app(SettingsService::class)->yaz(SettingGroup::Theme, 'layout', $duzen);
        Cache::flush();

        $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

        expect($html)->toContain('loading="lazy"')
            ->and($html)->toContain('decoding="async"');
    }

    // kart işaretlemesi ana sayfa dosyalarında ARTIK YOK
    foreach (['sade', 'vitrinli'] as $duzen) {
        $sablon = yorumsuz(
            base_path("resources/views/storefront/{$duzen}/anasayfa.blade.php")
        );

        expect($sablon)->not->toContain('<img');
    }
});
