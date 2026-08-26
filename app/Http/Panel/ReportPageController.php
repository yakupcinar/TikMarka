<?php

namespace App\Http\Panel;

use App\Domain\Analytics\ProductFunnelQuery;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panelde ÜRÜN RAPORU — görüntüleme → sepet → satış. (4.6F)
 *
 * ★ Olaylar 1F'den beri yazılıyordu ama markanın onları GÖRECEĞİ hiçbir
 * yer yoktu. Ölçüm var, ekran yok — `customer.view` izninin 4.6AC'ye
 * kadar ölü kalmasıyla aynı aile.
 *
 * ⚠️ SALT OKUNUR: rapor ekranında yazma ucu yok.
 */
class ReportPageController extends Controller
{
    /** Panelde seçilebilen dönemler — beyaz liste. */
    private const DONEMLER = [7, 30, 90, 365];

    public function __construct(private readonly ProductFunnelQuery $huni) {}

    public function __invoke(Request $istek): Response
    {
        /*
        | ⚠️ DÖNEM BEYAZ LİSTEDEN geçiyor. Doğrudan `(int)` alınsaydı
        | adrese `?gun=100000` yazan biri bütün olay tablosunu tarayan bir
        | sorgu açtırırdı — kimlik doğrulanmış olsa bile bu bir yük kapısı.
        */
        $gun = (int) $istek->query('gun', '30');

        if (! in_array($gun, self::DONEMLER, true)) {
            $gun = 30;
        }

        /*
        | ★ CİRO SÜTUNU AYRICA KISITLI (4F dersi).
        |
        | ⚠️ "Tablo listesini daraltmak yetmez, KOLON da temizlenir."
        | Ekranın kendisi katalog VEYA sipariş iznine açık — huni bilgisi
        | her ikisinin de işi. Ama ciro finansal veri: `finance.view`
        | olmayan personel sayıyı GÖRMEMELİ.
        |
        | ⚠️ Alan `null` gönderiliyor, sıfır DEĞİL: sıfır "bu üründen
        | hiç kazanılmadı" demek olurdu ve personel yanlış bilgilenirdi.
        | `null` "sana gösterilmiyor" demek ve ekran öyle yazıyor.
        */
        $ciroyuGorebilir = $istek->user('staff-web')?->hasPermission(Permission::FinanceView) ?? false;

        return Inertia::render('Rapor', [
            'gun' => $gun,
            'donemler' => self::DONEMLER,
            'ciroGorunur' => $ciroyuGorebilir,

            'satirlar' => $this->huni->huni($gun)->map(fn (object $r): array => [
                'baslik' => $r->baslik,
                'slug' => $r->slug,
                'goruntuleme' => (int) $r->goruntuleme,
                'sepeteEkleme' => (int) $r->sepete_ekleme,
                'satisAdedi' => (int) $r->satis_adedi,
                'ciro' => $ciroyuGorebilir ? (string) $r->ciro : null,
            ])->all(),
        ]);
    }
}
