<?php

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\HomeSections;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\EventType;
use App\Enums\SettingGroup;
use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
| ANA SAYFA BÖLÜMLERİ (B1)
|
| ★ Ana sayfa tek düz listeydi (24 ürün). Müşteri neye baktığını
| bilmiyordu: bu ürünler neden burada, hangisi yeni, hangisi tutuyor?
|
| ★ BU BLOĞUN EN ÖNEMLİ KARARI EŞİKLER — ve keyfî değil, ÖLÇÜMDEN doğdu:
|
|   görüntüleme olayı              20
|   müşteriye bağlı görüntüleme     1
|   son 30 günde eklenen ürün      23   (katalogun TAMAMI)
|
| Eşiksiz kurulsaydı "en çok tıklanan" tek tıklamayla popüler ürün ilan
| ederdi ve "yeni gelenler" katalogun tamamını gösterirdi. 4.6F'nin
| dersi: hesabı doğru ama sonucu saçma olan sayıyı gösterme.
*/

/** @return array{musteri: Customer} */
function bolumlerHazir(): array
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    // ⚠️ Önbellek testler arasında taşınmasın: bölümler 5 dk önbellekli.
    Cache::flush();

    return ['musteri' => Customer::factory()->create(['email' => 'alici@ornek.test'])];
}

// ─────────────────────────────────────────────────────────────────────
// EŞİKLER — bu bloğun asıl konusu
// ─────────────────────────────────────────────────────────────────────

it('★★★ VERISI OLMAYAN bolum HIC cizilmiyor', function () {
    ['musteri' => $musteri] = bolumlerHazir();
    bolumUrunleri(6);

    /*
    | Hiç görüntüleme yok, hiç satış yok, müşterinin geçmişi yok ve
    | ürünlerin TAMAMI yeni — yani hiçbir bölüm anlamlı değil.
    */
    expect(app(HomeSections::class)->bolumler($musteri))->toBe([]);
});

