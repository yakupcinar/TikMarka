<?php

namespace App\Domain\Catalog;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Yüklenen görseli WebP'ye çevirir ve boyutunu sınırlar. (4.6AA)
 *
 * ★ NEDEN WebP: aynı görsel kalitesinde JPEG'in yaklaşık yarısı kadar yer
 * kaplıyor. Vitrin sayfası onlarca ürün görseli taşıyor; fark doğrudan
 * açılış hızına yazılıyor.
 *
 * ⚠️ AYRI SINIF, `ProductImageService`'in içinde DEĞİL: burası saf bir
 * dönüşüm (girdi bayt, çıktı bayt) ve tek başına ölçülebilmeli. Servisin
 * içine gömülseydi dönüşümü sınamak için her seferinde ürün, marka ve
 * disk kurmak gerekirdi.
 *
 * ⚠️ Kiracıdan habersiz (M-2.7): dosyanın nereye yazılacağını bilmiyor.
 */
class ImageOptimizer
{
    /**
     * En uzun kenar. Aşan görsel ORANI KORUNARAK küçültülüyor.
     *
     * ⚠️ 2048 keyfi değil: en büyük vitrin görseli tam ekran gösterimde
     * bunun altında kalıyor. Daha büyüğü müşteriye indirtmek, görmediği
     * pikseller için mobil veri harcatmak demek.
     */
    public const MAKS_KENAR = 2048;

    /**
     * Kabul edilen en fazla piksel — SIKIŞTIRMA BOMBASI koruması.
     *
     * ⚠️ Dosya boyutu sınırı buna yetmiyor: küçük bir dosya devasa bir
     * tuvale açılabilir. 24 MP pratikte her telefon ve fotoğraf
     * makinesini kapsıyor; 4000×3000 bir kare 12 MP.
     */
    public const MAKS_PIKSEL = 24_000_000;

    /**
     * WebP kalitesi.
     *
     * ⚠️ 82 ölçülerek seçilmedi, yaygın kabul gören denge: 90+ dosyayı
     * gözle görülür bir kazanç olmadan büyütüyor, 70 altı ürün
     * fotoğrafında bant izleri bırakıyor.
     */
    public const KALITE = 82;

    /**
     * Yüklenen dosyayı WebP baytlarına çevirir.
     *
     * @return string WebP içeriği
     *
     * @throws ImageTooLargeException|UnsupportedImageTypeException
     */
    public function webpYap(UploadedFile $dosya): string
    {
        $yol = $dosya->getRealPath();

        /*
        | ⚠️ Boyut AÇMADAN ÖNCE okunuyor. `getimagesize()` yalnızca başlığı
        | okuyor; sırayı ters çevirseydik bombayı önce belleğe açar, sonra
        | "çok büyük" derdik — yani koruma hiçbir işe yaramazdı.
        */
        $bilgi = $yol === false ? false : @getimagesize($yol);

        if ($bilgi === false) {
            throw new UnsupportedImageTypeException(
                (string) $dosya->getMimeType(),
                ProductImageService::IZINLI_TURLER,
            );
        }

        [$genislik, $yukseklik] = $bilgi;

        if ($genislik * $yukseklik > self::MAKS_PIKSEL) {
            throw new ImageTooLargeException($genislik, $yukseklik, self::MAKS_PIKSEL);
        }

        $kaynak = $this->ac($yol, (string) $dosya->getMimeType());

        try {
            $hedef = $this->kucult($kaynak, $genislik, $yukseklik);

            try {
                return $this->webpBaytlari($hedef);
            } finally {
                // ⚠️ Küçültme yeni bir tuval açtıysa o da serbest bırakılmalı.
                if ($hedef !== $kaynak) {
                    imagedestroy($hedef);
                }
            }
        } finally {
            /*
            | ⚠️ `finally` ŞART: istisna fırlarsa da tuval serbest kalmalı.
            | GD belleği PHP'nin çöp toplayıcısına bağlı değil; sızıntı
            | `memory_limit`'i sonraki isteklerde değil AYNI istekte
            | doldurur (toplu yükleme).
            */
            imagedestroy($kaynak);
        }
    }

