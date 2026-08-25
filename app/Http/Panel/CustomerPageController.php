<?php

namespace App\Http\Panel;

use App\Domain\Identity\CustomerInsight;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panelde MÜŞTERİ sekmesi. (4.6AC)
 *
 * ★ `customer.view` izni Faz 1'den beri tanımlıydı ve üç role
 * verilmişti, ama HİÇBİR ROTA onu kullanmıyordu — izin ölüydü. 4.6S'de
 * `product.view` için ölçülen kusurun aynısı.
 *
 * ⚠️ SALT OKUNUR: bu sekmede yazma ucu YOK. Müşteri verisini panelden
 * değiştirmek KVKK tarafında ayrı bir sorumluluk (anonimleştirme 2G'de,
 * kendi akışıyla) ve buraya sızmamalı.
 */
class CustomerPageController extends Controller
{
    public function __construct(private readonly CustomerInsight $ozet) {}

    public function index(Request $istek): Response
    {
        $arama = trim((string) $istek->query('ara', ''));

        $musteriler = $this->ozet->liste($arama);

        return Inertia::render('Musteriler/Liste', [
            'musteriler' => $musteriler->through(fn (Customer $m): array => [
                'uuid' => $m->uuid,
                'ad' => $m->name,
                'eposta' => $m->email,
                'dogrulanmis' => $m->email_verified_at !== null,
                'kayit' => $m->created_at?->toIso8601String(),

                /*
                | ⚠️ `?? 0` ŞART: hiç siparişi olmayan müşteride
                | `withSum` NULL döndürüyor ve ekranda boş görünürdü.
                | Kolon varsayılanı modele ulaşmıyor tuzağının sorgu hâli.
                */
                'siparis' => (int) ($m->siparis_sayisi ?? 0),
                'harcama' => (string) ($m->toplam_harcama ?? '0'),
            ]),
            'ara' => $arama === '' ? null : $arama,
        ]);
    }

    public function show(Customer $musteri): Response
    {
        return Inertia::render('Musteriler/Ayrinti', [
            'musteri' => [
                'uuid' => $musteri->uuid,
                'ad' => $musteri->name,
                'eposta' => $musteri->email,
                'telefon' => $musteri->phone,
                'dogrulanmis' => $musteri->email_verified_at !== null,
                'pazarlama' => (bool) $musteri->accepts_marketing,
                'kayit' => $musteri->created_at?->toIso8601String(),
            ],

            'ozet' => $this->ozet->ozet($musteri),

            'siparisler' => $this->ozet->siparisler($musteri)
                ->map(fn (Order $s): array => [
                    'uuid' => $s->uuid,
                    'numara' => $s->order_number,
                    'tarih' => $s->placed_at?->toIso8601String(),
                    'tutar' => (string) $s->grand_total,
                    'odeme' => $s->payment_status->value,
                    'kargo' => $s->fulfillment_status->value,
                    'adet' => $s->items->sum('quantity'),

                    // ⚠️ Ürün adları SİPARİŞ SATIRINDAN (kopya): ürün
                    // silinse bile müşterinin ne aldığı görünüyor (1D).
                    'urunler' => $s->items->pluck('product_title')->all(),
                ])->all(),

            'favoriler' => $this->ozet->favoriler($musteri)
                ->map(fn (Favorite $f): array => [
                    /*
                    | ⚠️ Silinmiş ürün de gösteriliyor (vitrindeki listenin
                    | TERSİ, 4.6D): panelde soru "müşteriye ne gösterelim"
                    | değil "bu müşteri hakkında ne biliyoruz".
                    */
                    /*
                    | ⚠️ `?->` DEĞİL `->`: ilişki `with('product')` ile
                    | yüklendiği ve model `SoftDeletes` kullandığı için
                    | statik analiz `null` gelmeyeceğini biliyor. Yine de
                    | silinmiş ürün metni kalıyor — çalışma anında ilişki
                    | çözülemezse Blade'de boş satır çıkardı.
                    */
                    'urun' => $f->product->title ?? '[silinmiş ürün]',
                    'slug' => $f->product?->slug,
                    'tarih' => $f->created_at?->toIso8601String(),
                ])->all(),

            'basarisizOdemeler' => $this->ozet->basarisizOdemeler($musteri)
                ->map(fn (Payment $o): array => [
                    'numara' => $o->order?->order_number,
                    'tutar' => (string) $o->amount,
                    'saglayici' => $o->provider,
                    'tarih' => $o->created_at?->toIso8601String(),

                    /*
                    | ⚠️ RET GEREKÇESİ YOK. Banka "limit yetersiz" ya da
                    | "fraud şüphesi" diyebiliyor; bu müşterinin KARTINA
                    | dair bir bilgi ve markanın personeline açılması
                    | gerekmiyor — vitrinde de aynı sebeple gizleniyor.
                    */
                ])->all(),
        ]);
    }
}