it('★★★ POPULER esigin ALTINDA cizilmiyor, USTUNDE ciziliyor', function () {
    bolumlerHazir();
    $urunler = bolumUrunleri(6);

    $yaz = function (int $urunId, int $adet): void {
        foreach (range(1, $adet) as $i) {
            DB::table('events')->insert([
                'type' => EventType::ProductViewed->value,
                'payload' => json_encode(['product_id' => $urunId]),
                'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    };

    // eşiğin ALTINDA: 20 görüntüleme (gerçek markada ölçülen sayı)
    $urunler->take(4)->each(fn ($u) => $yaz($u->id, 5));

    Cache::flush();
    expect(app(HomeSections::class)->populer())->toBeEmpty();

    /*
    | ⚠️ EŞİK ÜSTÜ: aynı veri şekli, yalnızca hacim farklı. Bölüm
    | ancak popülerlik GERÇEKTEN ölçülebildiğinde açılıyor.
    */
    $urunler->take(4)->each(fn ($u) => $yaz($u->id, 10));

    Cache::flush();
    expect(app(HomeSections::class)->populer()->count())->toBe(4);
});

it('★★★ KATALOGUN TAMAMI yeniyse "yeni gelenler" cizilmiyor', function () {
    bolumlerHazir();
    bolumUrunleri(6);

    /*
    | ★ EN İNCE KARAR. Katalogun tamamı penceredeyse bölüm katalogun
    | KENDİSİDİR: müşteriye hiçbir şey söylemez ve aynı ürünleri iki kez
    | gösterir. Ölçüldü: geliştirme markasında 23 ürünün 23'ü yeniydi.
    */
    expect(app(HomeSections::class)->yeniGelenler())->toBeEmpty();

    // eski bir ürün ekleyince bölüm ANLAMLI hâle geliyor
    DB::table('products')->limit(2)->update(['created_at' => now()->subDays(90)]);

    Cache::flush();
    expect(app(HomeSections::class)->yeniGelenler()->count())->toBe(4);
});

it('★★★ AZ GECMISLI musteriye "sizin icin" cizilmiyor', function () {
    ['musteri' => $musteri] = bolumlerHazir();
    $urunler = bolumUrunleri(6);

    /*
    | ★ ÜRÜNLERE KATEGORİ VERİLİYOR — ve bu satır bir KIRMA DENEMESİNİN
    | açtığı boşluktan geldi.
    |
    | ⚠️ Eşiği kaldıran deneme ilk turda HİÇBİR testi düşürmedi: test
    | ürünlerinin kategorisi yoktu, yani öneri zaten `$kategoriler
    | ->isEmpty()` kolundan boş dönüyordu. Koruma eşikten DEĞİL veri
    | eksikliğinden geliyordu — 4.6AJ'de yaşananın aynısı.
    |
    | Kategori verilince eşik TEK BAŞINA sınanabiliyor.
    */
    $kategori = app(CategoryService::class)->olustur('Giyim');

    DB::table('products')->update(['category_id' => $kategori->id]);

    // tek bir ürüne bakmış — seçim yapılacak kadar veri yok
    DB::table('events')->insert([
        'customer_id' => $musteri->id,
        'type' => EventType::ProductViewed->value,
        'payload' => json_encode(['product_id' => $urunler->firstOrFail()->id]),
        'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(app(HomeSections::class)->sanaOzel($musteri->refresh()))->toBeEmpty();

    /*
    | ⚠️ EŞİK ÜSTÜ: aynı şekil, yalnızca etkileşim sayısı farklı. Bölüm
    | ancak seçim yapılacak kadar veri varken açılıyor.
    */
    foreach ($urunler->skip(1)->take(3) as $u) {
        DB::table('events')->insert([
            'customer_id' => $musteri->id,
            'type' => EventType::ProductViewed->value,
            'payload' => json_encode(['product_id' => $u->id]),
            'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    expect(app(HomeSections::class)->sanaOzel($musteri->refresh()))->not->toBeEmpty();
});

it('★★★ MISAFIRE "sizin icin" HIC sorulmuyor', function () {
    bolumlerHazir();
    bolumUrunleri(6);

    expect(app(HomeSections::class)->sanaOzel(null))->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────
// EKRAN — iki düzen de
// ─────────────────────────────────────────────────────────────────────

it('★★★ IKI DUZEN de bolumleri gosteriyor — tema bir AYAR', function () {
    bolumlerHazir();
    $urunler = bolumUrunleri(6);

    foreach ($urunler->take(4) as $u) {
        foreach (range(1, 15) as $i) {
            DB::table('events')->insert([
                'type' => EventType::ProductViewed->value,
                'payload' => json_encode(['product_id' => $u->id]),
                'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /*
    | ⚠️ 4.6A'nın dersi: ürün sayfasına eklenen her şey İKİ düzeni de
    | kapsamalı. Orada varyant seçicisi yalnızca `sade`'ye uygulanmıştı
    | ve altı test bunu göremedi — hepsi VARSAYILAN düzende koşuyordu.
    */
    foreach (['sade', 'vitrinli'] as $duzen) {
        app(SettingsService::class)->yaz(SettingGroup::Theme, 'layout', $duzen);
        Cache::flush();

        $this->get('http://marka-a.test/')
            ->assertOk()
            ->assertSee('Şu sıralar popüler')
            ->assertSee('Tüm ürünler');
    }
});

it('★★★ ARAMA sirasinda bolum CIZILMIYOR', function () {
    bolumlerHazir();
    $urunler = bolumUrunleri(6, 'Tişört');

    foreach ($urunler->take(4) as $u) {
        foreach (range(1, 15) as $i) {
            DB::table('events')->insert([
                'type' => EventType::ProductViewed->value,
                'payload' => json_encode(['product_id' => $u->id]),
                'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    Cache::flush();

    /*
    | ⚠️ Müşteri bir şey aradıysa ekranın cevabı O olmalı. Bölümler
    | sonucun altına konsaydı sonuç kaybolur, üstüne konsaydı müşteri
    | aradığını bulamazdı.
    */
    $this->get('http://marka-a.test/?q=Tişört')
        ->assertOk()
        ->assertDontSee('Şu sıralar popüler');
});

it('★★ ONBELLEK KISISEL bolume UYGULANMIYOR', function () {
    $kod = (string) File::get(
        base_path('app/Domain/Catalog/HomeSections.php')
    );

    $kod = (string) preg_replace('!/\*.*?\*/!s', '', $kod);

    /*
    | ★ EN TEHLİKELİ HATA BURADA OLURDU: kişisel bölüm ortak önbelleğe
    | konsaydı bir müşterinin önerileri BAŞKASINA gösterilirdi — çok
    | kiracılıkta değil, aynı marka içinde müşteriler arası sızma.
    */
    preg_match('/function sanaOzel\(.*?\n    \}/s', $kod, $m);

    expect($m[0] ?? '')->not->toContain('onbellekli')
        ->and($m[0] ?? '')->not->toContain('Cache::');
});
