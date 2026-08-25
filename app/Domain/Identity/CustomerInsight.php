<?php

namespace App\Domain\Identity;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Markanın müşterisi hakkında panelde gösterilen özet. (4.6AC)
 *
 * ★ `customer.view` izni Faz 1'den beri TANIMLIYDI ve üç role verilmişti,
 * ama HİÇBİR ROTA onu kullanmıyordu — yani izin ölüydü. 4.6S'de
 * `product.view` için ölçülen kusurun aynısı.
 *
 * ⚠️ HASSAS KOLON HİÇ SORGUYA GİRMİYOR. `password` ve
 * `remember_token` seçilmiyor bile — modelin `$hidden` listesine
 * güvenmek yetmez: `$hidden` yalnızca diziye/JSON'a çevirirken çalışıyor,
 * `toArray()` dışında bir yol (log, dd, ilişki serileştirme) onu
 * atlayabilir. 4F'de marka dökümüne bcrypt hash'lerinin girmesi tam
 * böyle olmuştu.
 */
class CustomerInsight
{
    /** Bir müşterinin listelenen son siparişi sayısı. */
    public const SON_SIPARIS = 20;

    /**
     * Panelde gösterilecek müşteri kolonları.
     *
     * ⚠️ LİSTE DAR TUTULUYOR ve genişletilirken düşünülmeli: burada
     * olmayan kolon panele HİÇ ulaşmıyor.
     *
     * @var list<string>
     */
    public const KOLONLAR = [
        'id', 'uuid', 'name', 'email', 'phone',
        'accepts_marketing', 'email_verified_at', 'created_at',
    ];

    /**
     * Müşteri listesi — sipariş sayısı ve toplam harcamayla.
     *
     * ⚠️ Sayımlar TEK SORGUDA: müşteri başına ayrı sorgu açılsaydı 200
     * müşterilik listede 400 ek sorgu olurdu (N+1).
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, Customer>
     */
    public function liste(string $arama = '', int $sayfa = 25): \Illuminate\Pagination\LengthAwarePaginator
    {
        $sorgu = Customer::query()
            ->select(self::KOLONLAR)

            /*
            | ⚠️ Toplam harcama ÖDENMİŞ siparişten. `pending` sayılsaydı
            | ödemesi hiç tamamlanmayan sepetler müşteriyi "iyi müşteri"
            | gibi gösterirdi.
            |
            | ⚠️ `partially_refunded` sayılıyor ama tutar SİPARİŞİN TAMAMI
            | — iade edilen kısım düşülmüyor. Bu bir SINIR ve ekranda da
            | öyle yazıyor: gerçek net ciro için iade kayıtlarının
            | siparişle eşleştirilmesi gerekiyor, o ayrı bir iş.
            */
            ->withCount(['orders as siparis_sayisi' => fn ($q) => $q->whereIn('payment_status', self::SATIS_DURUMLARI)])
            ->withSum(
                ['orders as toplam_harcama' => fn ($q) => $q->whereIn('payment_status', self::SATIS_DURUMLARI)],
                'grand_total'
            );

        if ($arama !== '') {
            /*
            | ⚠️ Arama SOL EŞLEŞME (`ara%`), kelime ortasından değil —
            | 4.5P ve 4.5S'de aynı karar verilmişti: "iş" araması
            | "Tişört"ü getirmemeli.
            |
            | ⚠️ `citext` marka şemasında ÇALIŞMIYOR (eklenti `public`'te),
            | o yüzden karşılaştırma `lower()` üzerinden.
            */
            $kucuk = mb_strtolower($arama);

            $sorgu->where(function ($q) use ($kucuk): void {
                $q->whereRaw('lower(name) LIKE ?', [$kucuk.'%'])
                    ->orWhereRaw('lower(email) LIKE ?', [$kucuk.'%']);
            });
        }

        return $sorgu->orderByDesc('id')->paginate($sayfa)->withQueryString();
    }

    /** Satış sayılan ödeme durumları. */
    private const SATIS_DURUMLARI = [
        'paid', 'partially_refunded',
    ];

    /**
     * Müşterinin son siparişleri.
     *
     * @return Collection<int, Order>
     */
    public function siparisler(Customer $musteri): Collection
    {
        return Order::query()
            ->where('customer_id', $musteri->id)
            ->with('items')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(self::SON_SIPARIS)
            ->get();
    }

    /**
     * Müşterinin favorileri.
     *
     * ⚠️ Silinmiş ürünün favorisi de GÖSTERİLİYOR (vitrindeki listenin
     * TERSİ, 4.6D): panelde soru "müşteriye ne gösterelim" değil, "bu
     * müşteri hakkında ne biliyoruz". Gizlemek markayı yanıltırdı.
     *
     * @return Collection<int, Favorite>
     */
    public function favoriler(Customer $musteri): Collection
    {
        return Favorite::query()
            ->where('customer_id', $musteri->id)
            ->with('product')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Başarısız ödeme denemeleri.
     *
     * ★ Kullanıcı isteği: "başarısız ödeme denemeleri". Destek ekibi
     * "kartım geçmiyor" diyen müşteriye ancak bunu görerek yardım
     * edebiliyor.
     *
     * ⚠️ SAĞLAYICININ RET GEREKÇESİ GÖSTERİLMİYOR. Banka "limit yetersiz"
     * ya da "fraud şüphesi" diyebiliyor; bu müşterinin KARTINA dair bir
     * bilgi ve markanın personeline açılması gerekmiyor — vitrinde de
     * aynı sebeple gizleniyor (4.5R). Panelde görünen şey DENEMENİN
     * VARLIĞI ve zamanı.
     *
     * @return Collection<int, Payment>
     */
    public function basarisizOdemeler(Customer $musteri): Collection
    {
        return Payment::query()
            ->whereIn('order_id', Order::query()->where('customer_id', $musteri->id)->select('id'))
            ->where('status', 'failed')
            ->with('order')
            ->orderByDesc('created_at')
            ->limit(self::SON_SIPARIS)
            ->get();
    }

    /**
     * Özet sayılar.
     *
     * @return array{siparis: int, harcama: string, favori: int, basarisiz: int}
     */
    public function ozet(Customer $musteri): array
    {
        $satislar = Order::query()
            ->where('customer_id', $musteri->id)
            ->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value]);

        return [
            'siparis' => (clone $satislar)->count(),

            // ⚠️ Para METİN olarak taşınıyor: float'ta kuruş kayboluyor.
            'harcama' => (string) ((clone $satislar)->sum(DB::raw('grand_total')) ?: '0'),
            'favori' => Favorite::where('customer_id', $musteri->id)->count(),
            'basarisiz' => $this->basarisizOdemeler($musteri)->count(),
        ];
    }
}
