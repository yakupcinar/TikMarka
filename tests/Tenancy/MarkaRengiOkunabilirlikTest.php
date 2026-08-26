<?php

use App\Domain\Settings\BrandPalette;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\SettingGroup;

/*
| MARKA RENGİ OKUNABİLİRLİĞİ (4.6AD)
|
| ★ Bu, projede GÖRSEL BİR KARARIN ölçülebilir hâle geldiği ilk yer.
| Renklerin ekranda nasıl durduğu test edilemez ama KONTRAST ORANI bir
| sayı — ve WCAG eşiği bir kural. Yani "okunuyor mu" sorusu, "hangi
| marka rengi seçilirse seçilsin eşiği geçiyor mu" sorusuna çevrilebiliyor.
|
| ★ ÖLÇÜLEN KUSUR: geliştirme markasının rengi `#743467` ve ürün fiyatı
| koyu zeminde 2.02 kontrastla çıkıyordu (gereken 4.5), bağlantılar 2.43.
| 4.6AB'de bu risk YAZILMIŞTI ama önlemi alınmamıştı.
|
| ⚠️ Testler ZOR RENKLERLE koşuyor: çok koyu, çok açık, doygun ve nötr.
| Yalnızca varsayılan renkle koşulsaydı hiçbir şey ölçülmezdi — varsayılan
| zaten okunur.
*/

/** @return list<string> */
function zorMarkaRenkleri(): array
{
    return [
        '#743467', // geliştirme markası — kusurun bulunduğu renk
        '#000000', // saf siyah   — koyu temada görünmez olurdu
        '#ffffff', // saf beyaz   — açık temada görünmez olurdu
        '#0d1117', // neredeyse siyah
        '#fefce8', // neredeyse beyaz
        '#1e40af', // koyu mavi   — kullanıcının "mavi yazılar" dediği aile
        '#facc15', // parlak sarı — açık zeminde en zoru
        '#dc2626', // kırmızı
        '#059669', // yeşil
    ];
}

it('★★★ HANGI MARKA RENGI secilirse secilsin METIN OKUNUR — iki temada da', function () {
    $palet = app(BrandPalette::class);

    foreach (zorMarkaRenkleri() as $renk) {
        foreach (['#ffffff' => 'açık', '#1c1917' => 'koyu'] as $zemin => $ad) {
            $okunur = $palet->okunur($renk, $zemin);
            $oran = $palet->kontrastOrani($okunur, $zemin);

            /*
            | ⚠️ İDDİA "renk değişti" DEĞİL "OKUNUR OLDU". Sadece
            | dönüştürüldüğünü ölçseydik, yanlış yöne dönüştüren bir kod
            | da testi geçerdi.
            */
            expect($oran)->toBeGreaterThanOrEqual(
                BrandPalette::HEDEF_KONTRAST,
                "{$renk} rengi {$ad} zeminde okunur değil: {$oran}"
            );
        }
    }
});

it('★★★ ZATEN OKUNUR renk DEGISTIRILMIYOR — gereksiz mudahale yok', function () {
    $palet = app(BrandPalette::class);

    /*
    | ⚠️ Koyu bir renk AÇIK zeminde zaten okunur; dokunulmamalı. Her
    | rengi körlemesine karıştıran bir kod da öteki testi geçerdi ama
    | marka kimliğini gereksiz yere bozardı.
    */
    expect($palet->okunur('#1e40af', '#ffffff'))->toBe('#1e40af');
    expect($palet->okunur('#facc15', '#1c1917'))->toBe('#facc15');
});

it('★★★ MARKA RENGI UZERINDEKI yazi da okunur — beyaz sabit DEGIL', function () {
    $palet = app(BrandPalette::class);

    foreach (zorMarkaRenkleri() as $renk) {
        $uzeri = $palet->uzeri($renk);
        $oran = $palet->kontrastOrani($uzeri, $renk);

        /*
        | ⚠️ Düğme yazısı sabit beyaz olsaydı AÇIK marka rengi seçen
        | markanın düğmeleri okunmaz olurdu — beyaz üstüne beyaz.
        | Ölçüldü: `--zemin` kullanılıyordu ve koyu temada tam bunun
        | tersi kırılıyordu (siyah üstüne koyu mor).
        */
        expect($oran)->toBeGreaterThanOrEqual(
            BrandPalette::HEDEF_KONTRAST,
            "{$renk} üzerindeki yazı okunur değil: {$oran}"
        );
    }
});

