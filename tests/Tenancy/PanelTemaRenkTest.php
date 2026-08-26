<?php

use App\Domain\Settings\BrandPalette;
use Illuminate\Support\Facades\File;

/*
| PANEL KOYU TEMASI (4.6AE)
|
| ★ ÖLÇÜLEN DURUM: panelde 532 sabit renk sınıfı vardı ve tek bir `dark:`
| yoktu. Her kullanıma `dark:` eklemek 532 dokunuş demekti ve unutulan
| her biri SESSİZ bir kusur olurdu — koyu temada beyaz kalan bir kutu.
| 36 farklı sınıf anlamlı adlara indirildi.
|
| ⚠️ BU TESTLERİN ÖLÇEBİLECEĞİNİN SINIRI VAR: rengin ekranda nasıl
| durduğunu ölçemezler. Ölçtükleri şey SÖZLEŞME — sabit sınıf kaldı mı,
| koyu tema her belirteci karşılıyor mu, kontrast eşiği geçiliyor mu.
| Bunlar kırılırsa koyu tema SESSİZCE bozulur.
*/

function panelCss(): string
{
    return (string) File::get(base_path('resources/css/panel.css'));
}

/**
 * Panel CSS'i — YORUMLAR AYIKLANMIŞ.
 *
 * ⚠️ BU AYIRMA BİR KIRMA DENEMESİNİN BULDUĞU BOŞLUKTAN DOĞDU.
 * `@theme inline` ifadesi dosyada İKİ KEZ geçiyor: bir kez gerçek
 * yönerge olarak, bir kez de onu ANLATAN yorumda. İddia ham metne
 * baktığı için yönerge bozulsa bile yorum onu ayakta tutuyordu — deneme
 * hiçbir şeyi düşürmedi.
 *
 * ⚠️ 4.6AB'de aynı tuzağa düşülüp düzeltilmişti (sabit renk taraması
 * yorumları okuyordu). Aynı hata iki blok sonra tekrarlandı; ayıklama
 * artık yardımcıda.
 */
function panelCssKod(): string
{
    return (string) preg_replace('!/\*.*?\*/!s', '', panelCss());
}

it('★★★ PANELDE SABIT RENK SINIFI KALMADI', function () {
    $sinif = [];

    foreach (File::allFiles(base_path('resources/js/Panel')) as $dosya) {
        if ($dosya->getExtension() !== 'vue') {
            continue;
        }

        preg_match_all(
            '/\b(bg|text|border|ring|divide|placeholder)-[a-z]+-[0-9]{2,3}\b/',
            (string) File::get($dosya->getPathname()),
            $bulunan
        );

        foreach ($bulunan[0] as $s) {
            /*
            | ⚠️ Anlamlı adlar hariç tutuluyor: `bg-yuzey-2` da bu kalıba
            | uyuyor ama o zaten belirteç.
            */
            if (! preg_match('/-(zemin|yuzey|kenar|metin|soluk|vurgu|dugme|tehlike|uyari|basari|bilgi)(-[a-z0-9]+)?$/', $s)) {
                $sinif[$s] = ($sinif[$s] ?? 0) + 1;
            }
        }
    }

    /*
    | ⚠️ Kalan tek sabit sınıf `text-white` OLABİLİR ve doğrudur: iki
    | temada da SABİT kalan zeminlerde (turuncu düğme, yeşil düğme)
    | kullanılıyor. Bağlama bakılmadan çevrilseydi o düğmelerin yazısı
    | kırılırdı — ölçüldü, 27'si turuncu zeminde.
    */
    expect(array_keys($sinif))->toBe([]);
});

it('★★★ `@theme inline` KULLANILIYOR — duz `@theme` calisma aninda tema DESTEKLEMIYOR', function () {
    /*
    | ⚠️ ÖLÇÜLDÜ: düz `@theme` değeri KOPYALIYOR ve üretilen kural sabit
    | renk taşıyor (`background-color: #fff`). `inline` ise değişkene
    | REFERANS veriyor (`background-color: var(--p-yuzey)`). Çalışma
    | anında tema değişimi ancak ikincisiyle çalışıyor.
    |
    | Bu ayrım gözle görülmüyor: `@theme` yazılsa da derleme geçer,
    | panel açılır, yalnızca tema düğmesi HİÇBİR ŞEY YAPMAZ.
    */
    expect(panelCssKod())->toContain('@theme inline')
        ->and(panelCssKod())->not->toMatch('/@theme\s*\{/');
});

