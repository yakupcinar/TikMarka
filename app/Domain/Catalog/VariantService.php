<?php

namespace App\Domain\Catalog;

use App\Domain\Search\ProductSearch;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Varyant — ekleme, düzenleme, toplu üretme.
 *
 * ★ Bu servisin asıl işi DOĞRULAMA: varyantın eksen değerleri ürünün
 * tanımıyla birebir uyuşmalı. Uyuşmazsa kayıt başarılı olur, stok o
 * varyanta yazılır ve müşteri onu hiçbir zaman seçemez — hatasız.
 */
class VariantService
{
    public function __construct(private readonly ProductSearch $arama) {}

    /** Ürün başına en fazla kaç varyant (1B-K4). */
    public const MAKS_VARYANT = 200;

    /**
     * @param  array<string, mixed>  $veri  sku · barcode · price · compare_at_price · cost_price · stock · is_active
     * @param  array<string, string>  $secenekler  eksen slug → değer slug
     *
     * @throws InvalidVariantOptionsException
     * @throws TooManyVariantsException
     */
    public function ekle(Product $urun, array $veri, array $secenekler = []): ProductVariant
    {
        $this->secenekleriDogrula($urun, $secenekler);
        $this->benzersizligiDogrula($urun, $secenekler);
        $this->skuDogrula($veri['sku'] ?? null);
        $this->sinirDogrula($urun, 1);

        /*
        | ⚠️ İlişki üzerinden — `product_id` $fillable dışında (1A.5 deseni).
        | `options` da $veri'den DEĞİL, doğrulanmış diziden geliyor:
        | istekten gelen ham değer doğrudan yazılsaydı doğrulamayı atlardı.
        */
        $varyant = $urun->variants()->make($veri);
        $varyant->options = $secenekler;
        $varyant->save();

        // ⚠️ SKU aramaya giriyor — varyant değişince tazelenmeli (2C).
        $this->urunuTazele($varyant);

        return $varyant;
    }

    /**
     * @param  array<string, mixed>  $veri
     * @param  array<string, string>|null  $secenekler  null → seçenekler değişmiyor
     *
     * @throws InvalidVariantOptionsException
     */
    public function guncelle(ProductVariant $varyant, array $veri, ?array $secenekler = null): ProductVariant
    {
        if ($secenekler !== null) {
            /*
            | ⚠️ `$varyant->product` değil `product()->firstOrFail()`:
            | ilişki tipi null olabilir görünüyor ve varyantın ürünsüz
            | kalması gerçekten bir veri bozukluğu olurdu — sessizce
            | geçmek yerine patlaması doğru.
            */
            /** @var Product $urun */
            $urun = $varyant->product()->firstOrFail();

            $this->secenekleriDogrula($urun, $secenekler);

            /*
            | ⚠️ GÜNCELLEMEDE DE ŞART — ölçüldü, burada HİÇBİR kontrol
            | yoktu. Bir varyantın seçeneklerini var olan bir birleşime
            | çevirmek ham veritabanı hatası veriyordu; ekleme yolundaki
            | koruma buraya hiç taşınmamıştı.
            */
            $this->benzersizligiDogrula($urun, $secenekler, $varyant);
            $varyant->options = $secenekler;
        }

        $this->skuDogrula($veri['sku'] ?? null, $varyant);

        $varyant->fill($veri);
        $varyant->save();

        // ⚠️ SKU aramaya giriyor — varyant değişince tazelenmeli (2C).
        $this->urunuTazele($varyant);

        return $varyant;
    }

    /**
     * Yumuşak siler.
     *
     * ⚠️ Sert silinseydi 1D'de siparişe bağlanan varyant satırı kaybolur,
     * geçmiş siparişin "ne satıldı" bilgisi kopardı.
     */
    public function sil(ProductVariant $varyant): void
    {
        $varyant->delete();
    }