it('★★★ SAYFADA iki tema icin AYRI okunur deger var', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    app(SettingsService::class)->yaz(SettingGroup::Theme, 'primary_color', '#743467');

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ Sunucu hangi temanın görüneceğini BİLMİYOR: seçim tarayıcıda ya
    | da işletim sisteminde. Tek değer gönderilseydi öteki temada yanlış
    | olurdu — bu yüzden ikisi de gönderiliyor.
    */
    preg_match('/:root \{(.*?)\n        \}/s', $html, $acik);
    preg_match('/:root\[data-tema="koyu"\] \{(.*?)\n        \}/s', $html, $koyu);

    expect($acik[1] ?? '')->toContain('--marka-metin')
        ->and($koyu[1] ?? '')->toContain('--marka-metin');

    $palet = app(BrandPalette::class);

    preg_match('/--marka-metin: (#[0-9a-f]{6})/', $acik[1] ?? '', $a);
    preg_match('/--marka-metin: (#[0-9a-f]{6})/', $koyu[1] ?? '', $k);

    // ★ İki değer FARKLI olmalı — aynıysa biri yanlış zemine göre üretilmiş.
    expect($a[1] ?? '')->not->toBe($k[1] ?? '');

    expect($palet->kontrastOrani($a[1] ?? '#000', '#ffffff'))->toBeGreaterThanOrEqual(BrandPalette::HEDEF_KONTRAST);
    expect($palet->kontrastOrani($k[1] ?? '#000', '#1c1917'))->toBeGreaterThanOrEqual(BrandPalette::HEDEF_KONTRAST);
});

it('★★★ FIYAT MARKA RENGINDE DEGIL — notr metin rengi', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ Kullanıcı kararı ve rakip ölçümüyle uyumlu: Trendyol,
    | Hepsiburada ve Shopify Dawn'ın üçü de fiyatı NÖTR renkte yazıyor.
    | Fiyat bir VURGU değil, okunması gereken bir BİLGİ.
    */
    expect($html)->toContain('.kart .fiyat { color: var(--metin)')
        ->and($html)->not->toContain('.kart .fiyat { color: var(--marka)');
});

it('★★ MARKA RENGI KENDISI KORUNUYOR — turetme onu ezmiyor', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    app(SettingsService::class)->yaz(SettingGroup::Theme, 'primary_color', '#743467');

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ `--marka` markanın seçtiği renk olarak KALIYOR (düğme zemini,
    | vurgu). Ezilseydi marka kimliği kaybolurdu — 4.6AB'de verilen
    | kararın aynısı.
    */
    expect($html)->toContain('--marka: #743467');
});

it('★★★ BAGLANTILARIN kendi rengi VAR — tarayici varsayilanina dusmuyor', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $html = (string) $this->get('http://marka-a.test/kategoriler')->assertOk()->getContent();

    /*
    | ⚠️ BU TESTİ TARAYICI ÖLÇÜMÜ YAZDIRDI, kod okuması değil.
    |
    | Genel bir `a` renk kuralı YOKTU; stillenmemiş her bağlantı
    | tarayıcının varsayılan mavisine (`#0000ee`) düşüyordu. Açık zeminde
    | kontrast 9.0 ile sorunsuz, KOYU zeminde **1.72** — yani kategori
    | listesindeki bağlantılar koyu temada neredeyse görünmüyordu.
    |
    | ⚠️ Belirti sinsiydi: hiçbir kural YANLIŞ değildi, EKSİKTİ. 4.6AB'de
    | renkler belirtece çevrilirken yalnızca YAZILMIŞ renkler tarandı;
    | tarayıcı varsayılanı taramaya hiç girmedi. "Sabit renk kalmadı"
    | testi bu yüzden yeşil kalıyordu.
    */
    preg_match('/<style>(.*?)<\/style>/s', $html, $eslesme);
    $stil = $eslesme[1] ?? '';

    expect($stil)->toContain('a { color: var(--baglanti); }');

    // ★ Ve o belirteç iki temada da OKUNUR olmalı.
    $palet = app(BrandPalette::class);

    preg_match('/:root \{.*?--baglanti: (#[0-9a-f]{6})/s', $stil, $a);
    preg_match('/:root\[data-tema="koyu"\] \{.*?--baglanti: (#[0-9a-f]{6})/s', $stil, $k);

    expect($palet->kontrastOrani($a[1] ?? '#fff', '#ffffff'))->toBeGreaterThanOrEqual(4.5)
        ->and($palet->kontrastOrani($k[1] ?? '#000', '#1c1917'))->toBeGreaterThanOrEqual(4.5);
});

