<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tuzak sayımı — bölme sırasında hiçbir tuzak kaybolmasın
|--------------------------------------------------------------------------
|
| ★ `CLAUDE.md` 1.318 satırdı ve belgelenen hedef 200; ölçüldü ki dosyanın
| %96'sı tuzak listesi. Uzun dosya bağlam yiyor ve UYUM ORANINI DÜŞÜRÜYOR —
| bu oturumda yazılı üç kural buna rağmen unutuldu.
|
| Tuzaklar yola bağlı kural dosyalarına TAŞINDI (kopyalanmadı). Bu test
| taşımanın kayıpsız olduğunu ölçüyor: bir tuzak yanlışlıkla silinirse ya
| da iki yere birden yazılırsa kırmızı olur.
|
| ⚠️ Sayı arttığında bu testin beklentisi de artırılır — ama BİLEREK.
| Beklentiyi düşürmek, bir tuzağın sessizce kaybolduğu anlamına gelir.
|
| ⚠️ SÜİT KOŞARKEN `CLAUDE.md` DÜZENLENMEZ: test dosyayı koştuğu anda
| okuyor. A2'de düzenlendi ve yerel koşu YANLIŞ SAYIYI yeşil gördü;
| gerçek durumu CI gösterdi.
*/

/**
 * Bir markdown dosyasındaki tuzak bloklarını döndürür.
 *
 * @return list<string>
 */
function tuzakBloklari(string $yol): array
{
    $metin = (string) file_get_contents($yol);

    $parcalar = preg_split('/\n(?=- \*\*)/', $metin) ?: [];

    return array_values(array_filter(
        array_map(rtrim(...), $parcalar),
        fn (string $p): bool => str_starts_with($p, '- **'),
    ));
}

it('★★★ TUZAKLARIN TOPLAMI DEGISMEDI — bolme kayipsiz', function () {
    $hepsi = tuzakBloklari(base_path('CLAUDE.md'));

    foreach (glob(base_path('.claude/rules/*.md')) ?: [] as $kural) {
        $hepsi = [...$hepsi, ...tuzakBloklari($kural)];
    }

    /*
    | ⚠️ SAYI ELLE ARTIRILIR — ve bu bilinçli. CI bunu bir kez yakaladı:
    | A2'de iki yeni tuzak eklendi (171 → 173) ve beklenti güncellenmedi.
    | Testin işi tam olarak buydu.
    |
    | Beklentiyi DÜŞÜRMEK bir tuzağın sessizce kaybolduğu anlamına gelir;
    | artırmak yeni tuzak eklendiği. İkisi de commit mesajında görünmeli.
    */
    expect($hepsi)->toHaveCount(174);
});

it('★★★ HICBIR TUZAK IKI YERDE DEGIL — kopya degil TASIMA', function () {
    /*
    | ⚠️ Kopyalanmış bir tuzak iki yerde bakım gerektirir ve biri
    | güncellenince öteki SESSİZCE bayat kalır.
    */
    $hepsi = tuzakBloklari(base_path('CLAUDE.md'));

    foreach (glob(base_path('.claude/rules/*.md')) ?: [] as $kural) {
        $hepsi = [...$hepsi, ...tuzakBloklari($kural)];
    }

    $basliklar = array_map(
        fn (string $b): string => (string) preg_replace('/\s+/', ' ', substr($b, 0, 80)),
        $hepsi,
    );

    expect(array_unique($basliklar))->toHaveCount(count($basliklar));
});

