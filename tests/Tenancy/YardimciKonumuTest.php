<?php

use Illuminate\Support\Facades\File;

/*
| TEST YARDIMCILARININ KONUMU (4.6AI)
|
| ★ BU TEST BİR TEKRARDAN DOĞDU. Kural CLAUDE.md'de yazılıydı —
| "test yardımcısı İKİNCİ dosyada kullanılacaksa `tests/Pest.php`'ye
| taşınır" — ve TEK OTURUMDA ÜÇ KEZ unutuldu:
|
|   panelSayfalari()  4.6AG · dört test "tanımsız fonksiyon" ile düştü
|   vitrinliMarka()   4.6AH · yedi test düştü
|   sonucAdresi()     4.6AI · iki test düştü
|
| ⚠️ BELİRTİ DOSYA YÜKLEME SIRASINA BAĞLI: tüm süit koşarken yardımcı
| BULUNABİLİR (öteki dosya önce yüklenmişse) ama tek dosya koşulunca
| "tanımsız fonksiyon" gelir. Yani hata bazen görünmez.
|
| ⚠️ AYNI MADALYONUN ÖTEKİ YÜZÜ: test dosyalarındaki fonksiyonlar
| GLOBAL. İki dosyada aynı ad varsa PHP "cannot redeclare" ile ölür
| (4.5H'de yaşandı) — o yüzden çözüm kopyalamak değil TAŞIMAK.
|
| Yazılı kural üç kez tutmadı; artık ölçülüyor.
*/

/**
 * Test dosyalarında tanımlı global fonksiyonlar.
 *
 * @return array<string, list<string>> ad => tanımlandığı dosyalar
 */
function testYardimcilari(): array
{
    $harita = [];

    $dosyalar = array_filter(
        iterator_to_array(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path('tests'))
        )),
        fn ($d) => $d->isFile() && str_ends_with($d->getFilename(), '.php'),
    );

    foreach ($dosyalar as $d) {
        $yol = $d->getPathname();

        if (str_ends_with($yol, 'tests/Pest.php')) {
            continue;
        }

        $icerik = (string) File::get($yol);

        // ⚠️ Yorumlar ayıklanıyor: örnek kod içeren yorum yanlış eşleşir.
        $kod = (string) preg_replace('!/\*.*?\*/!s', '', $icerik);
        $kod = (string) preg_replace('!^\s*//.*$!m', '', $kod);

        if (preg_match_all('/^function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $kod, $m)) {
            foreach ($m[1] as $ad) {
                $harita[$ad][] = str_replace(base_path().'/', '', $yol);
            }
        }
    }

    return $harita;
}

it('★★★ IKI DOSYANIN kullandigi yardimci tests/Pest.php\'de', function () {
    $kacak = [];

    foreach (testYardimcilari() as $ad => $tanimlandigi) {
        $kullanan = [];

        foreach (array_filter(
            iterator_to_array(new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path('tests'))
            )),
            fn ($d) => $d->isFile() && str_ends_with($d->getFilename(), '.php'),
        ) as $d) {
            $icerik = (string) File::get($d->getPathname());

            /*
            | ⚠️ ÇAĞRIYI arıyoruz, tanımı değil: `function ad(` eşleşmesi
            | tanımlandığı dosyayı da "kullanıyor" sayardı ve test hiçbir
            | zaman kırmızıya dönmezdi.
            */
            if (preg_match('/(?<!function )\b'.preg_quote($ad, '/').'\s*\(/', $icerik)) {
                $kullanan[] = str_replace(base_path().'/', '', $d->getPathname());
            }
        }

        if (count($kullanan) > 1) {
            $kacak[] = $ad.' → '.implode(', ', $kullanan).' (tanım: '.implode(',', $tanimlandigi).')';
        }
    }

    expect($kacak)->toBe([]);
});

it('★★ AYNI ADLI yardimci IKI dosyada tanimli degil — "cannot redeclare"', function () {
    /*
    | ⚠️ 4.5H'de yaşandı (`koleksiyonluMagaza` iki dosyada): TEK DOSYA
    | koşarken testler yeşildi, iki dosya birlikte yüklenince PHP öldü.
    | Gösteren şey Larastan oldu, testler değil.
    */
    $cakisan = [];

    foreach (testYardimcilari() as $ad => $dosyalar) {
        if (count($dosyalar) > 1) {
            $cakisan[] = $ad.' → '.implode(', ', $dosyalar);
        }
    }

    expect($cakisan)->toBe([]);
});
