<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Hook kurulumu — kural değil KİLİT
|--------------------------------------------------------------------------
|
| ★ Üç hook, üç GERÇEK olaydan doğdu ve üçü de CLAUDE.md'de YAZILI OLMASINA
| RAĞMEN tekrarlandı:
|
|   git checkout <dosya>   iki kez ısırdı (biri hiçbir şey yapmadı, öteki
|                          fazlasını geri aldı)
|   eşzamanlı süit         iki kez çökme, ikincisinde 142 test kırmızı
|   commit öncesi pint     CI bir kez bu yüzden kırmızı döndü
|
| Yazılı kural yetmedi; hook deterministik olarak engelliyor.
|
| ⚠️ BU TEST DAVRANIŞI ÖLÇMÜYOR — ölçemez: Pest KONTEYNERDE koşuyor ve
| orada `jq`, `python3`, `pgrep` yok, yani hook betikleri orada
| çalıştırılamıyor. Davranışı ölçen şey `.claude/hooks/hook-testi.sh`
| (host'ta, 13 vaka).
|
| Bu test o betiğin EKSİKSİZ kalmasını ölçüyor: kayıtlı her hook'un
| davranış testinde karşılığı olmalı. Yeni bir hook eklenip testi
| yazılmazsa CI kırmızı olur — "yazılı kural üç kez tutmadıysa test yaz"
| dersinin hook'lara uygulanmış hâli.
*/

/**
 * @return list<string>
 */
function kayitliHooklar(): array
{
    $ayar = json_decode(
        (string) file_get_contents(base_path('.claude/settings.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $betikler = [];

    foreach ($ayar['hooks'] ?? [] as $olaylar) {
        foreach ($olaylar as $grup) {
            foreach ($grup['hooks'] ?? [] as $h) {
                if (isset($h['command'])) {
                    $betikler[] = basename((string) $h['command']);
                }
            }
        }
    }

    return $betikler;
}

it('★★★ AYAR DOSYASI GECERLI ve hooklar kayitli', function () {
    $betikler = kayitliHooklar();

    expect($betikler)->not->toBeEmpty();

    foreach (['git-checkout-engel.sh', 'suit-kilidi.sh', 'pint-kapisi.sh'] as $beklenen) {
        expect($betikler)->toContain($beklenen);
    }
});

it('★★★ KAYITLI HER BETIK VAR ve CALISTIRILABILIR', function () {
    /*
    | ⚠️ Çalıştırma izni olmayan hook SESSİZCE çalışmıyor: Claude Code
    | onu koşturamaz, hata da vermez — kilit yokmuş gibi davranır.
    */
    foreach (kayitliHooklar() as $betik) {
        $yol = base_path(".claude/hooks/{$betik}");

        expect($betik.' var mı: '.(file_exists($yol) ? 'evet' : 'HAYIR'))
            ->toBe($betik.' var mı: evet');

        /*
        | ⚠️ `is_executable()` KULLANILMIYOR — ve bu bir kırma denemesinin
        | tutmamasından öğrenildi. Testler konteynerde ROOT olarak koşuyor
        | ve orada `is_executable()` çalıştırma biti HİÇ YOKKEN de `true`
        | dönüyor. Ölçüldü: dosya `-rw-r--r--` görünüyor, iddia yeşil.
        |
        | `fileperms()` stat'tan okuyor ve doğru değeri veriyor.
        */
        $bit = fileperms($yol) & 0111;

        expect($betik.' çalıştırma biti: '.($bit !== 0 ? 'var' : 'YOK'))
            ->toBe($betik.' çalıştırma biti: var');
    }
});

it('★★★ HER HOOKUN DAVRANIS TESTI VAR — testsiz hook eklenemez', function () {
    /*
    | ★ Asıl koruma bu. Davranış testi konteynerde koşamıyor, ama bir
    | hook'un testsiz eklenmesi CI'da yakalanıyor.
    |
    | ⚠️ Sadece "adı geçiyor mu" bakılmıyor — hem ENGELLEYEN hem İZİN
    | VEREN vakası olmalı. Yalnızca engelleme sınanan bir hook, meşru
    | komutları da engellediğinde fark edilmez.
    */
    $testBetigi = (string) file_get_contents(base_path('.claude/hooks/hook-testi.sh'));

    foreach (kayitliHooklar() as $betik) {
        $satirlar = array_filter(
            explode("\n", $testBetigi),
            fn (string $s): bool => str_contains($s, $betik) && str_starts_with(trim($s), 'bekle'),
        );

        $engel = array_filter($satirlar, fn (string $s): bool => str_contains($s, ' deny '));
        $izin = array_filter($satirlar, fn (string $s): bool => str_contains($s, ' izin '));

        /*
        | ⚠️ PARANTEZ ŞART — ve bu bir hatadan geldi. İlk hâli
        | `$betik.' … '.count($engel) > 0 ? 'var' : 'YOK'` yazıyordu:
        | PHP'de birleştirme (`.`) karşılaştırmadan (`>`) ÖNCE bağlanıyor,
        | yani ifade `("ornek.sh …: 3") > 0` oluyor ve sonuç sayı ne
        | olursa olsun `'var'`. Ölçüldü: 3 için de 0 için de "var".
        |
        | Yani iddia, engelleme vakası HİÇ YOKKEN de geçiyordu.
        */
        expect($betik.' engelleme vakası: '.(count($engel) > 0 ? 'var' : 'YOK'))
            ->toBe($betik.' engelleme vakası: var');

        expect($betik.' izin vakası: '.(count($izin) > 0 ? 'var' : 'YOK'))
            ->toBe($betik.' izin vakası: var');
    }
});

it('★★★ HOOKLAR CLAUDE.md DE ANLATILIYOR — devralan bilsin', function () {
    /*
    | ⚠️ Bir komutun neden engellendiğini bilmeyen devralan, hook'u
    | "bozuk" sanıp kaldırır — ve kilitlediği tuzak geri gelir.
    */
    expect((string) file_get_contents(base_path('CLAUDE.md')))
        ->toContain('.claude/hooks/');
});