it('★★★ HER KURAL DOSYASININ paths FRONTMATTERI VAR', function () {
    /*
    | ★ `paths` OLMAYAN kural dosyası KOŞULSUZ yükleniyor — yani bölmenin
    | tek amacı olan bağlam kazancı sessizce yok oluyor. Dosya duruyor,
    | içerik doğru, hiçbir hata yok; sadece kazanç yok.
    */
    $kurallar = glob(base_path('.claude/rules/*.md')) ?: [];

    expect($kurallar)->not->toBeEmpty();

    foreach ($kurallar as $kural) {
        $metin = (string) file_get_contents($kural);

        expect(preg_match('/^---\n(.*?)\n---/s', $metin, $m))
            ->toBe(1, basename($kural).' frontmatter yok');

        /*
        | ⚠️ MESAJ ARGÜMANI YOK. İlk hâli `toContain('paths:', basename(...))`
        | yazıyordu; `toContain()` çok argümanlı — ikinci argüman mesaj değil
        | İKİNCİ ARANAN DEĞER. Yani iddia dosya adını da frontmatter'da
        | arıyordu ve doğru dosyada bile düşüyordu.
        |
        | ⚠️ Bu tuzak `CLAUDE.md`'de İKİ KEZ yazılı (4.6AC · B6) ve burada
        | ÜÇÜNCÜ kez yapıldı — hem de bölmeyi koruyan testin içinde.
        | Dosya adı mesaj olarak değil, dizgeye katılarak taşınıyor.
        */
        expect(basename($kural).': '.(str_contains($m[1] ?? '', 'paths:') ? 'var' : 'YOK'))
            ->toBe(basename($kural).': var');
    }
});

it('★★★ CLAUDE.md DEVRALANA kural dosyalarini SOYLUYOR', function () {
    /*
    | ⚠️ Söylemezse devralan kişi/ajan tuzakların bir kısmının başka
    | dosyada olduğunu bilmez ve eksik bağlamla çalışır — bölmenin
    | yarattığı TEK yeni risk bu.
    */
    expect((string) file_get_contents(base_path('CLAUDE.md')))
        ->toContain('.claude/rules/');
});

it('★★★ KURALLAR DOGRU DOSYADA YUKLENIYOR — ve YANLIS dosyada YUKLENMIYOR', function () {
    /*
    | ★ Bölmenin TEK amacı bağlam kazancı: `app/Domain/` içinde çalışırken
    | tasarım tuzakları yüklenmemeli. Bunu ölçmenin yolu desenleri tek tek
    | sınamak — dosyanın var olması kuralın doğru yüklendiğini göstermiyor.
    |
    | ⚠️ NEGATİF durumlar pozitiflerden ÖNEMLİ: hepsi eşleşen bir desen
    | yazılsaydı dosya bölünmüş olur ama kazanç sıfır olurdu, ve bu hata
    | vermezdi.
    */
    $desenler = [];

    foreach (glob(base_path('.claude/rules/*.md')) ?: [] as $kural) {
        $metin = (string) file_get_contents($kural);

        preg_match('/^---\n(.*?)\n---/s', $metin, $m);
        preg_match_all('/^\s*-\s*"(.+)"$/m', $m[1] ?? '', $y);

        $desenler[basename($kural, '.md')] = $y[1];
    }

    $beklenen = [
        'resources/css/panel.css' => ['tasarim'],
        'resources/views/storefront/sade/urun.blade.php' => ['tasarim', 'vitrin'],
        'app/Http/Panel/ReportPageController.php' => ['panel'],
        'app/Logging/IstekBaglami.php' => ['gozlem'],
        'config/logging.php' => ['gozlem'],

        'app/Http/Storefront/CartController.php' => ['vitrin'],
        'tests/Pest.php' => ['test'],
        'routes/tenant.php' => ['kiracilik'],
        'app/Models/Product.php' => ['veri'],
        'app/Domain/Payment/IyzicoProvider.php' => ['odeme'],

        // ⚠️ ASIL ÖLÇÜM — bunlar hâlâ hiçbir kural yüklememeli
        'app/Domain/Order/CheckoutService.php' => [],
        'app/Console/Commands/AuditStockCounters.php' => [],
        'bootstrap/app.php' => [],
    ];

    foreach ($beklenen as $dosya => $kurallar) {
        $eslesen = [];

        foreach ($desenler as $ad => $liste) {
            foreach ($liste as $desen) {
                if (fnmatch($desen, $dosya)) {
                    $eslesen[] = $ad;

                    break;
                }
            }
        }

        sort($eslesen);
        sort($kurallar);

        expect($dosya.' => '.implode(',', $eslesen))
            ->toBe($dosya.' => '.implode(',', $kurallar));
    }
});
