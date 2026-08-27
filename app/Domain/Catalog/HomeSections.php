<?php

namespace App\Domain\Catalog;

use App\Enums\EventType;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ana sayfa bölümleri: sana özel · popüler · çok satanlar · yeni gelenler.
 *
 * ★ NEDEN BÖLÜM: ana sayfa bugüne kadar tek düz liste (24 ürün) idi.
 * Müşteri neye baktığını bilmiyordu — bu ürünler neden burada, hangisi
 * yeni, hangisi tutuyor?
 *
 * ★ HER BÖLÜMÜN EŞİĞİ VAR VE BU BLOĞUN EN ÖNEMLİ KARARI.
 *
 * ⚠️ Ölçüldü: geliştirme markasında **20 görüntüleme olayı** ve
 * **1 müşteriye bağlı** görüntüleme vardı; ürünlerin **tamamı** son 30
 * günde eklenmişti. Eşiksiz kurulsaydı "en çok tıklanan" bölümü tek bir
 * tıklamayla popüler ürün ilan eder, "yeni gelenler" ise KATALOGUN
 * TAMAMINI gösterirdi. İkisi de müşteriye yanlış bilgi verir.
 *
 * 4.6F'nin dersi: *hesabı doğru ama sonucu saçma olan sayıyı gösterme.*
 * Burada karşılığı: **verisi olmayan bölüm hiç çizilmez.**
 *
 * ⚠️ BÖLÜMLER ARASI TEKRAR ENGELLENMİYOR — bilerek. Bir ürün hem popüler
 * hem çok satan olabilir; "çok satanlar"dan gerçek en çok satanı, başka
 * bölümde geçtiği için çıkarmak başlığı YALAN yapardı.
 */
class HomeSections
{
    /** Bir bölümün çizilmesi için gereken en az ürün. */
    public const EN_AZ_URUN = 4;

    /** Bölüm başına gösterilen ürün. */
    public const LIMIT = 8;

    /** "Yeni gelenler" penceresi. */
    public const YENI_PENCERE_GUN = 30;

    /**
     * "Popüler" bölümünün açılması için gereken en az toplam görüntüleme.
     *
     * ⚠️ Bu sayı keyfî değil eşik: altında kalan veri POPÜLERLİK
     * ÖLÇMÜYOR, gürültü ölçüyor. Tek tıklamayla "popüler" ilan etmek
     * müşteriyi de markayı da yanıltır.
     */
    public const EN_AZ_GORUNTULEME = 50;

    /** Kişisel öneri için müşterinin en az bu kadar etkileşimi olmalı. */
    public const EN_AZ_ETKILESIM = 3;

    /** Önbellek ömrü — ana sayfa en çok vurulan sayfa. */
    public const ONBELLEK_SANIYE = 300;

    public function __construct(
        private readonly ProductQuery $urunler,
        private readonly SimilarProductQuery $oneriler,
    ) {}

    /**
     * Çizilecek bölümler, sırayla. Boş bölüm listeye HİÇ girmiyor.
     *
     * @return list<array{anahtar: string, baslik: string, urunler: Collection<int, Product>}>
     */
    public function bolumler(?Customer $musteri): array
    {
        $bolumler = [];

        foreach ([
            ['sana-ozel', 'Sizin için seçtiklerimiz', $this->sanaOzel($musteri)],
            ['populer', 'Şu sıralar popüler', $this->populer()],
            ['cok-satan', 'Çok satanlar', $this->cokSatanlar()],
            ['yeni', 'Yeni gelenler', $this->yeniGelenler()],
        ] as [$anahtar, $baslik, $urunler]) {
            if ($urunler->count() >= self::EN_AZ_URUN) {
                $bolumler[] = ['anahtar' => $anahtar, 'baslik' => $baslik, 'urunler' => $urunler];
            }
        }

        return $bolumler;
    }