    /** @throws UnsupportedImageTypeException */
    private function ac(string $yol, string $tur): GdImage
    {
        $gorsel = match ($tur) {
            'image/jpeg' => @imagecreatefromjpeg($yol),
            'image/png' => @imagecreatefrompng($yol),
            'image/webp' => @imagecreatefromwebp($yol),
            default => false,
        };

        /*
        | ⚠️ MIME kontrolünü GEÇEN dosya yine de AÇILAMAYABİLİR: bozuk ya da
        | kırpılmış bir görsel doğru sihirli baytları taşıyor olabilir.
        | Sessizce orijinali kaydetmek yerine reddediliyor — yoksa vitrinde
        | kırık görsel çıkardı ve sebebi hiç anlaşılmazdı.
        */
        if ($gorsel === false) {
            throw new UnsupportedImageTypeException($tur, ProductImageService::IZINLI_TURLER);
        }

        /*
        | ⚠️ ALFA AYARI KAYNAKTA DA ŞART — CI bunu yakaladı, yerel GD
        | affetmişti.
        |
        | İlk hâlinde yalnızca HEDEF tuvalde `imagealphablending(false)` +
        | `imagesavealpha(true)` vardı. İki ayrı bedeli oldu:
        |
        |   1. `imagecopyresampled` kaynağın alfasını hedefe KARIŞTIRARAK
        |      kopyalıyor; kaynakta harmanlama kapatılmazsa saydam
        |      pikseller opak çıkıyor. Yerelin libgd sürümü tolere etti,
        |      CI'ınki etmedi — testler yerelde YEŞİL, CI'da KIRMIZI.
        |
        |   2. Daha kötüsü: 2048'in ALTINDAKİ görsel hiç küçültülmüyor ve
        |      `kucult()` kaynağı olduğu gibi döndürüyor. O yolda hedef
        |      tuval hiç oluşmadığı için saydamlık HER GD SÜRÜMÜNDE
        |      kayboluyordu — ve testler bunu görmüyordu, çünkü saydamlık
        |      testi yalnızca büyük (küçültülen) görseli ölçüyordu.
        |
        | Ayar açılışta yapılınca iki yol da kapanıyor.
        */
        imagealphablending($gorsel, false);
        imagesavealpha($gorsel, true);

        return $gorsel;
    }

    private function kucult(GdImage $kaynak, int $genislik, int $yukseklik): GdImage
    {
        $enUzun = max($genislik, $yukseklik);

        if ($enUzun <= self::MAKS_KENAR) {
            return $kaynak;
        }

        $oran = self::MAKS_KENAR / $enUzun;
        $yeniG = max(1, (int) round($genislik * $oran));
        $yeniY = max(1, (int) round($yukseklik * $oran));

        $hedef = imagecreatetruecolor($yeniG, $yeniY);

        /*
        | ⚠️ SAYDAMLIK KORUNUYOR. Bu iki satır olmadan saydam PNG'ler SİYAH
        | zeminle kaydediliyor — ve ürün görselinde saydam zemin yaygın.
        | `imagecreatetruecolor` varsayılan olarak opak siyah bir tuval
        | üretiyor; alfa kanalı açıkça açılmalı.
        */
        imagealphablending($hedef, false);
        imagesavealpha($hedef, true);

        imagecopyresampled($hedef, $kaynak, 0, 0, 0, 0, $yeniG, $yeniY, $genislik, $yukseklik);

        return $hedef;
    }

    private function webpBaytlari(GdImage $gorsel): string
    {
        /*
        | ⚠️ `imagewebp()` doğrudan çıktıya yazıyor; dosya yolu vermek
        | geçici dosya yönetimi demek olurdu. Tampon yakalama bayt dizisini
        | doğrudan veriyor ve çağıran onu `Storage`'a yazıyor.
        */
        ob_start();
        imagewebp($gorsel, null, self::KALITE);

        return (string) ob_get_clean();
    }
}
