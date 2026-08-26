<?php

namespace App\Domain\Settings;

/**
 * Marka renginden OKUNABİLİR varyantlar türetir. (4.6AD)
 *
 * ★ ÖLÇÜLEN KUSUR: marka rengi `#743467` olan geliştirme markasında ürün
 * fiyatı koyu zeminde **2.02** kontrastla çıkıyordu (WCAG AA gereği 4.5).
 * Bağlantılar 2.43'tü. 4.6AB'de bu risk YAZILMIŞTI ama önlemi
 * alınmamıştı — riski belgelemek, çözmek değildir.
 *
 * ⚠️ HESAP CSS'TE YAPILAMAZ. `color-mix()` karıştırma yapıyor ama
 * "yeterince okunur olana kadar karıştır" diyemiyor; kontrast oranı bir
 * KOŞUL ve CSS onu değerlendiremiyor. Bu yüzden sunucuda hesaplanıp
 * belirteç olarak gönderiliyor.
 *
 * ⚠️ MARKA RENGİNİN KENDİSİ DEĞİŞMİYOR. `--marka` markanın seçtiği renk
 * olarak kalıyor (düğme zemini, vurgu); türetilen varyantlar yalnızca
 * METİN gerektiğinde kullanılıyor. Marka rengini ezmek, marka kimliğini
 * silmek olurdu.
 *
 * ⚠️ Kiracıdan habersiz (M-2.7): rengi nereden aldığını bilmiyor.
 */
class BrandPalette
{
    /** WCAG AA — normal boyutlu metin. */
    public const HEDEF_KONTRAST = 4.5;

    /**
     * Metin için okunur marka rengi.
     *
     * Marka rengi verilen zemine karşı yeterince okunur değilse, okunur
     * olana kadar beyaza ya da siyaha doğru karıştırılıyor.
     *
     * ⚠️ Yön ZEMİNE göre seçiliyor: koyu zeminde beyaza, açık zeminde
     * siyaha. Sabit bir yön seçilseydi temalardan biri her zaman
     * bozulurdu.
     *
     * ⚠️ Karıştırma ADIM ADIM ve en fazla 20 adım: sonsuz döngü riski yok
     * ve en kötü durumda saf beyaz/siyaha varılıyor — o da her zaman
     * okunur.
     */
    public function okunur(string $marka, string $zemin): string
    {
        $renk = $this->coz($marka);
        $arka = $this->coz($zemin);

        // Zemin koyuysa beyaza, açıksa siyaha doğru gidiyoruz.
        $hedef = $this->parlaklik($arka) < 0.5 ? [255, 255, 255] : [0, 0, 0];

        for ($adim = 0; $adim <= 20; $adim++) {
            $aday = $this->karistir($renk, $hedef, $adim / 20);

            if ($this->kontrast($aday, $arka) >= self::HEDEF_KONTRAST) {
                return $this->hex($aday);
            }
        }

        return $this->hex($hedef);
    }

    /**
     * Marka rengi ÜZERİNE yazılacak metnin rengi.
     *
     * ⚠️ Düğme zemini marka rengi ve üstündeki yazı sabit beyaz yazılsaydı,
     * AÇIK bir marka rengi seçen markanın düğmeleri okunmaz olurdu —
     * beyaz üstüne beyaz. Burada beyaz ve siyahtan hangisi daha okunursa
     * o seçiliyor.
     */
    public function uzeri(string $marka): string
    {
        $renk = $this->coz($marka);

        return $this->kontrast([255, 255, 255], $renk) >= $this->kontrast([0, 0, 0], $renk)
            ? '#ffffff'
            : '#111111';
    }

    /** İki rengin WCAG kontrast oranı (1–21). */
    public function kontrastOrani(string $a, string $b): float
    {
        return $this->kontrast($this->coz($a), $this->coz($b));
    }

    /** @return array{int, int, int} */
    private function coz(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** @param array{int, int, int} $renk */
    private function hex(array $renk): string
    {
        return sprintf('#%02x%02x%02x', $renk[0], $renk[1], $renk[2]);
    }

    /**
     * @param  array{int, int, int}  $a
     * @param  array{int, int, int}  $b
     * @return array{int, int, int}
     */
    private function karistir(array $a, array $b, float $oran): array
    {
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $oran),
            (int) round($a[1] + ($b[1] - $a[1]) * $oran),
            (int) round($a[2] + ($b[2] - $a[2]) * $oran),
        ];
    }

    /**
     * Göreli parlaklık (WCAG 2.x).
     *
     * ⚠️ Kanallar DOĞRUSALLAŞTIRILIYOR (gamma çözülüyor). Ham RGB
     * ortalaması alınsaydı sarı ile mavi aynı parlaklıkta sayılırdı ve
     * hesap gözle uyuşmazdı.
     *
     * @param  array{int, int, int}  $renk
     */
    private function parlaklik(array $renk): float
    {
        $kanal = static function (int $deger): float {
            $o = $deger / 255;

            return $o <= 0.03928 ? $o / 12.92 : (($o + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $kanal($renk[0]) + 0.7152 * $kanal($renk[1]) + 0.0722 * $kanal($renk[2]);
    }

    /**
     * @param  array{int, int, int}  $a
     * @param  array{int, int, int}  $b
     */
    private function kontrast(array $a, array $b): float
    {
        $l1 = $this->parlaklik($a);
        $l2 = $this->parlaklik($b);

        [$ust, $alt] = $l1 > $l2 ? [$l1, $l2] : [$l2, $l1];

        return ($ust + 0.05) / ($alt + 0.05);
    }
}
