<?php

use App\Domain\Favorite\FavoriteService;
use App\Domain\Identity\CustomerInsight;
use App\Domain\Privacy\Anonymizer;
use App\Domain\Settings\StorePublication;
use App\Enums\PaymentAttemptStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
| PANELDE MÜŞTERİ SEKMESİ (4.6AC)
|
| ★ `customer.view` izni Faz 1'den beri TANIMLIYDI, üç role verilmişti ve
| Türkçe etiketi bile vardı — ama HİÇBİR ROTA onu kullanmıyordu. İzin
| ölüydü. 4.6S'de `product.view` için ölçülen kusurun aynısı.
|
| ⚠️ Panel Inertia (4-K1): cevap ekrandaki METNİ İÇERMİYOR. Bu yüzden
| iddialar `component` ve `props` üzerinden kuruluyor; `assertSee`
| yazmak testi yalancı yapardı.
*/

/** @return array{sahip: User, musteri: Customer, urun: Product} */
function panelMusteriHazir(): array
{
    $hazir = teslimAlmisMusteri('marka-a.test');
    app(StorePublication::class)->yayinla();

    $sahip = User::where('email', 'sahip@marka-a.test')->firstOrFail();

    return ['sahip' => $sahip, 'musteri' => $hazir['musteri'], 'urun' => $hazir['urun']];
}

it('★★★ MUSTERI LISTESI acilliyor ve SIPARIS/HARCAMA ozeti tasiyor', function () {
    ['sahip' => $sahip, 'musteri' => $musteri, 'urun' => $urun] = panelMusteriHazir();

    $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim/musteriler')->assertOk()->getContent());

    expect($veri['component'])->toBe('Musteriler/Liste');

    /** @var list<array<string, mixed>> $satirlar */
    $satirlar = $veri['props']['musteriler']['data'];

    $satir = collect($satirlar)->firstWhere('eposta', $musteri->email);

    /*
    | ⚠️ BEKLEYEN SİPARİŞ DE VAR ve sayılmamalı — kırma denemesi bunu
    | ölçmediğimi gösterdi: `pending`'i satış durumlarına eklediğimde
    | hiçbir test düşmemişti, çünkü test müşterisinin bekleyen siparişi
    | yoktu. Sayılsaydı ödemesi hiç tamamlanmayan sepetler müşteriyi
    | "iyi müşteri" gibi gösterirdi.
    */
    /*
    | ⚠️ `odemeAsamasiSiparisiMusteriyle()` KULLANILAMIYOR: yeni bir ürünü
    | `TS-1` SKU'suyla açıyor ve o SKU zaten var — 4.6X.1'deki benzersizlik
    | koruması reddediyor. Kendi korumamızın test yardımcısını ısırması,
    | çalıştığının işareti. Mevcut varyant üzerinden bekleyen sipariş
    | açılıyor.
    */
    $varyant = $urun->variants()->firstOrFail();
    $bekleyen = bekleyenSiparis($varyant, $musteri);

    expect($bekleyen->payment_status->value)->toBe('pending');

    $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim/musteriler')->assertOk()->getContent());

    /** @var list<array<string, mixed>> $satirlar */
    $satirlar = $veri['props']['musteriler']['data'];

    $satir = collect($satirlar)->firstWhere('eposta', $musteri->email);

    expect($satir)->not->toBeNull();

    /** @var array<string, mixed> $satir */
    expect($satir['siparis'])->toBe(1)

        /*
        | ⚠️ `?? 0` olmasaydı hiç siparişi olmayan müşteride `withSum`
        | NULL döner ve ekranda boş görünürdü.
        */
        ->and((float) $satir['harcama'])->toBeGreaterThan(0);
});

