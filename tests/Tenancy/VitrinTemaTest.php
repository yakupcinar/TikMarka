<?php

use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\SettingGroup;

/*
| KOYU TEMA VE MOBİL UYUM (4.6AB)
|
| ⚠️ BU TESTLERİN ÖLÇEBİLECEĞİ ŞEYİN SINIRI VAR VE AÇIKÇA YAZILIYOR.
| Sunucu HTML ve CSS gönderiyor; rengin ekranda nasıl göründüğünü,
| medya sorgusunun hangi genişlikte devreye girdiğini ya da düğmeye
| basınca ne olduğunu ÖLÇEMEZ — o tarayıcının işi.
|
| Ölçülebilen şey SÖZLEŞME: belirteçler tanımlı mı, koyu tema iki yoldan
| da geliyor mu, düğme sayfada mı, betik CSS'ten ÖNCE mi, kural
| gövdelerinde sabit renk KALMADI mı. Bunlar kırılırsa koyu tema
| sessizce çalışmaz.
|
| ⚠️ 4.6A'nın dersi burada da geçerli: iddia İKİ DÜZENİ de kapsamalı.
| Düzen paylaşılan `layout.blade.php`'den geliyor ama bunu ÖLÇMEK gerek —
| varsayım yeter demek 4.6A'da tam olarak yarım kalmaya yol açmıştı.
*/

function temaMagazasi(string $alanAdi = 'marka-a.test'): void
{
    markaKur($alanAdi);
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
}

it('★★★ KOYU TEMA IKI YOLDAN da geliyor — sistem VE acik secim', function () {
    temaMagazasi();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ Yalnızca `prefers-color-scheme` olsaydı müşteri seçemezdi;
    | yalnızca `data-tema` olsaydı sistemi koyu olan telefonda site
    | beyaz açılırdı.
    */
    expect($html)->toContain('prefers-color-scheme: dark')
        ->and($html)->toContain('[data-tema="koyu"]');

    /*
    | ⚠️ SİSTEM KURALI `:not([data-tema="acik"])` İLE KORUNMALI: müşteri
    | açıkça "açık tema" dediyse sistem tercihi onu EZMEMELİ. Koruma
    | olmasaydı gece modundaki telefonda "açık tema" seçimi hiç
    | çalışmazdı.
    |
    | ⚠️ İDDİA BELİRTEÇLERİ TANIMLAYAN BLOĞA BAKIYOR, sayfada bir yerde
    | geçmesine değil. İlk hâli öyleydi ve KIRMA DENEMESİ TUTMADI:
    | korumayı belirteç bloğundan kaldırdım, testler yeşil kaldı — çünkü
    | aynı ifade tema düğmesinin kurallarında da geçiyor. Korunması
    | gereken şey RENKLERİ tanımlayan bloktu.
    */
    preg_match_all(
        '/@media \(prefers-color-scheme: dark\)\s*\{\s*([^{]+)\{([^}]*)\}/s',
        $html,
        $bloklar,
        PREG_SET_ORDER
    );

    $belirtecBlogu = null;

    foreach ($bloklar as $blok) {
        if (str_contains($blok[2], '--zemin:')) {
            $belirtecBlogu = trim($blok[1]);
            break;
        }
    }

    expect($belirtecBlogu)->not->toBeNull()
        ->and($belirtecBlogu)->toContain(':not([data-tema="acik"])');
});

it('★★★ TEMA BETIGI CSS TEN ONCE — yoksa beyaz parlama olur', function () {
    temaMagazasi();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ Betik CSS'ten SONRA gelseydi sayfa önce açık temayla boyanır,
    | sonra koyuya atlardı (FOUC). Sıra ölçülmezse bir gün biri betiği
    | gövdenin sonuna taşır ve kimse fark etmez.
    */
    $betikYeri = mb_strpos($html, 'tikmarka-tema');
    $stilYeri = mb_strpos($html, '<style>');

    expect($betikYeri)->not->toBeFalse()
        ->and($stilYeri)->not->toBeFalse();

    // ⚠️ `-1` sabitleri: `strpos` `false` da dönebiliyor ve statik analiz
    // onu karşılaştırmaya sokmuyor. Bulunamama hâli zaten yukarıda ölçüldü.
    expect($betikYeri === false ? -1 : $betikYeri)
        ->toBeLessThan($stilYeri === false ? -1 : $stilYeri);
});

