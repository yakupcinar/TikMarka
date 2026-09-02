<?php

use App\Domain\Settings\BrandPalette;
use Illuminate\Support\Facades\File;

/*
| PANEL GÖRSEL DİLİ — YAN MENÜ, TABLO, MOBİL (4.6AF)
|
| ★ ÖLÇÜLEN DURUM — üçü de tahmin değil, tarayıcıda sayıyla bulundu:
|
|   1. ÜST MENÜ MASAÜSTÜNDE BİLE TAŞIYORDU. 14 madde tek satırda:
|      menü 988px, başlığın ihtiyacı 1441px, kapsayıcı 1152px → 289px
|      taşma. Sahip rolü bütün maddeleri gördüğü için en çok onu
|      vuruyordu. Yatay menü madde sayısıyla ölçeklenmiyor.
|
|   2. 13 TABLONUN 13'ÜNDE de yatay kaydırma kabı yoktu. Telefonda
|      5 sütunlu bir tablo SAYFANIN TAMAMINI yatay kaydırıyordu.
|
|   3. PANELİN TAMAMINDA 4 KIRILMA NOKTASI vardı (25 sayfa) — panel
|      fiilen masaüstüne özeldi.
|
| ⚠️ BU TESTLERİN SINIRI: ekrandaki yerleşimi ölçemezler (Inertia,
| 4-K1). Ölçtükleri şey SÖZLEŞME — kap var mı, kırılma noktası var mı,
| kontrast eşiği geçiliyor mu. Yerleşimin kendisi tarayıcıda doğrulandı.
*/

function panelDuzeni(): string
{
    return yorumsuz(base_path('resources/js/Panel/Layouts/PanelDuzeni.vue'));
}

/**
 * Panel düzeni — YORUMLAR AYIKLANMIŞ.
 *
 * ⚠️ 4.6AE'de iki kırma denemesi, iddia yorumun içini okuduğu için
 * tutmamıştı. Bu dosyadaki her iddia kuralı ANLATAN yorumu değil
 * kuralın KENDİSİNİ görmeli.
 */
function panelDuzeniKod(): string
{
    $s = preg_replace('/<!--.*?-->/s', '', panelDuzeni());

    return (string) preg_replace('!/\*.*?\*/!s', '', (string) $s);
}

it('★★★ HER TABLO yatay kaydirma KABINDA — yoksa SAYFANIN TAMAMI kayar', function () {
    $kapsiz = [];

    foreach (panelSayfalari() as $yol) {
        $icerik = yorumsuz($yol);

        /*
        | Tablonun HEMEN ÜSTÜNDEKİ satırda kap aranıyor. "Dosyada bir
        | yerde overflow-x-auto geçiyor mu" diye sorulsaydı, kabı BAŞKA
        | bir tabloya ait olan dosya da geçerdi.
        */
        $satirlar = explode("\n", $icerik);

        foreach ($satirlar as $i => $satir) {
            if (! str_contains($satir, '<table')) {
                continue;
            }

            $onceki = $satirlar[$i - 1] ?? '';

            if (! str_contains($onceki, 'overflow-x-auto')) {
                $kapsiz[] = basename(dirname($yol)).'/'.basename($yol).':'.($i + 1);
            }
        }
    }

    expect($kapsiz)->toBe([]);
});

it('★★★ KOSUL YONERGESI KABA tasindi — tabloda kalirsa v-if zinciri KIRILIR', function () {
    /*
    | ⚠️ BU TEST BİR HATADAN DOĞDU. Tablolar ilk sarıldığında `v-else`
    | tablonun üstünde bırakılmıştı; sarmalayıcı araya girince `v-else`
    | artık `v-if`'in KOMŞUSU değildi ve Vue derlemesi patlıyordu.
    | Derleme yakaladı, ama yakalamayabilirdi de: koşulsuz bir tabloya
    | sonradan `v-if` eklenirse aynı kırılma sessizce geri gelir.
    */
    $hatali = [];

    foreach (panelSayfalari() as $yol) {
        preg_match_all('/<table\b[^>]*>/', yorumsuz($yol), $m);

        foreach ($m[0] as $tag) {
            if (preg_match('/\sv-(if|else-if|else|for)\b/', $tag)) {
                $hatali[] = basename($yol).' → '.$tag;
            }
        }
    }

    expect($hatali)->toBe([]);
});

it('★★★ MENU YAN MENUDE ve GRUPLU — 14 madde yatayda 289px tasiyordu', function () {
    $kod = panelDuzeniKod();

    expect($kod)->toContain('<aside')
        ->and($kod)->toContain('id="panel-menu"');

    // gruplar: başlıksız kök + üç başlık
    foreach (['Katalog', 'Satış', 'Ayarlar'] as $baslik) {
        expect($kod)->toContain("baslik: '{$baslik}'");
    }

    /*
    | ⚠️ BOŞ GRUP DÜŞMELİ: yalnızca sipariş izni olan personel "Katalog"
    | başlığını altı BOŞ hâlde görmemeli.
    */
    expect($kod)->toContain('grup.maddeler.length > 0');
});

