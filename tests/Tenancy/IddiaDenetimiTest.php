<?php

/*
| İDDİA DENETİMİ (A5)
|
| ★ BU TEST BİR KATALOGDAN DOĞDU. /kirma skill'i, kırma denemesi
| tutmadığında suçlunun genellikle KOD DEĞİL İDDİA olduğunu sekiz vakayla
| anlatıyor. Ama katalog bir YAZI; bu projede yazılı kural üç kez tutmazsa
| kural değil test yazılıyor (4.6AI) — ve bu kuralların ikisi zaten
| tekrarlandı:
|
|   ->not->toContain(a, b)   4.6AC'de bulundu, CLAUDE.md'ye yazıldı,
|                            B6'da TEKRARLANDI (en sert güvenlik kararını
|                            ölçen test hiçbir şey ölçmüyordu)
|   yorum ayıklama           4.6AB'de bulundu, 4.6AE'de İKİ BLOK SONRA
|                            tekrarlandı
|
| ⚠️ A5'te ölçüldü: `PanelMusteriTest` müşterinin kart RET GEREKÇESİNİN
| personele sızmadığını `->not->toContain('gerekce', 'hata',
| 'failure_reason')` ile "ölçüyordu". Kod gerekçeyi sızdıracak biçimde
| kırıldı ve dosyanın DOKUZ TESTİ DE YEŞİL KALDI. İddia tek tek yazılınca
| düştü. Yani mahremiyet kararı ölçüsüzdü ve bunu kimse fark etmemişti.
|
| ⚠️ BU TESTİN KENDİSİ DE TUZAĞA DÜŞEBİLİR: aranan kalıplar, kuralı
| ANLATAN yorumlarda da geçiyor (bu yorum bloğu dâhil). O yüzden tarama
| `testGovdeleri()` üzerinden yapılıyor — yorumlar ayıklanmış hâlde.
*/

/**
 * Bir fonksiyon çağrısındaki ÜST DÜZEY argüman sayısı.
 *
 * ⚠️ Virgül saymak yetmiyor: `not->toMatch('/[a-z]:focus\s*[,{]/')`
 * çağrısında virgül DİZGE İÇİNDE. Ölçüldü — kaba sayım bu satırı yanlış
 * alarm olarak veriyordu.
 *
 * @param  int  $ac  açılış parantezinin konumu
 */
function ustDuzeyArguman(string $kod, int $ac): int
{
    $derinlik = 0;
    $tirnak = null;
    $adet = 1;

    for ($i = $ac, $n = strlen($kod); $i < $n; $i++) {
        $k = $kod[$i];

        if ($tirnak !== null) {
            if ($k === '\\') {
                $i++;
            } elseif ($k === $tirnak) {
                $tirnak = null;
            }

            continue;
        }

        if ($k === "'" || $k === '"') {
            $tirnak = $k;
        } elseif ($k === '(' || $k === '[') {
            $derinlik++;
        } elseif ($k === ')' || $k === ']') {
            $derinlik--;

            if ($derinlik === 0) {
                return $adet;
            }
        } elseif ($k === ',' && $derinlik === 1) {
            $adet++;
        }
    }

    return $adet;
}

/**
 * Yorumsuz kaynakta bir kalıbın geçtiği "dosya:satır" konumları.
 *
 * @param  array<string, string>  $govdeler
 * @return list<string>
 */