it('★★★ RENKLER BELIRTECTEN — kural govdesinde sabit renk YOK', function () {
    temaMagazasi();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    preg_match('/<style>(.*?)<\/style>/s', $html, $eslesme);
    $stil = $eslesme[1] ?? '';

    expect($stil)->not->toBe('');

    /*
    | ⚠️ BLOĞUN TEMEL İDDİASI. Sabit renk kural gövdesinde kalırsa koyu
    | temada O KURAL açık kalır — ve bu sessizdir: sayfanın çoğu koyu,
    | bir kutu beyaz. Belirteç tanımlarının kendisi hariç tutuluyor.
    */
    $tanimSonu = mb_strrpos($stil, '--golge: rgba(0, 0, 0, .4);');

    expect($tanimSonu)->not->toBeFalse();

    $kurallar = mb_substr($stil, (int) $tanimSonu);

    /*
    | ⚠️ YORUMLAR AYIKLANIYOR. Ölçüm sırasında fark edildi: bir CSS
    | yorumunda geçen renk kodu testi düşürüyor — oysa yorumdaki renk
    | ekranda hiçbir şey boyamıyor. Ayıklanmasaydı "koyu temada neden
    | böyle" diye yazılan bir açıklama testi kırardı ve yazan kişi
    | gerçek bir kusur sanırdı.
    */
    $kurallar = (string) preg_replace('!/\*.*?\*/!s', '', $kurallar);

    expect(preg_match('/#[0-9a-fA-F]{3,6}\b/', $kurallar))->toBe(0);
});

it('★★★ MARKA RENGI KORUNUYOR — koyu tema onu EZMIYOR', function () {
    temaMagazasi();

    app(SettingsService::class)->yaz(SettingGroup::Theme, 'primary_color', '#7c3aed');

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ Marka rengini değiştirme özelliği (4A) bu blokla kırılmamalı.
    | Koyu tema bloğu `--marka`'yı yeniden tanımlasaydı marka kimliği
    | koyu temada kaybolurdu.
    */
    expect($html)->toContain('--marka: #7c3aed');

    preg_match('/:root\[data-tema="koyu"\]\s*\{(.*?)\}/s', $html, $eslesme);

    expect($eslesme[1] ?? '')->not->toContain('--marka');
});

it('★★★ TEMA DUGMESI sayfada ve IKI DURUMLU', function () {
    temaMagazasi();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    /*
    | ⚠️ `aria-pressed` olmasaydı durum yalnızca simgeyle anlatılırdı ve
    | ekran okuyucu kullanan müşteri hangi temada olduğunu bilemezdi.
    |
    | ⚠️ FORM DEĞİL `type="button"`: form olsaydı her tıklama sayfayı
    | yeniden yükler ve müşteri ödeme formunda yazdıklarını kaybederdi.
    */
    expect($html)->toContain('data-tema-dugme')
        ->and($html)->toContain('aria-pressed')
        ->and($html)->toContain('type="button"');
});

it('★★★ MOBIL KIRILMA NOKTALARI tanimli — tablo SAYFAYI kaydirmiyor', function () {
    temaMagazasi();

    $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

    expect($html)->toContain('@media (max-width: 900px)')
        ->and($html)->toContain('@media (max-width: 620px)');

    /*
    | ⚠️ Tablo kuralı ÖLÇÜLÜYOR çünkü belirtisi en yanıltıcı olan oydu:
    | sipariş ayrıntısındaki tablo dar ekranda gövdeyi genişletiyor ve
    | BAŞLIK DÂHİL tüm sayfa yatay kayıyordu — sorun tabloda görünmüyordu.
    */
    expect($html)->toContain('overflow-x: auto');
});

it('★★★ IKI DUZEN de tema dugmesini ve belirtecleri tasiyor', function () {
    temaMagazasi();

    /*
    | ⚠️ 4.6A'nın dersi: özellik tek düzene uygulanıp öteki unutulabiliyor.
    | Burada ikisi de paylaşılan düzeni kullanıyor ama bu VARSAYIM olarak
    | bırakılmıyor — ölçülüyor.
    */
    foreach (['sade', 'vitrinli'] as $duzen) {
        app(SettingsService::class)->yaz(SettingGroup::Theme, 'layout', $duzen);

        $html = (string) $this->get('http://marka-a.test/')->assertOk()->getContent();

        expect($html)->toContain('data-tema-dugme')
            ->and($html)->toContain('--zemin:')
            ->and($html)->toContain('prefers-color-scheme: dark');
    }
});
