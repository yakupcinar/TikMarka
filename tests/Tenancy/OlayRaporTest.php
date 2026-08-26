<?php

use App\Domain\Privacy\Anonymizer;
use App\Domain\Privacy\DataExporter;
use App\Domain\Settings\StorePublication;
use App\Enums\EventType;
use App\Enums\Permission;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| TIKLAMA SAYIMI VE PANEL RAPORU (4.6F)
|
| ★ ÜÇ İŞ, BİRİNCİSİ BİR KUSUR:
|
| 1. Vitrin SAYFASI olay yazmıyordu. `product_viewed` yalnızca
|    `CatalogController`'dan (API) kaydediliyordu; müşterinin gerçekten
|    gezdiği Blade sayfası hiç kayıt üretmiyordu. Ölçüldü: 18 görüntüleme
|    olayı vardı ve HİÇBİRİ bir müşteriye bağlı değildi. 4.5I'deki
|    sayfa/API ayrımının aynısı.
|
| 2. KVKK yolları olayları KAPSAMIYORDU — ve bu boşluk varsayımsal
|    değildi: ölçüm anında 137 olay kayıtlıydı, 51'i müşteriye bağlı.
|    Yani "verimi ver" ve "beni unut" talepleri EKSİK cevaplanıyordu.
|
| 3. Panelde rapor ekranı yoktu: ölçüm vardı, markanın göreceği yer yoktu.
*/

/** @return array{sahip: User, urun: Product, musteri: Customer} */
function raporHazir(): array
{
    $hazir = teslimAlmisMusteri('marka-a.test');
    app(StorePublication::class)->yayinla();

    return [
        'sahip' => User::where('email', 'sahip@marka-a.test')->firstOrFail(),
        'urun' => $hazir['urun'],
        'musteri' => $hazir['musteri'],
    ];
}

/**
 * Olayı DOĞRUDAN tabloya yazar.
 *
 * ⚠️ `Event::create()` KULLANILMIYOR: `$fillable` bilerek BOŞ —
 * `customer_id` bir sahiplik alanı ve dışarıdan alınmamalı (1A kuralı).
 * Testin bunu delmesi, korumayı test uğruna gevşetmek olurdu.
 *
 * @param  array<string, mixed>  $payload
 */