function kalipKonumlari(array $govdeler, string $kalip, ?callable $suzgec = null): array
{
    $bulgular = [];

    foreach ($govdeler as $yol => $kod) {
        if (! preg_match_all($kalip, $kod, $m, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($m[0] as $eslesme) {
            if ($suzgec !== null && ! $suzgec($kod, (int) $eslesme[1], $yol)) {
                continue;
            }

            $bulgular[] = $yol.':'.(substr_count(substr($kod, 0, (int) $eslesme[1]), "\n") + 1);
        }
    }

    return $bulgular;
}

it('★★★ OLUMSUZ toContain TEK argumanli — cok argumanli hali biri eksikken GECIYOR', function () {
    /*
    | `toContain()` DEĞİŞKEN SAYIDA arananı kabul ediyor, mesaj argümanı
    | ALMIYOR. Olumsuzu tamamı üzerinde çalıştığı için argümanlardan biri
    | eksik olduğu anda iddia geçiyor ve ötekini hiç ölçmüyor.
    */
    $kacak = kalipKonumlari(
        testGovdeleri(),
        '/not->toContain\s*\(/',
        function (string $kod, int $ofset): bool {
            $ac = strpos($kod, '(', $ofset);

            return $ac !== false && ustDuzeyArguman($kod, $ac) > 1;
        },
    );

    expect($kacak)->toBe([], 'Cok argumanli olumsuz toContain: '.implode(' · ', $kacak));
});

it('★★ TESTLERDE is_executable YOK — konteynerde root olarak YALAN soyluyor', function () {
    /*
    | A2'de ölçüldü: çalıştırma biti HİÇ YOKKEN bile `true` dönüyor, çünkü
    | testler konteynerde root koşuyor. Bit kontrolü `fileperms() & 0111`.
    */
    $kacak = kalipKonumlari(testGovdeleri(), '/\bis_executable\s*\(/');

    expect($kacak)->toBe([], 'is_executable kullanan test: '.implode(' · ', $kacak));
});

it('★★★ JsonCevapTest postJson/getJson KULLANMIYOR — kullanirsa hicbir sey olcmez', function () {
    /*
    | 2E: `Accept` başlığı OLMAYAN istemci 500 alıyordu ve 425 testin hiçbiri
    | yakalamadı, çünkü `postJson`/`getJson` başlığı KENDİ ekliyor. Bu dosya
    | tam da o eksikliği ölçüyor; yardımcıları kullanırsa ölçtüğü şeyi
    | ortadan kaldırır.
    */
    $govdeler = array_filter(
        testGovdeleri(),
        fn (string $yol): bool => str_contains($yol, 'JsonCevapTest'),
        ARRAY_FILTER_USE_KEY,
    );

    expect($govdeler)->not->toBeEmpty('JsonCevapTest bulunamadi — tasindiysa bu denetim de guncellenmeli');

    $kacak = kalipKonumlari($govdeler, '/->(post|get|put|patch|delete)Json\s*\(/');

    expect($kacak)->toBe([], 'JsonCevapTest icinde Json yardimcisi: '.implode(' · ', $kacak));
});

it('★★★ HAM KAYNAKTA iddia eden test YORUM AYIKLIYOR', function () {
    /*
    | 4.6AE: bir kuralı ANLATAN yorum, kuralın kendisiyle aynı metni içerir.
    | Ham metinde arayan iddia, yönerge bozulsa bile yeşil kalıyor — iki
    | kırma denemesi bu yüzden tutmamıştı. Kural "ayıklama tek yerde olsun"
    | diye yazıldı; A5'te ölçüldü, yardımcı HİÇ YAZILMAMIŞTI.
    */
    $kacak = [];

    foreach (testGovdeleri() as $yol => $kod) {
        if (str_contains($yol, 'IddiaDenetimiTest') || str_contains($yol, 'tests/Pest.php')) {
            continue;
        }

        $kaynakOkuyor = preg_match(
            '/(app_path|base_path|resource_path)\(\s*[\'"][^\'"]+\.(php|blade\.php|vue|css|js)[\'"]/',
            $kod,
        );

        if ($kaynakOkuyor && preg_match('/toContain|toMatch/', $kod) && ! preg_match('/yorumsuz(Metin)?\s*\(/', $kod)) {
            $kacak[] = $yol;
        }
    }

    expect($kacak)->toBe([], 'Yorum ayiklamadan kaynak okuyan test: '.implode(' · ', $kacak));
});