it('★★★ PAROLA HASH I PANELE HIC ULASMIYOR — kolon sorguya bile girmiyor', function () {
    ['sahip' => $sahip, 'musteri' => $musteri] = panelMusteriHazir();

    /*
    | ⚠️ BLOĞUN EN KRİTİK İDDİASI. 4F'de marka dökümüne bcrypt hash'leri
    | tam böyle girmişti: tablo listesi daraltılmıştı ama KOLON
    | temizlenmemişti.
    |
    | ⚠️ Modelin `$hidden` listesine güvenmek YETMEZ: o yalnızca
    | diziye/JSON'a çevirirken çalışıyor. Kolon sorguya hiç girmiyor.
    */
    foreach (['/yonetim/musteriler', "/yonetim/musteriler/{$musteri->uuid}"] as $yol) {
        $html = (string) $this->actingAs($sahip, 'staff-web')
            ->get("http://marka-a.test{$yol}")->assertOk()->getContent();

        expect($html)->not->toContain('$2y$')
            ->and($html)->not->toContain('password')
            ->and($html)->not->toContain('remember_token');
    }

    /*
    | ⚠️ YUKARIDAKİ İDDİA TEK BAŞINA YETMİYOR — kırma denemesiyle ölçüldü:
    | `->select(self::KOLONLAR)` kaldırıldığında ekran YİNE temiz kalıyor,
    | çünkü onu koruyan şey modelin `$hidden` listesi.
    |
    | Ama `$hidden` yalnızca diziye/JSON'a çevirirken çalışıyor; log, `dd`
    | ya da ilişki serileştirmesi onu atlayabilir. Kolonun SORGUYA HİÇ
    | GİRMEDİĞİ ayrıca ölçülüyor — 4F'de marka dökümüne bcrypt hash'leri
    | tam bu ikinci savunma olmadığı için girmişti.
    */
    $yuklenen = app(CustomerInsight::class)->liste()->getCollection()->first();

    expect($yuklenen)->not->toBeNull();

    $kolonlar = array_keys($yuklenen?->getAttributes() ?? []);

    /*
    | ⚠️ İDDİALAR TEK TEK. İlk hâli `->not->toContain('password',
    | 'remember_token')` yazıyordu ve KIRMA DENEMESİ TUTMADI: çok
    | argümanlı `toContain`'in olumsuzu, argümanlardan biri eksik olduğu
    | anda geçiyor. `remember_token` zaten hiç yüklenmediği için iddia
    | `password` varken bile yeşil kalıyordu — yani ölçtüğünü sandığım
    | şeyi hiç ölçmüyordu.
    */
    expect($kolonlar)->not->toContain('password');
    expect($kolonlar)->not->toContain('remember_token');
});

it('★★★ AYRINTI sayfasi SIPARIS, FAVORI ve BASARISIZ ODEMEYI gosteriyor', function () {
    ['sahip' => $sahip, 'musteri' => $musteri, 'urun' => $urun] = panelMusteriHazir();

    app(FavoriteService::class)->degistir($musteri, $urun);

    $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
        ->get("http://marka-a.test/yonetim/musteriler/{$musteri->uuid}")->assertOk()->getContent());

    expect($veri['component'])->toBe('Musteriler/Ayrinti')
        ->and($veri['props']['siparisler'])->toHaveCount(1)
        ->and($veri['props']['favoriler'])->toHaveCount(1)
        ->and($veri['props']['ozet']['favori'])->toBe(1);

    /*
    | ⚠️ Ürün adları SİPARİŞ SATIRINDAN (kopya, 1D): ürün silinse bile
    | müşterinin ne aldığı görünmeli.
    */
    expect($veri['props']['siparisler'][0]['urunler'])->not->toBeEmpty();
});

it('★★★ SILINMIS urunun favorisi PANELDE gorunuyor — vitrinin TERSI', function () {
    ['sahip' => $sahip, 'musteri' => $musteri, 'urun' => $urun] = panelMusteriHazir();

    app(FavoriteService::class)->degistir($musteri, $urun);
    $urun->delete();

    /*
    | ⚠️ Vitrinde silinmiş ürünün favorisi GİZLENİYOR (4.6D): orada soru
    | "müşteriye ne gösterelim". Panelde soru "bu müşteri hakkında ne
    | biliyoruz" — gizlemek markayı yanıltırdı.
    */
    $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
        ->get("http://marka-a.test/yonetim/musteriler/{$musteri->uuid}")->assertOk()->getContent());

    expect($veri['props']['favoriler'])->toHaveCount(1)
        ->and($veri['props']['favoriler'][0]['urun'])->toBe('[silinmiş ürün]');
});