it('★★★ KONTROL SINIRLARI WCAG 1.4.11 esigini geciyor — iki temada da', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    preg_match('/<style>(.*?)<\/style>/s', $html, $m);
    $stil = $m[1] ?? '';

    $palet = app(BrandPalette::class);

    $oku = function (string $blok, string $ad) use ($stil): string {
        preg_match('/'.$blok.'\s*\{(.*?)\n\s*\}/s', $stil, $b);
        preg_match('/'.$ad.': (#[0-9a-f]{6})/', $b[1] ?? '', $d);

        return $d[1] ?? '';
    };

    /*
    | ⚠️ BU TESTİ TARAYICI ÖLÇÜMÜ YAZDIRDI. Ölçüldü: tasarımdaki HER
    | çizgi 3:1'in altındaydı — arama kutusunun sınırı açık temada 1.43,
    | koyu temada 2.12. Yani kontrolün sınırı pratikte yoktu ve az gören
    | bir müşteri kutuyu bulamıyordu.
    |
    | ⚠️ AYRAÇ ile KONTROL ayrı: ayraç dekoratif ve sakin kalabilir,
    | kontrolün sınırı WCAG 1.4.11'e göre ZORUNLU. Tek belirteç
    | kullanılsaydı ya kontroller görünmez ya ayraçlar gürültülü olurdu.
    */
    $acikKontrol = $oku(':root', '--kenar-koyu');
    $koyuKontrol = $oku(':root\[data-tema="koyu"\]', '--kenar-koyu');

    expect($palet->kontrastOrani($acikKontrol, '#fafaf9'))->toBeGreaterThanOrEqual(3.0)
        ->and($palet->kontrastOrani($koyuKontrol, '#232020'))->toBeGreaterThanOrEqual(3.0);

    // ★ Ayraç GÖRÜNÜR olmalı ama kontrol kadar güçlü DEĞİL.
    $acikAyrac = $oku(':root', '--kenar');
    $koyuAyrac = $oku(':root\[data-tema="koyu"\]', '--kenar');

    expect($palet->kontrastOrani($acikAyrac, '#fafaf9'))->toBeGreaterThan(1.4)
        ->and($palet->kontrastOrani($koyuAyrac, '#232020'))->toBeGreaterThan(1.4);

    expect($palet->kontrastOrani($acikAyrac, '#fafaf9'))
        ->toBeLessThan($palet->kontrastOrani($acikKontrol, '#fafaf9'));
});

it('★★★ URUN KARTI CERCEVESIZ — gorunmeyen cizgi yerine bosluk', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ ÖLÇÜLEN KUSUR: kart zemini / sayfa zemini 1.04, kart çizgisi /
    | kart zemini 1.26. Kart ne zeminden ayrışıyordu ne çizgisi
    | görünüyordu. İki tutarlı çıkıştan çerçeveyi KALDIRMAK seçildi
    | (kullanıcı kararı) — Hepsiburada ve Shopify Dawn'da da ölçüldü.
    */
    preg_match('/\.kart \{(.*?)\n        \}/s', $html, $m);
    $kural = $m[1] ?? '';

    expect($kural)->not->toBe('')
        ->and($kural)->not->toContain('border:')
        ->and($kural)->not->toContain('background:');
});
