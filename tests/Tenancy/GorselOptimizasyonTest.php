<?php

use App\Domain\Catalog\ImageOptimizer;
use App\Domain\Catalog\ImageTooLargeException;
use App\Domain\Catalog\ProductImageService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\UnsupportedImageTypeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| GÖRSEL OPTİMİZASYONU (4.6AA)
|
| ★ Marka panelden telefonundan çıkmış JPEG/PNG yüklüyor; aynı kalitede
| WebP yaklaşık YARISI kadar yer kaplıyor ve vitrin sayfası onlarca görsel
| taşıyor. Fark doğrudan açılış hızına yazılıyor.
|
| ⚠️ BU DOSYADA `UploadedFile::fake()` KULLANILMIYOR (dönüşümü ölçen
| testlerde). Fake, MIME türünü UZANTIDAN uyduruyor ve içeriği gerçek bir
| görsel değil — yani "4000 piksellik dosya küçüldü mü" sorusunu
| soramazsın. 4G'de aynı ders çıkarılmıştı: içerik tabanlı bir davranışı
| ölçen test GERÇEK dosya yazmalı.
*/

/** Gerçek bir görsel dosyası üretir ve `UploadedFile` olarak sarar. */
function gercekGorsel(int $genislik, int $yukseklik, string $tur = 'jpeg', bool $saydam = false): UploadedFile
{
    // ⚠️ Statik analiz `int<1, max>` istiyor; sıfır/negatif tuval anlamsız.
    $g = imagecreatetruecolor(max(1, $genislik), max(1, $yukseklik));

    if ($saydam) {
        imagealphablending($g, false);
        imagesavealpha($g, true);
        imagefill($g, 0, 0, (int) imagecolorallocatealpha($g, 0, 0, 0, 127));
    }

    // ⚠️ Düz renk KULLANILMIYOR: tek renkli tuval her biçimde birkaç yüz
    // bayta iniyor ve "WebP daha küçük mü" sorusu anlamsızlaşıyor.
    for ($i = 0; $i < 200; $i++) {
        imagefilledellipse(
            $g, random_int(0, $genislik), random_int(0, $yukseklik),
            random_int(10, 120), random_int(10, 120),
            (int) imagecolorallocate($g, random_int(0, 255), random_int(0, 255), random_int(0, 255))
        );
    }

    $yol = tempnam(sys_get_temp_dir(), 'gorsel').'.'.($tur === 'jpeg' ? 'jpg' : $tur);

    match ($tur) {
        'png' => imagepng($g, $yol),
        'webp' => imagewebp($g, $yol, 90),
        default => imagejpeg($g, $yol, 92),
    };

    imagedestroy($g);

    // ⚠️ `new UploadedFile(...)` — `fake()` DEĞİL: gerçek içerik gerekiyor.
    return new UploadedFile($yol, basename($yol), null, null, true);
}

/**
 * Yalnızca BAŞLIĞI olan, devasa boyut BEYAN EDEN bir PNG üretir.
 *
 * ⚠️ Piksel verisi yok — dosya 100 bayttan küçük. `getimagesize()` yalnızca
 * IHDR'ı okuduğu için beyan edilen boyutu görüyor; koruma da tam burada,
 * görsel açılmadan devreye giriyor.
 */