    /**
     * Ürünün eksenlerinden TÜM kombinasyonları üretir; var olanları atlar.
     *
     * Marka 5 renk × 4 beden tanımlayıp tek tuşla 20 varyant açabilsin diye.
     * Fiyat ve stok hepsine aynı başlangıç değeriyle yazılıyor, sonra tek
     * tek düzenleniyor.
     *
     * @param  array<string, mixed>  $ortakVeri  price · stock · is_active
     * @return list<ProductVariant>
     *
     * @throws TooManyVariantsException
     */
    public function tumKombinasyonlariUret(Product $urun, array $ortakVeri, string $skuOneki): array
    {
        $eksenler = $urun->options()->with('values')->get();

        if ($eksenler->isEmpty()) {
            return [];
        }

        $kombinasyonlar = $this->kartezyen($eksenler);
        $mevcut = $urun->variants()->pluck('options')->map(fn ($o) => json_encode($o))->all();

        $yeniler = array_values(array_filter(
            $kombinasyonlar,
            fn (array $k) => ! in_array(json_encode($k), $mevcut, strict: true),
        ));

        // ⚠️ Sınır ÜRETİMDEN ÖNCE denetleniyor: yarısı yazılıp yarısı
        // reddedilen bir üretim, markayı elle temizlemeye zorlardı.
        $this->sinirDogrula($urun, count($yeniler));

        return DB::transaction(function () use ($urun, $yeniler, $ortakVeri, $skuOneki) {
            $uretilen = [];
            $sira = $urun->variants()->withTrashed()->count() + 1;

            foreach ($yeniler as $secenekler) {
                $varyant = $urun->variants()->make($ortakVeri);
                $varyant->sku = $skuOneki.'-'.$sira;
                $varyant->options = $secenekler;
                $varyant->save();

                $uretilen[] = $varyant;
                $sira++;
            }

            return $uretilen;
        });
    }

    /**
     * Varyantın seçenekleri ürünün eksenleriyle BİREBİR uyuşmalı.
     *
     * @param  array<string, string>  $secenekler
     *
     * @throws InvalidVariantOptionsException
     */
    private function secenekleriDogrula(Product $urun, array $secenekler): void
    {
        $eksenler = $urun->options()->with('values')->get();

        /** @var array<string, list<string>> $gecerli  eksen slug → değer slug'ları */
        $gecerli = $eksenler
            ->mapWithKeys(fn (Option $e) => [
                $e->slug => $e->values->map(fn (OptionValue $d) => $d->slug)->all(),
            ])
            ->all();

        $sorunlar = [];

        // Eksik anahtar: tanımlı eksen varyantta yok.
        foreach (array_keys($gecerli) as $eksenSlug) {
            if (! array_key_exists($eksenSlug, $secenekler)) {
                $sorunlar[] = "'{$eksenSlug}' ekseni eksik.";
            }
        }

        foreach ($secenekler as $eksenSlug => $degerSlug) {
            // Fazla anahtar: ürün bu ekseni kullanmıyor.
            if (! array_key_exists($eksenSlug, $gecerli)) {
                $sorunlar[] = "'{$eksenSlug}' bu üründe tanımlı bir eksen değil.";

                continue;
            }

            // Tanımsız değer: eksende böyle bir seçenek yok.
            if (! in_array($degerSlug, $gecerli[$eksenSlug], strict: true)) {
                $sorunlar[] = "'{$eksenSlug}' ekseninde '{$degerSlug}' diye bir değer yok.";
            }
        }

        if ($sorunlar !== []) {
            throw new InvalidVariantOptionsException($sorunlar);
        }
    }