    /**
     * Müşterinin baktığı/aldığı kategorilerden, ALMADIKLARI.
     *
     * @return Collection<int, Product>
     */
    public function sanaOzel(?Customer $musteri): Collection
    {
        if (! $musteri instanceof Customer) {
            return collect();
        }

        /*
        | ⚠️ ÖNBELLEĞE KONMUYOR. Bölümün tamamı müşteriye özel; ortak
        | önbelleğe konsaydı bir müşterinin önerileri BAŞKASINA
        | gösterilirdi — çok kiracılıkta değil, aynı marka içinde
        | müşteriler arası sızma.
        */
        $gorulenUrunler = DB::table('events')
            ->where('customer_id', $musteri->id)
            ->where('type', EventType::ProductViewed->value)
            ->whereRaw("payload->>'product_id' IS NOT NULL")
            ->selectRaw("(payload->>'product_id')::bigint AS product_id")
            ->pluck('product_id');

        $alinanUrunler = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->where('orders.customer_id', $musteri->id)
            ->pluck('product_variants.product_id');

        $etkilesim = $gorulenUrunler->merge($alinanUrunler)->unique();

        /*
        | ⚠️ AZ VERİYLE ÖNERİ YAPILMIYOR. Tek bir ürüne bakmış müşteriye
        | "sizin için seçtiklerimiz" demek, seçim yapılmadığını gizler.
        */
        if ($etkilesim->count() < self::EN_AZ_ETKILESIM) {
            return collect();
        }

        $kategoriler = DB::table('products')
            ->whereIn('id', $etkilesim->all())
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->unique();

        if ($kategoriler->isEmpty()) {
            return collect();
        }

        return $this->urunler->forStorefront()
            ->whereIn('category_id', $kategoriler->all())

            /*
            | ⚠️ ALDIĞI ÜRÜN ÖNERİLMİYOR. "Sizin için" başlığı altında
            | dün satın aldığı şeyi görmek öneriyi değersizleştirir.
            | Gördüğü ama almadığı ÖNERİLİYOR — ilgi göstermiş demektir.
            */
            ->whereNotIn('id', $alinanUrunler->all())
            ->with(['images', 'variants'])
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * En çok görüntülenen ürünler — YALNIZCA yeterli veri varsa.
     *
     * @return Collection<int, Product>
     */
    public function populer(): Collection
    {
        return $this->onbellekli('populer', function (): Collection {
            $pencere = now()->subDays(self::YENI_PENCERE_GUN);

            $toplam = DB::table('events')
                ->where('type', EventType::ProductViewed->value)
                ->where('occurred_at', '>=', $pencere)
                ->count();

            /*
            | ★ EŞİK. Altında kalan veri popülerlik değil GÜRÜLTÜ ölçüyor.
            | Ölçüldü: geliştirme markasında toplam 20 görüntüleme vardı;
            | eşiksiz bir liste tek tıklamayı "popüler" ilan ederdi.
            */
            if ($toplam < self::EN_AZ_GORUNTULEME) {
                return collect();
            }

            $idler = DB::table('events')
                ->selectRaw("(payload->>'product_id')::bigint AS product_id, count(*) AS adet")
                ->where('type', EventType::ProductViewed->value)
                ->where('occurred_at', '>=', $pencere)
                ->whereRaw("payload->>'product_id' IS NOT NULL")
                ->groupBy(DB::raw("(payload->>'product_id')::bigint"))
                ->orderByDesc('adet')
                ->limit(self::LIMIT)
                ->pluck('product_id');

            return $this->sirayaGore(array_map('intval', array_values($idler->all())));
        });
    }

    /** @return Collection<int, Product> */
    public function cokSatanlar(): Collection
    {
        // ⚠️ Mevcut sorgu yeniden kullanılıyor: satış `order_items`'tan
        //    sayılıyor, olaylardan DEĞİL (4.6F'nin kararı).
        return $this->onbellekli('cok-satan', fn (): Collection => $this->oneriler->cokSatanlar(self::LIMIT));
    }

    /**
     * Son eklenenler — ama YALNIZCA gerçek bir alt kümeyse.
     *
     * @return Collection<int, Product>
     */
    public function yeniGelenler(): Collection
    {
        return $this->onbellekli('yeni', function (): Collection {
            $pencere = now()->subDays(self::YENI_PENCERE_GUN);

            /*
            | ★ EN İNCE KARAR. Katalogun TAMAMI penceredeyse "yeni
            | gelenler" bölümü katalogun kendisidir ve müşteriye hiçbir
            | şey söylemez — üstelik iki kez aynı ürünleri görür.
            |
            | Ölçüldü: geliştirme markasında 23 ürünün 23'ü son 30 günde
            | eklenmişti. Bölüm ancak penceren DIŞINDA da ürün varsa
            | anlamlı.
            */
            $eskiVar = $this->urunler->forStorefront()
                ->where('created_at', '<', $pencere)
                ->exists();

            if (! $eskiVar) {
                return collect();
            }

            return $this->urunler->forStorefront()
                ->where('created_at', '>=', $pencere)
                ->latest('created_at')
                ->with(['images', 'variants'])
                ->limit(self::LIMIT)
                ->get();
        });
    }

    /**
     * Verilen sırayı KORUYARAK ürünleri yükler.
     *
     * ⚠️ `whereIn` sırayı korumuyor; korunmazsa "en çok görüntülenen"
     * listesi rastgele sırada çıkar ve başlık yalan söyler.
     *
     * @param  list<int>  $idler
     * @return Collection<int, Product>
     */
    private function sirayaGore(array $idler): Collection
    {
        if ($idler === []) {
            return collect();
        }

        $urunler = $this->urunler->forStorefront()
            ->whereIn('id', $idler)
            ->with(['images', 'variants'])
            ->get()
            ->keyBy('id');

        return collect($idler)
            ->map(fn (int $id): ?Product => $urunler->get($id))
            ->filter()
            ->values();
    }

    /**
     * Kişisel OLMAYAN bölümler için önbellek.
     *
     * ⚠️ Yalnızca KİMLİKLER saklanıyor, model değil: önbelleğe konan
     * Eloquent koleksiyonu hem şişiyor hem bayat ilişki taşıyor.
     *
     * ⚠️ Anahtar kiracıya göre öneklenmiyor — `CacheTenancyBootstrapper`
     * bunu kendisi yapıyor (`config/tenancy.php`). Elle önek eklemek
     * çift önek üretirdi.
     *
     * @param  callable(): Collection<int, Product>  $uret
     * @return Collection<int, Product>
     */
    private function onbellekli(string $anahtar, callable $uret): Collection
    {
        $idler = Cache::remember(
            'anasayfa-bolum-'.$anahtar,
            self::ONBELLEK_SANIYE,
            fn (): array => $uret()->pluck('id')->all(),
        );

        return $this->sirayaGore(array_map('intval', array_values($idler)));
    }
}