function devasaPngBasligi(int $genislik, int $yukseklik): string
{
    $ihdr = 'IHDR'.pack('N2', $genislik, $yukseklik).pack('C5', 8, 2, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n"
        .pack('N', 13).$ihdr.pack('N', crc32($ihdr))
        .pack('N', 0).'IEND'.pack('N', crc32('IEND'));
}

it('★★★ JPEG WebP e cevriliyor ve DOSYA KUCULUYOR', function () {
    markaKur('opt-a.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    $dosya = gercekGorsel(1200, 900, 'jpeg');
    $oncekiBoyut = $dosya->getSize();

    $gorsel = app(ProductImageService::class)->yukle($urun, $dosya);

    expect($gorsel->path)->toEndWith('.webp');

    $yeniBoyut = Storage::disk('public')->size($gorsel->path);

    /*
    | ⚠️ İddia "dönüştürüldü" DEĞİL "KÜÇÜLDÜ": uzantıyı değiştirip aynı
    | baytları yazmak da testi geçerdi. Bloğun varlık sebebi boyut.
    */
    expect($yeniBoyut)->toBeLessThan((int) $oncekiBoyut);

    // ★ Diskteki dosya GERÇEKTEN WebP mi — ada değil İÇERİĞE bakılıyor.
    $tam = Storage::disk('public')->path($gorsel->path);

    expect(mime_content_type($tam))->toBe('image/webp');
});

it('★★★ BUYUK gorsel KUCULTULUYOR — oran korunarak', function () {
    markaKur('opt-b.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    // 3000×1500 → en uzun kenar 2048'e inmeli, oran 2:1 kalmalı.
    $gorsel = app(ProductImageService::class)->yukle($urun, gercekGorsel(3000, 1500, 'jpeg'));

    $boyut = getimagesize(Storage::disk('public')->path($gorsel->path));

    expect($boyut)->not->toBeFalse();

    expect($boyut === false ? 0 : $boyut[0])->toBe(ImageOptimizer::MAKS_KENAR)
        ->and($boyut === false ? 0 : $boyut[1])->toBe(ImageOptimizer::MAKS_KENAR / 2);
});

it('★★ KUCUK gorsel BUYUTULMUYOR', function () {
    markaKur('opt-c.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    /*
    | ⚠️ Küçültme tek yönlü olmalı. `imagecopyresampled` büyütmeyi de
    | yapardı ve sonuç bulanık, dosya gereksiz büyük olurdu.
    */
    $gorsel = app(ProductImageService::class)->yukle($urun, gercekGorsel(400, 300, 'png'));

    $boyut = getimagesize(Storage::disk('public')->path($gorsel->path));

    expect($boyut)->not->toBeFalse();

    expect($boyut === false ? 0 : $boyut[0])->toBe(400)
        ->and($boyut === false ? 0 : $boyut[1])->toBe(300);
});

it('★★★ SAYDAMLIK korunuyor — PNG siyah zeminle kaydedilmiyor', function () {
    markaKur('opt-d.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    /*
    | ⚠️ `imagecreatetruecolor` OPAK SİYAH tuval üretiyor. `imagealphablending`
    | ve `imagesavealpha` konmasaydı saydam PNG'ler siyah zeminle
    | kaydedilirdi — ürün görselinde saydam zemin yaygın ve bozulma
    | vitrinde görülene kadar fark edilmezdi.
    */
    $gorsel = app(ProductImageService::class)->yukle($urun, gercekGorsel(3000, 2000, 'png', saydam: true));

    $tam = Storage::disk('public')->path($gorsel->path);
    $cikti = imagecreatefromwebp($tam);

    expect($cikti)->toBeInstanceOf(GdImage::class);

    /** @var GdImage $cikti */
    $renk = imagecolorat($cikti, 5, 5);
    $alfa = ($renk & 0x7F000000) >> 24;

    imagedestroy($cikti);

    // 127 = tamamen saydam. Saydamlık kaybolsaydı 0 (opak) olurdu.
    expect($alfa)->toBeGreaterThan(100);
});

it('★★★ SIKISTIRMA BOMBASI reddediliyor — dosya KUCUK olsa bile', function () {
    markaKur('opt-e.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    /*
    | ⚠️ Bomba GD İLE ÜRETİLMİYOR, başlığı ELLE yazılıyor — ve bu tesadüf
    | değil: 6000×5000 bir tuvali oluşturmak testin KENDİSİNİN 120 MB
    | istemesi demek. İlk hâli tam bu yüzden `memory_limit` ile düştü.
    |
    | Elle yazmak iddiayı ayrıca GÜÇLENDİRİYOR: dosyanın piksel verisi
    | hiç yok, yalnızca IHDR'da yazan boyut var. Yani test, korumanın
    | görseli AÇMADAN reddettiğini kanıtlıyor — sıkıştırma bombası
    | korumasının tek anlamlı biçimi bu.
    */
    $yol = tempnam(sys_get_temp_dir(), 'bomba').'.png';
    file_put_contents($yol, devasaPngBasligi(6000, 5000));

    $dosya = new UploadedFile($yol, 'bomba.png', null, null, true);

    expect($dosya->getSize())->toBeLessThan(200)
        ->and($dosya->getMimeType())->toBe('image/png')
        ->and(6000 * 5000)->toBeGreaterThan(ImageOptimizer::MAKS_PIKSEL);

    expect(fn () => app(ProductImageService::class)->yukle($urun, $dosya))
        ->toThrow(ImageTooLargeException::class);
});

it('★★★ MIME i DOGRU ama ICERIGI BOZUK dosya reddediliyor', function () {
    markaKur('opt-f.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    /*
    | ⚠️ Doğru sihirli baytlarla başlayan ama kırpılmış bir dosya MIME
    | kontrolünü GEÇİYOR. Sessizce kaydedilseydi vitrinde kırık görsel
    | çıkar ve sebebi hiç anlaşılmazdı.
    */
    $yol = tempnam(sys_get_temp_dir(), 'bozuk').'.jpg';
    file_put_contents($yol, "\xFF\xD8\xFF\xE0".str_repeat("\x00", 500));

    $dosya = new UploadedFile($yol, 'bozuk.jpg', null, null, true);

    expect(fn () => app(ProductImageService::class)->yukle($urun, $dosya))
        ->toThrow(UnsupportedImageTypeException::class);
});

it('★★ ZATEN WebP olan dosya da isleniyor ve WebP kaliyor', function () {
    markaKur('opt-g.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    $gorsel = app(ProductImageService::class)->yukle($urun, gercekGorsel(800, 600, 'webp'));

    expect($gorsel->path)->toEndWith('.webp')
        ->and(mime_content_type(Storage::disk('public')->path($gorsel->path)))->toBe('image/webp');
});

it('★★★ BASLIGI GECERLI ama PIKSEL VERISI OLMAYAN dosya reddediliyor', function () {
    markaKur('opt-h.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    /*
    | ⚠️ BU TEST BİR KIRMA DENEMESİNİN BULDUĞU BOŞLUKTAN DOĞDU.
    |
    | `ac()` içindeki "açılamadı → reddet" dalı ÖLÇÜLMÜYORDU: onu kaldırıp
    | yerine 1×1 boş tuval koyduğumda testlerin hepsi YEŞİL kaldı. Sebep,
    | öteki testin kırpılmış JPEG'inin zaten `getimagesize()` aşamasında
    | düşmesi — yani `ac()`'ye hiç gelinmiyordu.
    |
    | Bu dosya BAŞLIĞI GEÇERLİ (getimagesize okuyor, 10×10 diyor) ama
    | piksel verisi (IDAT) YOK — yani `imagecreatefrompng()` tam olarak o
    | dalı tetikliyor. Sessizce boş tuval yazılsaydı marka "yükledim ama
    | görsel boş çıktı" derdi ve sebebi hiç anlaşılmazdı.
    */
    $yol = tempnam(sys_get_temp_dir(), 'bossuz').'.png';
    file_put_contents($yol, devasaPngBasligi(10, 10));

    $dosya = new UploadedFile($yol, 'bossuz.png', null, null, true);

    // Başlık okunabiliyor — yani ilk kapıdan GEÇİYOR.
    expect(getimagesize($yol))->not->toBeFalse();

    expect(fn () => app(ProductImageService::class)->yukle($urun, $dosya))
        ->toThrow(UnsupportedImageTypeException::class);
});
