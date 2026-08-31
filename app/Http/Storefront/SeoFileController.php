<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\ProductQuery;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

/**
 * `sitemap.xml` ve `robots.txt` — MARKA BAŞINA. (B3)
 *
 * ★ 4.6G'de ölçüldü: `sitemap.xml` 404 dönüyordu ve `robots.txt`
 * Laravel'in varsayılanıydı (`Disallow:` boş — her şeye izin).
 *
 * ⚠️ İKİSİ DE KİRACIYA ÖZEL olmak zorunda: sitemap markanın kendi alan
 * adında ve YALNIZCA o markanın ürünlerini içermeli. Statik bir dosya
 * bunu yapamaz — bu yüzden `public/robots.txt` kaldırıldı ve yerine
 * rota kondu.
 */
class SeoFileController extends Controller
{
    /**
     * Sitemap'e giren en fazla ürün.
     *
     * ⚠️ Sitemap protokolünün sınırı 50.000 URL / 50 MB. Bu sayının
     * altında kalındığı sürece tek dosya yetiyor; aşılırsa sitemap
     * indeksine bölmek gerekir.
     */
    public const MAKS_URL = 40_000;

    public function __construct(
        private readonly ProductQuery $urunler,
        private readonly CategoryService $kategoriler,
    ) {}

    public function sitemap(): Response
    {
        $adresler = [];

        $ekle = function (string $yol, ?string $tarih = null, string $siklik = 'weekly') use (&$adresler): void {
            $adresler[] = ['loc' => url($yol), 'lastmod' => $tarih, 'changefreq' => $siklik];
        };

        $ekle('/', null, 'daily');
        $ekle('/kategoriler');
        $ekle('/koleksiyonlar');

        /*
        | ⚠️ VİTRİN SORGUSU kullanılıyor, panel sorgusu değil: taslak,
        | arşiv ve satılamayan ürün sitemap'e girmemeli. Girseydi motor
        | 404 alacağı adresleri tarardı (1B-K10).
        */
        $this->urunler->forStorefront()
            ->select(['slug', 'updated_at'])
            ->limit(self::MAKS_URL)
            ->get()
            ->each(function (Product $urun) use ($ekle): void {
                $ekle('/urun/'.$urun->slug, $urun->updated_at?->toAtomString());
            });

        $this->kategoriler->listele()->each(function (Category $kategori) use ($ekle): void {
            $ekle('/kategori/'.$kategori->slug);
        });

        /*
        | ⚠️ Yasal metinler de giriyor: mesafeli satış ve iade politikası
        | müşterinin arayabileceği sayfalar ve markanın güvenilirliğini
        | gösteriyor.
        */
        $ekle('/yasal', null, 'monthly');

        $xml = view('storefront.sitemap', ['adresler' => $adresler])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        /*
        | ⚠️ VARSAYILAN `robots.txt` HER ŞEYE İZİN VERİYORDU — panel giriş
        | ekranı, sepet ve ödeme dâhil. Bunlar taranınca hem tarama
        | bütçesi harcanıyor hem de giriş sayfası dizine girebiliyor.
        |
        | ⚠️ `Disallow` bir GÜVENLİK aracı değil: korumayı middleware
        | yapıyor. Buradaki amaç yalnızca motorun boşa gezmemesi.
        |
        | ⚠️ Sitemap adresi MUTLAK olmalı ve markanın kendi alan adını
        | taşımalı — `url()` istekten üretiyor.
        */
        $satirlar = [
            'User-agent: *',
            'Disallow: /yonetim',
            'Disallow: /sepet',
            'Disallow: /odeme',
            'Disallow: /hesabim',
            'Disallow: /giris',
            'Disallow: /kayit',

            // ⚠️ Arama sonucu sayfaları: sonsuz adres üretiyor.
            'Disallow: /*?q=',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $satirlar), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