    /**
     * Aynı seçenek birleşiminde ikinci varyant açılmasını engeller.
     *
     * ⚠️ Veritabanı kısıtı `(product_id, options)` ZATEN VARDI ve doğru
     * çalışıyordu — ama yakalanmadığı için panelde ham **500** görünüyordu.
     * Kontrol buraya konuyor, controller'a değil: aynı kural artisan
     * komutundan ya da tohumlayıcıdan da geçilebilmeli.
     *
     * ⚠️ Kısıt KALDIRILMIYOR. Bu kontrol ile veritabanı arasında yarış
     * durumu var (iki eşzamanlı istek ikisi de "yok" görebilir); kısıt
     * son savunma olarak duruyor.
     *
     * @param  array<string, string>  $secenekler
     *
     * @throws DuplicateVariantException
     */
    /**
     * @param  array<string, string>  $secenekler
     * @param  ProductVariant|null  $haric  güncellenen varyant kendisiyle çakışmasın
     *
     * @throws DuplicateVariantException
     */
    private function benzersizligiDogrula(Product $urun, array $secenekler, ?ProductVariant $haric = null): void
    {
        /*
        | ⚠️ `variants()` YUMUŞAK SİLİNMİŞLERİ GÖRMÜYOR ve bu DOĞRU:
        | 4.6X'te veritabanı kısıtı da kısmi indekse çevrildi
        | (`WHERE deleted_at IS NULL`). Öncesinde ikisi UYUŞMUYORDU —
        | Domain "boş" diyordu, veritabanı "dolu" diyordu ve marka ham
        | hata görüyordu.
        */
        $var = $urun->variants()
            ->when($haric !== null, fn ($q) => $q->whereKeyNot($haric?->getKey()))
            ->get()
            ->contains(fn (ProductVariant $v) => ($v->options ?? []) === $secenekler);

        if ($var) {
            throw new DuplicateVariantException($secenekler);
        }
    }

    /**
     * SKU marka genelinde benzersiz — CANLI varyantlar arasında.
     *
     * ⚠️ Kapsam ÜRÜN DEĞİL MARKA: veritabanı kısıtı `sku` tek başına.
     * Yalnızca ürün içinde arasaydık kontrol geçer, veritabanı yine
     * patlardı — yani kural iki yerde farklı olurdu.
     *
     * @throws DuplicateSkuException
     */
    private function skuDogrula(?string $sku, ?ProductVariant $haric = null): void
    {
        if ($sku === null || $sku === '') {
            return;
        }

        /*
        | ⚠️ `withTrashed()` ŞART — kural silinmişleri de kapsıyor
        | (4.6X.1) ve veritabanı kısıtı da öyle. Bakılmasaydı Domain
        | "boş" der, veritabanı "dolu" derdi ve marka yine ham hata
        | görürdü; 4.6X'te düzeltilen tuzağın aynısı ters yönde.
        */
        $cakisan = ProductVariant::withTrashed()
            ->where('sku', $sku)
            ->when($haric !== null, fn ($q) => $q->whereKeyNot($haric?->getKey()))
            ->first();

        if ($cakisan !== null) {
            throw new DuplicateSkuException($sku, $cakisan->trashed());
        }
    }

    /** @throws TooManyVariantsException */
    private function sinirDogrula(Product $urun, int $eklenecek): void
    {
        $mevcut = $urun->variants()->count();

        if ($mevcut + $eklenecek > self::MAKS_VARYANT) {
            throw new TooManyVariantsException($mevcut, self::MAKS_VARYANT);
        }
    }

    /**
     * Eksenlerin tüm kombinasyonları.
     *
     * 2 renk × 3 beden → 6 dizi. Kombinatorik patlamanın kaynağı ve
     * `MAKS_VARYANT` sınırının sebebi tam olarak bu fonksiyon.
     *
     * @param  Collection<int, Option>  $eksenler
     * @return list<array<string, string>>
     */
    private function kartezyen($eksenler): array
    {
        /** @var list<array<string, string>> $sonuc */
        $sonuc = [[]];

        foreach ($eksenler as $eksen) {
            $yeni = [];

            foreach ($sonuc as $kismi) {
                foreach ($eksen->values as $deger) {
                    $yeni[] = $kismi + [$eksen->slug => $deger->slug];
                }
            }

            $sonuc = $yeni;
        }

        return $sonuc;
    }

    /**
     * Varyantın ürününün arama alanlarını tazeler. (2C)
     *
     * ⚠️ İlişki boş dönebiliyor (silinmiş ürün); o durumda yapacak bir
     * şey yok, sessizce geçiliyor.
     */
    private function urunuTazele(ProductVariant $varyant): void
    {
        $urun = $varyant->product;

        if ($urun !== null) {
            $this->arama->tazele($urun);
        }
    }
}