it('★★★ BASARISIZ ODEME gorunuyor ama RET GEREKCESI GORUNMUYOR', function () {
    $siparis = bildirimeHazirSiparis('marka-a.test')['siparis'];
    $sahip = User::where('email', 'sahip@marka-a.test')->firstOrFail();

    $musteri = Customer::create(['name' => 'Ayşe', 'email' => 'ayse@ornek.test', 'password' => bcrypt('sifre12345')]);
    $siparis->customer()->associate($musteri);
    $siparis->save();

    $odeme = Payment::where('order_id', $siparis->id)->firstOrFail();
    $odeme->status = PaymentAttemptStatus::Failed;
    $odeme->save();

    $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
        ->get("http://marka-a.test/yonetim/musteriler/{$musteri->uuid}")->assertOk()->getContent());

    expect($veri['props']['basarisizOdemeler'])->toHaveCount(1)
        ->and($veri['props']['ozet']['basarisiz'])->toBe(1);

    /*
    | ⚠️ Banka "limit yetersiz" ya da "fraud şüphesi" diyebiliyor; bu
    | müşterinin KARTINA dair bir bilgi ve markanın personeline açılması
    | gerekmiyor. Vitrinde de aynı sebeple gizleniyor (4.5R).
    */
    expect(array_keys($veri['props']['basarisizOdemeler'][0]))
        ->not->toContain('gerekce', 'hata', 'failure_reason');
});

it('★★★ IZIN OLMADAN sekme KAPALI — olu izin artik CANLI', function () {
    panelMusteriHazir();

    /*
    | ⚠️ Bu test iznin GERÇEKTEN kullanıldığını ölçüyor. `customer.view`
    | Faz 1'den beri tanımlıydı ama hiçbir rota kullanmıyordu; izin
    | tanımlı olmak, korunuyor olmak DEĞİLDİR.
    */
    $katalogcu = izinliPersonel(['product.view', 'product.write'], 'katalogcu@marka-a.test');

    $this->actingAs($katalogcu, 'staff-web')
        ->get('http://marka-a.test/yonetim/musteriler')
        ->assertForbidden();

    $destek = izinliPersonel(['customer.view'], 'destek@marka-a.test');

    $this->actingAs($destek, 'staff-web')
        ->get('http://marka-a.test/yonetim/musteriler')
        ->assertOk();
});

it('★★★ SEKMEDE YAZMA UCU YOK — salt okunur', function () {
    /*
    | ⚠️ Müşteri verisini panelden değiştirmek KVKK tarafında ayrı bir
    | sorumluluk (anonimleştirme 2G, kendi akışıyla). Buraya bir yazma
    | ucu sızarsa o akış atlanabilir hâle gelir.
    */
    markaKur('marka-a.test');

    $yazanlar = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_contains((string) $r->uri(), 'yonetim/musteriler'))
        ->reject(fn ($r) => $r->methods() === ['GET', 'HEAD']);

    expect($yazanlar)->toHaveCount(0);
});

it('★★★ ANONIMLESTIRILMIS musteri PANELDE de tanınmaz halde', function () {
    ['sahip' => $sahip, 'musteri' => $musteri] = panelMusteriHazir();

    app(Anonymizer::class)->musteriyiAnonimlestir($musteri);

    /*
    | ⚠️ Anonimleştirme müşteriyi SİLMİYOR, maskeliyor (2G) — yani kayıt
    | panelde durmaya devam ediyor. Maskelemenin panele de yansıdığı
    | ölçülmezse "sildim" sanılan veri burada okunabilir kalırdı.
    */
    $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
        ->get("http://marka-a.test/yonetim/musteriler/{$musteri->uuid}")->assertOk()->getContent());

    expect($veri['props']['musteri']['ad'])->toBe('[silindi]')
        ->and($veri['props']['musteri']['eposta'])->toContain('@anonim.invalid');
});

it('★★ ARAMA SOL ESLESME — kelime ortasindan bulmuyor', function () {
    ['sahip' => $sahip] = panelMusteriHazir();

    Customer::create(['name' => 'Tişörtçü Ahmet', 'email' => 'ahmet@ornek.test', 'password' => bcrypt('x12345678')]);

    $bul = function (string $ara) use ($sahip): array {
        $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
            ->get('http://marka-a.test/yonetim/musteriler?ara='.urlencode($ara))->assertOk()->getContent());

        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $veri['props']['musteriler']['data'];

        return $satirlar;
    };

    // ⚠️ 4.5P ve 4.5S'deki kararın aynısı: "iş" araması "Tişört"ü getirmemeli.
    expect($bul('iş'))->toHaveCount(0)
        ->and(array_column($bul('tiş'), 'eposta'))->toContain('ahmet@ornek.test');
});
