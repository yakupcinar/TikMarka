<?php

namespace App\Domain\Analytics;

use App\Enums\EventType;
use App\Enums\PaymentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ürün başına huni: görüntüleme → sepete ekleme → satış. (4.6F)
 *
 * ★ ÜÇ ÖLÇÜ, İKİ FARKLI KAYNAK — ve bu bilinçli bir seçim.
 *
 * Görüntüleme ve sepete ekleme YALNIZCA olaylarda var; başka kaydı yok.
 * Satış ise `order_items`'ta var ve asıl kaynak ORASI.
 *
 * ⚠️ SATIŞI OLAYLARDAN SAYMAK YANLIŞ OLURDU. Olay kaydı bilerek
 * "işi bozmayan" bir yol: kuyruğa atılamazsa istisna yutuluyor (1F-K3).
 * Yani bir olayın YOKLUĞU, o şeyin olmadığı anlamına gelmiyor. Ciro ve
 * satış adedi bu belirsizliği kaldıramaz — para `order_items`'tan
 * sayılıyor, orada kayıp olamaz.
 *
 * ⚠️ Tersi de doğru: görüntülemeyi `order_items`'tan sayamazsın, orada
 * yok. Her ölçüyü GÜVENİLİR OLDUĞU yerden almak, tek kaynağa zorlamaktan
 * daha doğru.
 *
 * ⚠️ Bu sınıf kiracıdan habersiz (M-2.7): `search_path` zaten kurulu.
 */
class ProductFunnelQuery
{
    /**
     * Satırlar: product_id · baslik · slug · goruntuleme · sepete_ekleme
     * · satis_adedi · ciro.
     *
     * @return Collection<int, \stdClass>
     */
    public function huni(int $gunSayisi = 30, int $limit = 50): Collection
    {
        $baslangic = now()->subDays($gunSayisi);

        /*
        | ⚠️ GÖRÜNTÜLEME `payload->>'product_id'` ÜZERİNDEN. Metin
        | dönüyor, karşılaştırmadan önce sayıya çevriliyor — çevrilmezse
        | PostgreSQL `text = bigint` diye patlar.
        */
        $goruntulemeler = DB::table('events')
            ->selectRaw("(payload->>'product_id')::bigint AS product_id, count(*) AS adet")
            ->where('type', EventType::ProductViewed->value)
            ->where('occurred_at', '>=', $baslangic)
            ->whereRaw("payload->>'product_id' IS NOT NULL")
            ->groupBy(DB::raw("(payload->>'product_id')::bigint"));

        /*
        | ⚠️ SEPETE EKLEME payload'da ÜRÜN kimliği TAŞIMIYOR — yalnızca
        | `variant_id` var. Ürüne ulaşmak için varyant üzerinden geçmek
        | zorunlu. Bu, olay payload'ını sonradan değiştirmemenin bedeli:
        | eski kayıtlar yeni alanı taşımaz, bu yüzden birleştirme
        | payload'a değil TABLOYA dayandırılıyor.
        */
        $sepetlemeler = DB::table('events')
            ->join('product_variants', 'product_variants.id', '=', DB::raw("(events.payload->>'variant_id')::bigint"))
            ->selectRaw('product_variants.product_id AS product_id, count(*) AS adet')
            ->where('events.type', EventType::CartItemAdded->value)
            ->where('events.occurred_at', '>=', $baslangic)
            ->whereRaw("events.payload->>'variant_id' IS NOT NULL")
            ->groupBy('product_variants.product_id');

        /*
        | ⚠️ YALNIZCA ÖDENMİŞ sipariş sayılıyor. `pending` de sayılsaydı
        | terk edilen ödemeler satış görünür ve marka olmayan bir talebe
        | göre stok planlardı. (4.6AC'de aynı ayrım yapılmıştı.)
        */
        $satislar = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->selectRaw('product_variants.product_id AS product_id, sum(order_items.quantity) AS adet, sum(order_items.line_total) AS ciro')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.placed_at', '>=', $baslangic)
            ->groupBy('product_variants.product_id');

        /*
        | ⚠️ ÜRÜNDEN BAŞLANIYOR, olaydan değil. Olaydan başlansaydı hiç
        | bakılmamış ama satılmış bir ürün listeye HİÇ girmezdi — oysa
        | markanın görmesi gereken tam olarak o satır.
        |
        | ⚠️ `leftJoinSub` — üç ölçünün üçü de boş olabilir.
        */
        return collect(DB::table('products')
            ->leftJoinSub($goruntulemeler, 'g', 'g.product_id', '=', 'products.id')
            ->leftJoinSub($sepetlemeler, 's', 's.product_id', '=', 'products.id')
            ->leftJoinSub($satislar, 'st', 'st.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->selectRaw('products.id AS product_id, products.title AS baslik, products.slug AS slug')
            ->selectRaw('coalesce(g.adet, 0) AS goruntuleme')
            ->selectRaw('coalesce(s.adet, 0) AS sepete_ekleme')
            ->selectRaw('coalesce(st.adet, 0) AS satis_adedi')
            ->selectRaw('coalesce(st.ciro, 0) AS ciro')
            ->orderByRaw('coalesce(g.adet, 0) DESC, coalesce(st.adet, 0) DESC')
            ->limit($limit)
            ->get());
    }
}