it('★★★ MOBIL CEKMECE var ve DURUMUNU soyluyor', function () {
    $kod = panelDuzeniKod();

    expect($kod)->toContain('lg:hidden')
        ->and($kod)->toContain('aria-expanded')
        ->and($kod)->toContain('aria-controls="panel-menu"');

    /*
    | ⚠️ Yönlendirmeden sonra kapanmalı — Inertia sayfayı değiştirir ama
    | bileşen ayakta kalır, çekmece açık kalırsa yeni sayfayı örter.
    */
    expect($kod)->toContain("router.on('navigate'");
});

it('★★★ ANA SUTUNDA min-w-0 var — yoksa kaydirma kabi ISE YARAMAZ', function () {
    /*
    | Flex çocuğunun varsayılan en küçük genişliği İÇERİĞİ kadardır.
    | `min-w-0` konmazsa geniş tablo ana sütunu şişirir, kap hiç
    | daralmaz ve sayfanın tamamı yatay kayar — yani 1. testteki kap
    | tek başına YETMEZ.
    */
    expect(panelDuzeniKod())->toMatch('/<main[^>]*min-w-0/');
});

it('★★★ ETKIN SAYFA isaretli — ve Pano SUREKLI etkin gorunmuyor', function () {
    $kod = panelDuzeniKod();

    expect($kod)->toContain('aria-current')
        ->and($kod)->toContain('function etkinMi');

    /*
    | ⚠️ `/yonetim` HER yolun öneki. Önek eşleşmesi kök için de
    | uygulansaydı Pano her sayfada etkin görünürdü — yani işaret
    | hiçbir şey söylemezdi.
    */
    expect($kod)->toMatch("/yol === '\/yonetim'[\s\S]{0,120}simdiki === '\/yonetim'/");
});

it('★★★ ETKIN MADDE esikleri geciyor — IKI temada', function () {
    $palet = app(BrandPalette::class);
    $css = (string) preg_replace('!/\*.*?\*/!s', '', yorumsuz(base_path('resources/css/panel.css')));

    $deger = function (string $ad, string $blok) use ($css): string {
        // blok: açık = ilk :root, koyu = [data-tema="koyu"]
        $govde = $blok === 'koyu'
            ? (string) preg_replace('/^.*\[data-tema="koyu"\]\s*\{/s', '', $css)
            : (string) preg_replace('/@media.*$/s', '', $css);

        preg_match('/--'.preg_quote($ad, '/').':\s*(#[0-9a-fA-F]{6})/', $govde, $m);

        return $m[1] ?? '';
    };

    foreach (['acik', 'koyu'] as $tema) {
        $zemin = $deger('p-vurgu-zemin', $tema);
        $metin = $deger('p-metin', $tema);
        $cubuk = $deger('p-vurgu-metin', $tema);
        $yuzey = $deger('p-yuzey', $tema);

        expect($zemin)->not->toBe('');

        // metin okunur olmalı
        expect($palet->kontrastOrani($metin, $zemin))
            ->toBeGreaterThanOrEqual(4.5, "{$tema}: etkin madde metni");

        /*
        | ⚠️ ÇUBUK NON-TEXT: WCAG 1.4.11 → 3:1. İlk denemede
        | `--p-vurgu` kullanılmıştı ve koyu temada 1,99 ölçüldü —
        | o belirteç iki temada da aynı (düğme ZEMİNİ olduğu için).
        */
        expect($palet->kontrastOrani($cubuk, $zemin))
            ->toBeGreaterThanOrEqual(3.0, "{$tema}: etkin madde çubuğu / zemin");

        expect($palet->kontrastOrani($cubuk, $yuzey))
            ->toBeGreaterThanOrEqual(3.0, "{$tema}: etkin madde çubuğu / yan menü");

        /*
        | ⚠️ BU İDDİA BİR KIRMA DENEMESİNİN AÇTIĞI BOŞLUKTAN DOĞDU.
        |
        | Etkin zemin ilk seçildiğinde koyu temada yan menü yüzeyiyle
        | kontrastı 1,04 ölçülmüştü — yani zemin GÖRÜNMÜYORDU, "neredeyim"
        | işaretini yalnızca çubuk taşıyordu. Değer bu ölçüm yüzünden
        | değiştirildi; ama testler zemini yüzeye karşı hiç ölçmediği için
        | eski değeri geri koymak HİÇBİR TESTİ DÜŞÜRMÜYORDU.
        |
        | ⚠️ 1,2 BİR WCAG EŞİĞİ DEĞİL. WCAG bu tinte sayı koymuyor: durum
        | zaten çubukla (3:1) ve kalın metinle anlatılıyor, yani zemin
        | PEKİŞTİRME. Ama pekiştirme görünmüyorsa hiç yok demektir —
        | 1,2 ölçümle bulunan algılanabilirlik tabanı: reddettiği değer
        | 1,04, kabul ettikleri 1,31 (açık) ve 1,47 (koyu).
        */
        expect($palet->kontrastOrani($zemin, $yuzey))
            ->toBeGreaterThanOrEqual(1.2, "{$tema}: etkin zemin / yan menü yüzeyi");
    }
});

