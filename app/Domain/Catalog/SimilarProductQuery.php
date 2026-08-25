<?php

namespace App\Domain\Catalog;

use App\Enums\PaymentStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ürün sayfasının altındaki öneriler. (4.6E)
 *
 * ⚠️ İKİ SORU AYRI: "buna benzer ne var" ve "en çok ne satılıyor".
 * Birleştirilseydi ekran hangisini gösterdiğini söyleyemezdi ve müşteri
 * "benzer" başlığı altında alakasız ama çok satan bir ürün görürdü.
 *
 * ⚠️ Kiracıdan habersiz (M-2.7).
 */
class SimilarProductQuery
{
    /** Bir bölümde gösterilen en fazla ürün. */
    public const LIMIT = 8;

    public function __construct(private readonly ProductQuery $urunler) {}

    /**
     * Benzer ürünler — ÜÇ KADEMELİ.
     *
     * 1. Aynı kategori ALT AĞACINDAN (4.6B'deki `kategoriyeGore`)
     * 2. Yetmezse aynı MARKA
     * 3. Yetmezse EN YENİLER
     *
     * ⚠️ Kademeler BİRBİRİNİ TAMAMLIYOR, biri ötekini elemiyor: tek
     * kademeli olsaydı kategorisi olmayan ya da kategorisinde tek ürün
     * bulunan sayfada bölüm boş kalırdı — ve boş bir "Benzer ürünler"
     * başlığı mağazayı bozuk gösterir.
     *
     * @return Collection<int, Product>
     */
    public function benzerler(Product $urun, int $limit = self::LIMIT): Collection
    {
        /** @var Collection<int, Product> $sonuc */
        $sonuc = new Collection;

        $kategori = $urun->category;

        if ($kategori !== null) {
            $sonuc = $this->temel($urun, $sonuc)
                ->tap(fn ($q) => $this->urunler->kategoriyeGore($q, $kategori))
                ->limit($limit)
                ->get();
        }

        if ($sonuc->count() < $limit && $urun->brand !== null && $urun->brand !== '') {
            $ek = $this->temel($urun, $sonuc)
                ->where('brand', $urun->brand)
                ->limit($limit - $sonuc->count())
                ->get();

            /** @var Collection<int, Product> $sonuc */
            $sonuc = new Collection([...$sonuc->all(), ...$ek->all()]);
        }

        if ($sonuc->count() < $limit) {
            /*
            | ⚠️ Son kademe "en yeniler" — RASTGELE değil. Rastgele
            | olsaydı aynı sayfa her yenilendiğinde başka ürünler
            | gösterir, müşteri az önce gördüğü ürünü bir daha
            | bulamazdı.
            */
            $ek = $this->temel($urun, $sonuc)
                ->orderByDesc('id')
                ->limit($limit - $sonuc->count())
                ->get();

            /** @var Collection<int, Product> $sonuc */
            $sonuc = new Collection([...$sonuc->all(), ...$ek->all()]);
        }

        return $sonuc;
    }

    /**
     * Çok satanlar.
     *
     * ⚠️ "BEĞENİLENLER" DEĞİL ve başlığı da öyle DEĞİL. Beğeni sayacı
     * için gereken olaylar 4.6F'de yazılacak; o blok bitmeden "beğeni"
     * demek uydurma bir sayı sunmak olurdu. Elimizdeki gerçek sinyal
     * SATIŞ.
     *
     * @return Collection<int, Product>
     */
    public function cokSatanlar(int $limit = self::LIMIT, ?Product $haric = null): Collection
    {
        /*
        | ⚠️ SATIŞ SAYIMI ÖDENMİŞ SİPARİŞTEN. `pending` sayılsaydı ödemesi
        | hiç tamamlanmayan sepetler "çok satan" üretirdi — ve o listeyi
        | üretmenin yolu ödeme sayfasına kadar gidip vazgeçmek olurdu.
        |
        | ⚠️ `partially_refunded` SAYILIYOR: siparişin bir kısmı iade
        | edilmiş olsa da bir kısmı satılmış. `refunded` (tamamı iade)
        | sayılmıyor — o satış geri alınmış demektir.
        |
        | ⚠️ Bağ `variant_id` üzerinden ve `order_items`'ta `product_id`
        | YOK (sipariş satırı kendi kopyasını taşıyor, 1D). Varyantı
        | gerçekten silinmiş satırlarda bağ `null` — o satışlar sayıma
        | girmiyor. Yumuşak silinmişte satır duruyor, o yüzden pratikte
        | kayıp yok.
        */
        $satislar = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->whereIn('orders.payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
            ])
            ->groupBy('product_variants.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit($limit * 2)
            ->pluck('product_variants.product_id')
            ->all();

        if ($satislar === []) {
            return new Collection;
        }

        /*
        | ⚠️ Sıra SORGUDAN GELMİYOR, burada korunuyor: `whereIn` kendi
        | sırasını uyguluyor ve "çok satan" listesi satış sırasına göre
        | olmazsa adı yalan olur.
        |
        | ⚠️ `limit * 2` alınmasının sebebi bu adım: satılan ürünlerin
        | bir kısmı artık vitrinde olmayabilir (taslak, arşiv, silinmiş).
        */
        $urunler = $this->urunler->forStorefront()
            ->whereIn('id', $satislar)
            ->when($haric !== null, fn ($q) => $q->whereKeyNot($haric?->getKey()))
            ->get()
            ->sortBy(fn (Product $u) => array_search($u->id, $satislar, strict: true))
            ->take($limit)
            ->values();

        /** @var Collection<int, Product> $sonuc */
        $sonuc = new Collection($urunler->all());

        return $sonuc;
    }

    /**
     * Ortak taban: vitrinde görünen, KENDİSİ ve ZATEN SEÇİLENLER hariç.
     *
     * @param  Collection<int, Product>  $secilenler
     * @return Builder<Product>
     */
    private function temel(Product $urun, Collection $secilenler): Builder
    {
        /*
        | ⚠️ `forStorefront()` ŞART: kendi sorgusunu yazsaydı taslak ve
        | arşiv ürünler "benzer ürün" olarak sızardı — 4.5H'de koleksiyon
        | için ölçülen kusurun aynısı. Görsel ve varyantlar da orada eager
        | load ediliyor, yani kart başına sorgu açılmıyor (N+1).
        |
        | ⚠️ Zaten seçilenler dışlanıyor: kademeler birbirini tamamladığı
        | için aynı ürün iki kez listelenebilirdi.
        */
        return $this->urunler->forStorefront()
            ->whereKeyNot($urun->getKey())
            ->when($secilenler->isNotEmpty(), fn ($q) => $q->whereKeyNot($secilenler->modelKeys()));
    }
}
