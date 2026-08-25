<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ürün görselleri — yükleme, sıralama, silme.
 *
 * ⚠️ Bu servis "hangi markadayım" diye SORMUYOR (M-2.7). Dosya doğru
 * klasöre, paketin dosya sistemi bootstrapper'ı `storage_path()`'i kiracıya
 * çevirdiği için gidiyor — ölçüldü:
 *   merkez  → storage/app/public/
 *   marka A → storage/tenant<id>/app/public/
 */
class ProductImageService
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

    /** Kabul edilen türler — istemcinin söylediğine değil, DOSYANIN kendisine bakılıyor. */
    public const IZINLI_TURLER = ['image/jpeg', 'image/png', 'image/webp'];

    /** Ürün başına en fazla görsel. */
    public const MAKS_GORSEL = 20;

    /**
     * @return Collection<int, ProductImage>
     */
    public function listele(Product $urun): Collection
    {
        return $urun->images()->orderBy('position')->orderBy('id')->get();
    }

    /**
     * Görseli marka klasörüne yazar ve kaydı açar.
     *
     * @throws TooManyImagesException
     * @throws UnsupportedImageTypeException
     */
    public function yukle(Product $urun, UploadedFile $dosya, ?ProductVariant $varyant = null, ?string $alt = null): ProductImage
    {
        $mevcut = $urun->images()->count();

        if ($mevcut >= self::MAKS_GORSEL) {
            throw new TooManyImagesException($mevcut, self::MAKS_GORSEL);
        }

        /*
        | ⚠️ Tür kontrolü DOSYANIN İÇERİĞİNDEN.
        |
        | `$dosya->getClientMimeType()` istemcinin söylediği şeydir ve
        | uydurulabilir. `getMimeType()` dosyayı okuyup karar veriyor.
        | Uydurulabilir olana güvenseydik `zararlı.php` dosyası
        | "image/jpeg" etiketiyle diske yazılabilirdi.
        */
        $tur = $dosya->getMimeType();

        if (! in_array($tur, self::IZINLI_TURLER, strict: true)) {
            throw new UnsupportedImageTypeException((string) $tur, self::IZINLI_TURLER);
        }

        /*
        | ⚠️ Dosya adı da uzantısı da İSTEMCİDEN ALINMIYOR.
        |
        | Gerçek adı kullansaydık: aynı adlı ikinci yükleme öncekini ezerdi,
        | Türkçe karakterli ad bazı dosya sistemlerinde bozulurdu ve
        | `../../` içeren bir ad yol dışına yazmayı denerdi.
        | Uzantı da gerçek türden türetiliyor.
        */
        /*
        | ★ HER GÖRSEL WebP'YE ÇEVRİLİYOR (4.6AA).
        |
        | Marka panelden genelde telefonundan çıkmış JPEG/PNG yüklüyor;
        | aynı kalitede WebP yaklaşık YARISI kadar yer kaplıyor ve vitrin
        | sayfası onlarca görsel taşıyor.
        |
        | ⚠️ Uzantı artık türden TÜRETİLMİYOR, SABİT: dosya ne yüklenirse
        | yüklensin diske WebP olarak gidiyor. Eski `match` bırakılsaydı
        | içerik WebP, adı `.jpg` olurdu — tarayıcı yine açardı ama
        | `Content-Type` yanlış çıkar ve dosya adı YALAN söylerdi.
        |
        | ⚠️ ÖNCEDEN YÜKLENMİŞ GÖRSELLER DÖNÜŞTÜRÜLMÜYOR. Kayıt yolu
        | kolonda saklı ve eski uzantılar çalışmaya devam ediyor; geriye
        | dönük dönüştürme ayrı bir iş (dosya taşıma + kolon güncelleme +
        | geri alınamaz kayıp riski). "Kolon sonradan eklendiyse geriye
        | dönük doldurma gerekir" tuzağının görsel hâli — burada BİLEREK
        | yapılmadı, unutulmadı.
        */
        $icerik = $this->optimizer->webpYap($dosya);

        $klasor = "products/{$urun->uuid}";
        $ad = Str::uuid7().'.webp';

        /*
        | ⚠️ Artık `putFileAs` DEĞİL `put`: diske yazılan şey yüklenen dosya
        | değil, dönüştürülmüş baytlar. Eski yorumun uyardığı tuzak
        | (`$dosya->get()` false döndürebilir) burada YOK — `webpYap()`
        | ya string döndürüyor ya istisna fırlatıyor.
        */
        Storage::disk('public')->put("{$klasor}/{$ad}", $icerik);

        $yol = "{$klasor}/{$ad}";

        $gorsel = new ProductImage(['alt' => $alt, 'position' => $mevcut]);
        $gorsel->path = $yol;
        $gorsel->product()->associate($urun);
        $gorsel->variant()->associate($varyant);
        $gorsel->save();

        return $gorsel;
    }

    /**
     * Sıralamayı verilen uuid dizisine göre yeniden yazar.
     *
     * @param  list<string>  $uuidSirasi
     */
    public function sirala(Product $urun, array $uuidSirasi): void
    {
        DB::transaction(function () use ($urun, $uuidSirasi) {
            foreach ($uuidSirasi as $sira => $uuid) {
                // ⚠️ ÜRÜNE DARALTILMIŞ güncelleme (1A.5 deseni): listeye
                // başka ürünün görsel uuid'si karıştırılsa bile o satır
                // sonuç kümesine girmiyor, sessizce atlanıyor.
                $urun->images()->where('uuid', $uuid)->update(['position' => $sira]);
            }
        });
    }

    /**
     * Kaydı ve DOSYAYI siler.
     *
     * ⚠️ Yalnızca satır silinseydi dosya diskte öksüz kalırdı; marka yıllar
     * içinde binlerce görsel yükleyip silecek ve kimse fark etmeyecekti.
     */
    public function sil(ProductImage $gorsel): void
    {
        DB::transaction(function () use ($gorsel) {
            Storage::disk('public')->delete($gorsel->path);
            $gorsel->delete();
        });
    }
}
