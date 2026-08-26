<?php

use App\Domain\Settings\BrandPalette;
use Illuminate\Support\Facades\File;

/*
| PANEL GÖRSEL DİLİ — ÖLÇEK, DERİNLİK, ETKİLEŞİM (4.6AG)
|
| ★ ÖLÇÜLEN DURUM (tarayıcıda, blok öncesi):
|     gölge kuralı        0        yazılmış odak stili   0
|     geçiş               2        yarıçap değeri        4 (vitrinde 6)
|     hover              18 / 25 sayfa
|     metin boyutu       225× 14px · 42× 12px · 23× 24px — 16px HİÇ YOK
|
| Yani hiyerarşi yoktu: sipariş numarası ile e-posta neredeyse aynı
| ağırlıktaydı.
|
| ⚠️ YOĞUNLUK KASITLI OLARAK KORUNDU (kullanıcı kararı): satır dolgusu
| değişmiyor. Sipariş listesinde 50+ kayıt olabiliyor ve "nefes payı" ile
| "bir ekranda kaç satır görüyorum" doğrudan çelişiyor.
|
| ⚠️ BU DOSYADAKİ HER İDDİA YORUMLARI AYIKLAR — 4.6AE'de iki kırma
| denemesi, iddia kuralı ANLATAN yorumu okuduğu için tutmamıştı.
*/

function gorselDilCss(): string
{
    return (string) preg_replace(
        '!/\*.*?\*/!s',
        '',
        (string) File::get(base_path('resources/css/panel.css'))
    );
}

it('★★★ YARICAP UC BASAMAGA indi — rol belirliyor, boyut degil', function () {
    $css = gorselDilCss();

    foreach (['--radius-sm: 6px', '--radius-lg: 10px', '--radius-xl: 14px'] as $basamak) {
        expect($css)->toContain($basamak);
    }

    /*
    | ⚠️ ÇIPLAK `rounded` KALMAMALI. Tailwind'de o sınıf `--radius`
    | değişkenine DEĞİL sabit .25rem'e bağlı — yani ölçeğin dışında
    | kalıyor ve belirteci değiştirmek onu düzeltmiyor.
    */
    $kalan = [];

    foreach (panelSayfalari() as $yol) {
        if (preg_match_all('/class="[^"]*\brounded\b(?!-)[^"]*"/', (string) File::get($yol), $m)) {
            $kalan[] = basename($yol).' → '.$m[0][0];
        }
    }

    expect($kalan)->toBe([]);
});

it('★★★ DERINLIK: acik temada golge, KOYU temada YUZEY BASAMAGI', function () {
    $css = gorselDilCss();

    // açık blok: @media'dan önceki kısım
    $acik = (string) preg_replace('/@media.*$/s', '', $css);
    $koyu = (string) preg_replace('/^.*\[data-tema="koyu"\]\s*\{/s', '', $css);

    /*
    | ⚠️ GÖLGE KONTRASTLA GÖRÜNÜR. Koyu zeminde koyu gölge yok
    | hükmünde; koyu tema derinliği yüzey açıklığıyla anlatıyor.
    | Gölge iki temaya da konsaydı açık temada derinlik olur, koyu
    | temada HİÇBİR ŞEY olmazdı — ve bu hata vermezdi.
    */
    expect($acik)->toMatch('/--p-golge-1:\s*0 1px 2px/');

    foreach ([1, 2, 3] as $kat) {
        expect($koyu)->toMatch('/--p-golge-'.$kat.':\s*none/');
    }
});

it('★★★ ODAK HALKASI YAZILDI — tarayici varsayilanina birakilmadi', function () {
    $css = gorselDilCss();

    /*
    | ⚠️ `:focus` DEĞİL `:focus-visible`: `:focus` fareyle tıklanan her
    | düğmeye halka takar ve marka bunu arıza sanar.
    |
    | ⚠️ "Odak stili yok" ilk ölçümde YANLIŞ okundu: `el.focus()`
    | `:focus-visible`'ı tetiklemiyor. Gerçek Tab ile ölçünce
    | tarayıcının VARSAYILAN halkası çıktı — sorun halkanın olmaması
    | değil, renginin ve kalınlığının bizde olmamasıydı.
    */
    expect($css)->toMatch('/:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--p-vurgu-metin\)/')
        ->and($css)->not->toMatch('/[^-]:focus\s*\{/');

    // hareket duyarlılığı: animasyon rahatsızlık değil ENGEL
    expect($css)->toContain('prefers-reduced-motion');

    /*
    | ⚠️ `transition: all` YASAK — yerleşim özellikleri de animasyona
    | girer ve tablo satırları kayarken sürüklenir.
    */
    expect($css)->not->toMatch('/transition(-property)?:\s*all/');
});

it('★★★ ODAK HALKASI ESIGI GECIYOR — iki temada', function () {
    $palet = app(BrandPalette::class);
    $css = gorselDilCss();

    $deger = function (string $ad, string $tema) use ($css): string {
        $govde = $tema === 'koyu'
            ? (string) preg_replace('/^.*\[data-tema="koyu"\]\s*\{/s', '', $css)
            : (string) preg_replace('/@media.*$/s', '', $css);

        preg_match('/--'.preg_quote($ad, '/').':\s*(#[0-9a-fA-F]{6})/', $govde, $m);

        return $m[1] ?? '';
    };

    foreach (['acik', 'koyu'] as $tema) {
        // WCAG 1.4.11 — odak göstergesi metin değil: eşik 3:1
        expect($palet->kontrastOrani($deger('p-vurgu-metin', $tema), $deger('p-yuzey', $tema)))
            ->toBeGreaterThanOrEqual(3.0, "{$tema}: odak halkası / yüzey");
    }
});

it('★★★ SUTUN BASLIGI VERIYLE AYNI AGIRLIKTA DEGIL', function () {
    /*
    | Başlık 14px `text-metin-2` iken satırın asıl verisiyle aynı
    | ağırlıktaydı — göz neye bakacağını bilmiyordu. Artık etiket:
    | 12px, büyük harf, soluk.
    */
    $kotu = [];

    foreach (panelSayfalari() as $yol) {
        if (preg_match_all('/<thead[^>]*class="([^"]*)"/', (string) File::get($yol), $m)) {
            foreach ($m[1] as $sinif) {
                if (! str_contains($sinif, 'text-xs') || ! str_contains($sinif, 'uppercase')) {
                    $kotu[] = basename($yol).' → '.$sinif;
                }
            }
        }
    }

    expect($kotu)->toBe([]);
});

it('★★★ YOGUNLUK KORUNDU — satir dolgusu BUYUMEDI', function () {
    /*
    | ⚠️ BU BİR KULLANICI KARARI, estetik tercih değil: sipariş
    | listesinde 50+ kayıt olabiliyor. "Nefes payı" ile "bir ekranda kaç
    | satır görüyorum" doğrudan çelişiyor ve marka ikincisini seçti.
    |
    | Tipografi büyüdü ama DOLGU büyümedi; bu testin varlık sebebi,
    | ileride biri "biraz ferahlatalım" diyerek kararı sessizce geri
    | almasın diye.
    */
    $buyuk = [];

    foreach (panelSayfalari() as $yol) {
        if (preg_match_all('/<td[^>]*class="([^"]*)"/', (string) File::get($yol), $m)) {
            foreach ($m[1] as $sinif) {
                if (preg_match('/\bp-([5-9]|1[0-9])\b|\bpy-([4-9]|1[0-9])\b/', $sinif)) {
                    $buyuk[] = basename($yol).' → '.$sinif;
                }
            }
        }
    }

    expect($buyuk)->toBe([]);
});