it('★★ CUBUK vurgu-metin kullaniyor — vurgu DEGIL', function () {
    /*
    | Ölçümün bulduğu kusurun yapısal karşılığı. Değer testi eşiği
    | ölçüyor; bu test SEBEBİ sabitliyor, yoksa biri `border-vurgu`
    | yazdığında hata "kontrast düştü" diye çıkar ve sebebi aranır.
    */
    expect(panelDuzeniKod())->toContain('border-vurgu-metin')
        ->and(panelDuzeniKod())->not->toMatch('/border-vurgu(?!-metin)/');
});

/*
|--------------------------------------------------------------------------
| 4.6AF.1 — GERÇEK TARAYICI KOŞUSUNUN BULDUKLARI
|--------------------------------------------------------------------------
|
| Yukarıdaki testler yeşilken 375px'te 14 sayfanın 5'i hâlâ yatay
| kayıyordu ve bir sayfada iki tablo 118px'e sıkışmıştı. Yani sözleşme
| testleri "kap var mı"yı ölçüyordu, "ekranda ne oluyor"u değil.
| Aşağıdakiler tarayıcının bulduğu kusurların yapısal karşılığı.
*/

it('★★★ PANELDE KOSULSUZ COK SUTUNLU IZGARA YOK — mobilde iceriği ezer', function () {
    /*
    | ⚠️ 11 tane vardı ve hiçbiri kırılma noktası taşımıyordu. Bedeli
    | yalnızca taşma değil SIKIŞMA: Personel'de iki tablo 375px'te
    | 118px'lik iki sütuna giriyordu — okunacak hiçbir şey kalmıyordu.
    | ⚠️ Taşmayı ölçen tarama bunu GÖREMEZDİ: sıkışan içerik taşmıyor.
    */
    $kotu = [];

    foreach (panelSayfalari() as $yol) {
        foreach (explode("\n", yorumsuz($yol)) as $i => $satir) {
            if (preg_match_all('/(?<![a-z:-])grid-cols-([2-9])/', $satir, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $es) {
                    $onek = substr($satir, max(0, $es[1] - 4), 4);

                    if (! preg_match('/(sm|md|lg|xl):$/', $onek)) {
                        $kotu[] = basename($yol).':'.($i + 1).' → '.$es[0];
                    }
                }
            }
        }
    }

    expect($kotu)->toBe([]);
});

it('★★★ SAYFALAMA CEVIRISI VAR — yoksa ekranda "pagination.next" YAZAR', function () {
    /*
    | ⚠️ lang/tr/pagination.php HİÇ YOKTU. Laravel çeviri bulamayınca
    | anahtarın kendisini basıyor: dört panel sayfasında birden düğmede
    | "pagination.previous" / "pagination.next" yazıyordu.
    |
    | ⚠️ 4.6AA'daki `validation.uploaded` ile AYNI AİLE — orada da
    | "unutulursa hemen fark edilir" denmişti ve fark edilmemişti.
    | 963 testin hiçbiri görmedi; gerçek tarayıcı koşusu gördü.
    */
    expect(File::exists(base_path('lang/tr/pagination.php')))->toBeTrue();

    foreach (['previous', 'next'] as $anahtar) {
        $ceviri = __('pagination.'.$anahtar);

        expect($ceviri)->not->toBe('pagination.'.$anahtar)
            ->and($ceviri)->toMatch('/[a-zçğıöşü]/iu');
    }
});

it('★★★ SAYFALAMA SATIRI SARIYOR — dar ekranda tasmasin', function () {
    $kotu = [];

    foreach (panelSayfalari() as $yol) {
        $icerik = yorumsuz($yol);

        // sayfalama satırı: içinde `.links` döngüsü olan kap
        if (! str_contains($icerik, '.links"')) {
            continue;
        }

        foreach (explode("\n", $icerik) as $i => $satir) {
            if (preg_match('/class="[^"]*\bflex\b[^"]*"/', $satir)
                && preg_match('/(links\.length|last_page)/', $satir)
                && ! str_contains($satir, 'flex-wrap')) {
                $kotu[] = basename($yol).':'.($i + 1);
            }
        }
    }

    expect($kotu)->toBe([]);
});
