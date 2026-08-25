<?php

namespace App\Domain\Favorite;

use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

/**
 * Müşteri favorileri. (4.6D)
 *
 * ⚠️ Kiracıdan habersiz (M-2.7): "hangi markadayım" diye sormuyor,
 * `search_path` zaten kapsamı belirlemiş oluyor.
 */
class FavoriteService
{
    /**
     * Favoriye ekler ya da çıkarır — sonuçta favoride mi, onu döndürür.
     *
     * ★ TEK UÇ, İKİ YÖN. Ayrı "ekle" ve "çıkar" uçları olsaydı ekran hangi
     * uca gideceğini bilmek için önce durumu okumak zorunda kalırdı ve iki
     * istek arasında durum değişebilirdi (iki sekme).
     *
     * @return bool işlemden SONRA favoride mi
     */
    public function degistir(Customer $musteri, Product $urun): bool
    {
        $mevcut = Favorite::where('customer_id', $musteri->id)
            ->where('product_id', $urun->id)
            ->first();

        if ($mevcut !== null) {
            $mevcut->delete();

            return false;
        }

        try {
            $favori = new Favorite;
            $favori->customer()->associate($musteri);
            $favori->product()->associate($urun);
            $favori->save();
        } catch (QueryException $hata) {
            /*
            | ⚠️ YARIŞ DURUMU: müşteri iki sekmeden aynı anda basarsa iki
            | istek de "yok" görüp eklemeye çalışır ve benzersiz kısıt
            | patlar. Kısıt SON SAVUNMA olarak kalıyor (4.6X'in dersi) ama
            | müşteriye 500 göstermek yanlış olurdu: sonuç zaten istediği
            | şey — ürün favoride.
            |
            | ⚠️ Yalnızca BENZERSİZLİK ihlali yutuluyor; başka bir
            | veritabanı hatası (bağlantı, kısıt) yukarı fırlatılıyor.
            */
            if (! str_contains($hata->getMessage(), 'favorites_customer_id_product_id_unique')) {
                throw $hata;
            }
        }

        return true;
    }

    /** Ürün bu müşterinin favorisinde mi? */
    public function favorideMi(Customer $musteri, Product $urun): bool
    {
        return Favorite::where('customer_id', $musteri->id)
            ->where('product_id', $urun->id)
            ->exists();
    }

    /**
     * Müşterinin favorileri — YENİDEN ESKİYE.
     *
     * ⚠️ `whereHas('product')` ŞART: ürün yumuşak silinmişse favori satırı
     * duruyor ama ürün katalogda yok. Eklenmeseydi liste, tıklanınca 404
     * veren ölü kartlar gösterirdi. Bu, "AÇAN yol silinmişi görmemeli"
     * kuralının liste hâli.
     *
     * @return Collection<int, Favorite>
     */
    public function listele(Customer $musteri): Collection
    {
        return Favorite::where('customer_id', $musteri->id)
            ->whereHas('product')
            ->with(['product.images'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Müşterinin TÜM favorilerini siler.
     *
     * ⚠️ Anonimleştirmede (2G) çağrılıyor. Favori "bu kişi neyi beğendi"
     * bilgisidir; anonimleştirilecek bir alanı YOK, bağın kendisi kişisel
     * veri. Bu yüzden maskelenmiyor, SİLİNİYOR.
     */
    public function hepsiniSil(Customer $musteri): void
    {
        Favorite::where('customer_id', $musteri->id)->delete();
    }
}