it('★★★ KOYU TEMA acik temanin HER belirtecini karsiliyor', function () {
    $css = panelCssKod();

    $blok = function (string $desen) use ($css): array {
        preg_match('/'.$desen.'\s*\{(.*?)\n\s*\}/s', $css, $m);
        preg_match_all('/(--p-[a-z0-9-]+):/', $m[1] ?? '', $b);

        return $b[1];
    };

    $acik = $blok(':root');
    $koyu = $blok(':root\[data-tema="koyu"\]');
    $sistem = $blok(':root:not\(\[data-tema="acik"\]\)');

    expect($acik)->not->toBe([]);

    /*
    | ⚠️ Eksik kalan belirteç koyu temada AÇIK değerinde kalır — sayfanın
    | çoğu koyu, bir kutu beyaz. Hata vermez, sadece yanlış görünür.
    */
    expect(array_diff($acik, $koyu))->toBe([], 'koyu temada eksik belirteç var');
    expect(array_diff($acik, $sistem))->toBe([], 'sistem temasında eksik belirteç var');
});

it('★★★ KONTROL SINIRI ve DUGME YAZISI esikleri geciyor — iki temada', function () {
    $css = panelCssKod();
    $palet = app(BrandPalette::class);

    $oku = function (string $blok, string $ad) use ($css): string {
        preg_match('/'.$blok.'\s*\{(.*?)\n\s*\}/s', $css, $b);
        preg_match('/'.$ad.': (#[0-9a-f]{6})/', $b[1] ?? '', $d);

        return $d[1] ?? '';
    };

    // ★ Kontrol sınırı — WCAG 1.4.11, 3:1 ZORUNLU.
    expect($palet->kontrastOrani($oku(':root', '--p-kenar-kontrol'), $oku(':root', '--p-yuzey')))
        ->toBeGreaterThanOrEqual(3.0);

    expect($palet->kontrastOrani(
        $oku(':root\[data-tema="koyu"\]', '--p-kenar-kontrol'),
        $oku(':root\[data-tema="koyu"\]', '--p-yuzey')
    ))->toBeGreaterThanOrEqual(3.0);

    /*
    | ★ DÜĞME YAZISI — ve bu kusur KOYU TEMA İŞİYLE GELMEDİ, önceden de
    | vardı. Turuncu `#ea580c` üzerinde beyaz yazı 3.56 kontrasttaydı;
    | ölçüm ortaya çıkardı ve zemin `#c2410c`'ye koyulaştırıldı (5.18).
    */
    foreach ([':root', ':root\[data-tema="koyu"\]'] as $tema) {
        expect($palet->kontrastOrani('#ffffff', $oku($tema, '--p-vurgu')))
            ->toBeGreaterThanOrEqual(4.5, "turuncu düğme yazısı okunmuyor: {$tema}");
    }

    // ★ Gövde metni.
    foreach ([':root', ':root\[data-tema="koyu"\]'] as $tema) {
        expect($palet->kontrastOrani($oku($tema, '--p-metin'), $oku($tema, '--p-zemin')))
            ->toBeGreaterThanOrEqual(4.5);
    }
});

it('★★★ TEMA BETIGI CSS TEN ONCE ve ANAHTAR VITRINDEN AYRI', function () {
    /*
    | ⚠️ BLADE YORUMLARI AYIKLANIYOR — aynı sebeple. `tikmarka-panel-tema`
    | hem betikte hem onu ANLATAN yorumda geçiyor; ham metinde ilk
    | eşleşme yorumdaki oluyor ve betik nereye taşınırsa taşınsın sıra
    | iddiası değişmiyordu. Kırma denemesi bunu gösterdi.
    */
    $html = (string) preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        (string) File::get(base_path('resources/views/panel/app.blade.php'))
    );

    $betik = mb_strpos($html, 'tikmarka-panel-tema');
    $vite = mb_strpos($html, '@vite');

    expect($betik)->not->toBeFalse()
        ->and($vite)->not->toBeFalse();

    /*
    | ⚠️ Betik CSS'ten SONRA gelseydi panel önce açık temayla boyanır,
    | sonra koyuya atlardı (FOUC) — vitrinde 4.6AB'de ölçülen kararın
    | aynısı.
    */
    expect($betik === false ? -1 : $betik)->toBeLessThan($vite === false ? -1 : $vite);

    /*
    | ⚠️ ANAHTAR VİTRİNDEN AYRI: panel BİZİM aracımız, vitrin markanın
    | sitesi (4C). Paylaşılsaydı personelin vitrinde yaptığı seçim paneli
    | de değiştirirdi — `localStorage` köken başına olduğu için aynı
    | marka alan adında ikisi çakışırdı.
    */
    expect($html)->not->toContain("'tikmarka-tema'");
});

it('★★ SISTEM KURALI kullanici secimini EZMIYOR', function () {
    /*
    | ⚠️ Personel açıkça "açık tema" dediyse sistem tercihi onu ezmemeli;
    | koruma olmasaydı gece modundaki makinede seçim hiç çalışmazdı.
    | Vitrinde bu korumanın ÖLÇÜLMEDİĞİ bir kırma denemesiyle bulunmuştu.
    */
    preg_match(
        '/@media \(prefers-color-scheme: dark\)\s*\{\s*([^{]+)\{/s',
        panelCssKod(),
        $m
    );

    expect(trim($m[1] ?? ''))->toContain(':not([data-tema="acik"])');
});
