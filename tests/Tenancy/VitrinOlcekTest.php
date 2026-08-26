<?php

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use Illuminate\Support\Facades\File;

/*
| VİTRİN ÖLÇEĞİ (4.6AH)
|
| ★ ÖLÇÜLEN DURUM: vitrinde ON İKİ farklı yazı boyutu (12·13·14·15·16·17
| ·18·20·24·26·28·34) ve ALTI farklı yarıçap dolaşıyordu.
|
| ⚠️ PANELİN SORUNUNUN TERSİ, SONUCU AYNI. Panelde ölçek YOKTU (225
| kullanım tek boyutta); vitrinde FAZLA vardı. İkisinde de hiyerarşi
| okunmuyor: biri her şeyi eşitliyor, öteki hiçbir şeyi eşitlemiyor.
|
| ⚠️ YAZI TİPİNE DOKUNULMUYOR: `--yazi` marka ayarından geliyor (4-K5).
| Sistemleşen şey boyut ve yarıçap; marka serif de seçse sans da seçse
| ölçek aynı çalışıyor.
*/

function vitrinDuzeni(): string
{
    return (string) File::get(base_path('resources/views/storefront/layout.blade.php'));
}

/**
 * Vitrin düzeni — YORUMLAR AYIKLANMIŞ.
 *
 * ⚠️ Bir kuralı ANLATAN yorum kuralın kendisiyle aynı metni içeriyor;
 * ham metinde arayan iddia, yönerge bozulsa bile yeşil kalır (4.6AE).
 */
function vitrinDuzeniKod(): string
{
    $s = preg_replace('!/\*.*?\*/!s', '', vitrinDuzeni());

    return (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) $s);
}

it('★★★ OLCEK ALTI BASAMAGA indi — kural govdesinde SABIT boyut yok', function () {
    $kod = vitrinDuzeniKod();

    foreach (['--boyut-xs: 12px', '--boyut-sm: 14px', '--boyut-md: 16px',
        '--boyut-lg: 20px', '--boyut-xl: 24px', '--boyut-2xl: 32px'] as $basamak) {
        expect($kod)->toContain($basamak);
    }

    /*
    | Belirteç TANIMLARI hariç, hiçbir kural gövdesinde sabit piksel
    | kalmamalı — kalırsa ölçek onu kapsamaz ve sessizce dışarıda durur.
    */
    $govde = (string) preg_replace('/--boyut-[a-z0-9]+:\s*\d+px;/', '', $kod);

    preg_match_all('/font-size:\s*(\d+px)/', $govde, $m);

    expect($m[1])->toBe([]);
});

it('★★★ YARICAP UC BASAMAK — 999px ve 50% haric sabit deger yok', function () {
    $kod = vitrinDuzeniKod();

    foreach (['--r-sm: 6px', '--r-md: 10px', '--r-lg: 14px'] as $basamak) {
        expect($kod)->toContain($basamak);
    }

    $govde = (string) preg_replace('/--r-[a-z]+:\s*\d+px;/', '', $kod);

    preg_match_all('/border-radius:\s*(\d+px)/', $govde, $m);

    /*
    | ⚠️ 999px MEŞRU: hap biçimi bir ölçek basamağı değil, "tam yuvarlak"
    | demenin yolu. Ölçeğe sokulsaydı rozet boyutuna göre değişirdi.
    */
    expect(array_unique($m[1]))->toBe(['999px']);
});

it('★★★ DERINLIK: acik temada golge, KOYU temada none', function () {
    $kod = vitrinDuzeniKod();

    expect($kod)->toMatch('/--golge-1:\s*0 1px 2px/');

    /*
    | Koyu temanın İKİ bloğu var: sistem tercihi ve açık seçim. İkisinde
    | de kapatılmalı — yalnızca birinde kapatılsaydı müşterinin AÇIK
    | seçimiyle sistem tercihi farklı davranırdı.
    */
    $koyuBloklar = 0;

    foreach (['@media (prefers-color-scheme: dark)', ':root[data-tema="koyu"]'] as $isaret) {
        $i = mb_strpos($kod, $isaret);

        expect($i)->not->toBeFalse();

        $parca = mb_substr($kod, (int) $i, 2000);

        if (str_contains($parca, '--golge-1: none;')) {
            $koyuBloklar++;
        }
    }

    expect($koyuBloklar)->toBe(2);
});

it('★★★ KART GOLGESIZ KALDI — 4.6AD kararı geri alinmadi', function () {
    /*
    | ⚠️ Ürün kartı 4.6AD'de BİLEREK çerçevesiz bırakıldı (sakin D2C
    | dili). Derinlik eklerken en kolay hata her kaba gölge dağıtmak
    | olurdu; o karar sessizce geri alınırdı. Gölge yalnızca GERÇEKTEN
    | yükseltilmiş yüzeyde: üst bar, ödeme özeti, adres kartı.
    */
    $kod = vitrinDuzeniKod();

    preg_match('/\.kart\s*\{[^}]*\}/', $kod, $m);

    expect($m[0] ?? '')->not->toContain('box-shadow');
});

it('★★★ GECIS var ve HAREKET DUYARLILIGI korunuyor', function () {
    $kod = vitrinDuzeniKod();

    expect($kod)->toContain('prefers-reduced-motion')
        ->and($kod)->not->toMatch('/transition:\s*all/');
});

it('★★ ODAK HALKASI hâlâ yazili — :focus DEGIL :focus-visible', function () {
    $kod = vitrinDuzeniKod();

    expect($kod)->toMatch('/:focus-visible[^{]*\{[^}]*outline:\s*2px solid/')
        ->and($kod)->not->toMatch('/[a-z]:focus\s*[,{]/');
});

it('★★★ IKI DUZEN de ayni olcegi aliyor — tema bir AYAR', function () {
    /*
    | ⚠️ 4.6A'nın dersi: ürün sayfasına eklenen her şey İKİ düzeni de
    | kapsamalı. Orada varyant seçicisi yalnızca `sade`'ye uygulanmış ve
    | altı test bunu göremezdi — hepsi VARSAYILAN düzende koşuyordu.
    |
    | Burada ölçek ortak `layout.blade.php`'de, yani yapısal olarak
    | ikisi de kapsanıyor; bu test onu ÖLÇÜYOR.
    */
    vitrinliMarka();

    foreach (['sade', 'vitrinli'] as $duzen) {
        app(SettingsService::class)->yaz(SettingGroup::Theme, 'layout', $duzen);

        $cevap = $this->get('http://marka-a.test/');

        $cevap->assertOk()
            ->assertSee('--boyut-md: 16px', escape: false)
            ->assertSee('--r-lg: 14px', escape: false)
            ->assertSee('--golge-1:', escape: false);
    }
});