function olayYaz(?int $musteriId, string $tip, array $payload, ?string $anonId = null): void
{
    DB::table('events')->insert([
        'customer_id' => $musteriId,
        'anon_id' => $anonId,
        'type' => $tip,
        'payload' => json_encode($payload),
        'occurred_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────
// 1 · KUSUR: vitrin sayfası olay yazmıyordu
// ─────────────────────────────────────────────────────────────────────

it('★★★ VITRIN SAYFASI goruntuleme olayi yaziyor — API degil, SAYFA', function () {
    ['urun' => $urun] = raporHazir();

    Event::query()->delete();

    /*
    | ⚠️ GERÇEK bir tarayıcı kullanıcı aracısı gönderiliyor. Test
    | istemcisinin varsayılanı boş ve boş aracı BOT sayılıyor — yani
    | başlık konmasaydı test, kodu doğru olsa bile düşerdi ve sebep
    | anlaşılmazdı.
    */
    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/120.0 Safari/537.36')
        ->get("http://marka-a.test/urun/{$urun->slug}")
        ->assertOk();

    expect(Event::where('type', EventType::ProductViewed->value)->count())->toBe(1);

    $olay = Event::where('type', EventType::ProductViewed->value)->firstOrFail();

    expect($olay->payload['product_id'] ?? null)->toBe($urun->id);
});

it('★★★ GIRIS YAPMIS musteri olaya BAGLANIYOR — 18 olayin hicbiri bagli degildi', function () {
    ['urun' => $urun, 'musteri' => $musteri] = raporHazir();

    Event::query()->delete();

    /*
    | ⚠️ `actingAs` KULLANILMIYOR — guard'ı da değiştirdiği için kimliğin
    | HANGİ guard'dan çözüldüğünü gizler (4.5I'de iki kez ısırdı).
    | Gerçek giriş isteği atılıyor.
    */
    $this->post('http://marka-a.test/giris', [
        'email' => $musteri->email,
        'password' => 'sifre1234',
    ]);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/120.0 Safari/537.36')
        ->get("http://marka-a.test/urun/{$urun->slug}")
        ->assertOk();

    expect(Event::where('type', EventType::ProductViewed->value)->first()?->customer_id)
        ->toBe($musteri->id);
});

it('★★★ BOT trafigi olcume GIRMIYOR', function () {
    ['urun' => $urun] = raporHazir();

    Event::query()->delete();

    /*
    | ⚠️ Görüntüleme OKUMA YOLUNDA yazılıyor ve ürün sayfası herkese
    | açık: elenmezse marka "400 kez bakılmış" diye bir sayı görür ve
    | ona göre stok planlar.
    */
    foreach ([
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'curl/8.4.0',
        'python-requests/2.31.0',
        '',
    ] as $ajan) {
        $this->withHeader('User-Agent', $ajan)
            ->get("http://marka-a.test/urun/{$urun->slug}")
            ->assertOk();
    }

    expect(Event::where('type', EventType::ProductViewed->value)->count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────
// 2 · KVKK — bloğun ZORUNLU parçası
// ─────────────────────────────────────────────────────────────────────

it('★★★ ANONIMLESTIRME olaylari BAGDAN KOPARIYOR — silmiyor', function () {
    ['musteri' => $musteri, 'urun' => $urun] = raporHazir();

    Event::query()->delete();

    olayYaz($musteri->id, EventType::ProductViewed->value,
        ['product_id' => $urun->id], (string) Str::uuid());

    app(Anonymizer::class)->musteriyiAnonimlestir($musteri->refresh());

    $olay = Event::firstOrFail();

    /*
    | ⚠️ FAVORİNİN TERSİ KARAR. Favoride kişisel veri BAĞIN KENDİSİYDİ
    | ve maskelenecek alanı yoktu — silindi. Olayda ise kişisel veri
    | yalnızca `customer_id`; bağ koparılınca geriye markanın meşru
    | ölçümü kalıyor ama artık kimseye ait değil.
    */
    expect($olay->customer_id)->toBeNull();

    /*
    | ⚠️ `anon_id` DE temizlenmeli: takma kimlik de bir kimliktir.
    | Kalsaydı aynı `anon_id`'yi taşıyan olaylar birbirine bağlanıp
    | profil yeniden kurulabilirdi.
    */
    expect($olay->anon_id)->toBeNull();
});

it('★★★ VERI DOKUMU olaylari ICERIYOR — "elimizde ne var"', function () {
    ['musteri' => $musteri, 'urun' => $urun] = raporHazir();

    Event::query()->delete();

    olayYaz($musteri->id, EventType::ProductViewed->value,
        ['product_id' => $urun->id, 'slug' => $urun->slug]);

    $dokum = app(DataExporter::class)->musteriDokumü($musteri->refresh());

    expect($dokum)->toHaveKey('davranis_kayitlari')
        ->and($dokum['davranis_kayitlari'])->toHaveCount(1);

    expect($dokum['davranis_kayitlari'][0]['tur'])->toBe(EventType::ProductViewed->value);

    /*
    | ⚠️ `payload` OLDUĞU GİBİ yazılıyor. Kişisel veri içermemesi
    | gerekiyor (1F-K4); süzülseydi kural ihlal edilse bile müşteri
    | bunu göremezdi. Yani bu alan aynı zamanda 1F-K4'ün DENETİMİ.
    */
    expect($dokum['davranis_kayitlari'][0]['ayrinti'])->toHaveKey('product_id');
});

it('★★ BASKA musterinin olayi dokume GIRMIYOR', function () {
    ['musteri' => $musteri, 'urun' => $urun] = raporHazir();

    Event::query()->delete();

    /*
    | ⚠️ GERÇEK ikinci müşteri: `customer_id` yabancı anahtar taşıyor,
    | uydurma kimlik veritabanından geri döner. Kısıtın kendisi de bu
    | testin ölçtüğü şeyin bir parçası.
    */
    $baskasi = Customer::factory()->create(['email' => 'baskasi@ornek.test']);

    olayYaz($baskasi->id, EventType::ProductViewed->value, ['product_id' => $urun->id]);

    expect(app(DataExporter::class)->musteriDokumü($musteri->refresh())['davranis_kayitlari'])
        ->toBe([]);
});

// ─────────────────────────────────────────────────────────────────────
// 3 · PANEL RAPORU
// ─────────────────────────────────────────────────────────────────────

it('★★★ RAPOR EKRANI acilliyor ve huni tasiyor', function () {
    ['sahip' => $sahip] = raporHazir();

    $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim/rapor')
        ->assertOk()
        ->getContent());

    expect($veri['component'])->toBe('Rapor');

    expect($veri['props']['satirlar'])->not->toBeEmpty();

    foreach (['baslik', 'goruntuleme', 'sepeteEkleme', 'satisAdedi'] as $alan) {
        expect($veri['props']['satirlar'][0])->toHaveKey($alan);
    }
});

it('★★★ CIRO SUTUNU finance.view OLMADAN gonderilmiyor', function () {
    ['sahip' => $sahip] = raporHazir();

    /*
    | ★ 4F'nin dersi: "tablo listesini daraltmak YETMEZ, KOLON da
    | temizlenir." Ekran katalog VEYA sipariş iznine açık ama ciro
    | finansal veri.
    |
    | ⚠️ Alan `null` gidiyor, SIFIR değil: sıfır "bu üründen hiç
    | kazanılmadı" demek olurdu ve personel yanlış bilgilenirdi.
    */
    $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim/rapor')->getContent());

    expect($sahip->hasPermission(Permission::FinanceView))->toBeTrue()
        ->and($veri['props']['ciroGorunur'])->toBeTrue();

    // finansal yetkisi olmayan personel
    $kisitli = izinliPersonel([Permission::ProductView->value], 'rapor@marka-a.test');

    $veri2 = inertiaVerisi((string) $this->actingAs($kisitli, 'staff-web')
        ->get('http://marka-a.test/yonetim/rapor')->getContent());

    expect($veri2['props']['ciroGorunur'])->toBeFalse();

    foreach ($veri2['props']['satirlar'] as $satir) {
        expect($satir['ciro'])->toBeNull();
    }
});

it('★★★ DONEM BEYAZ LISTEDEN geciyor — adrese ne yazilirsa yazilsin', function () {
    ['sahip' => $sahip] = raporHazir();

    /*
    | ⚠️ Doğrudan `(int)` alınsaydı `?gun=100000` yazan biri bütün olay
    | tablosunu tarayan bir sorgu açtırırdı. Kimlik doğrulanmış olsa
    | bile bu bir yük kapısı.
    */
    foreach (['100000', 'abc', '-5', '0'] as $kotu) {
        $veri = inertiaVerisi((string) $this->actingAs($sahip, 'staff-web')
            ->get("http://marka-a.test/yonetim/rapor?gun={$kotu}")->getContent());

        expect($veri['props']['gun'])->toBe(30);
    }
});
