<?php

namespace App\Http\Storefront;

use App\Models\Product;

/**
 * Ürün sayfasının schema.org yapısal verisi. (B3)
 *
 * ★ NEDEN BLADE'DE DEĞİL: schema.org anahtarları `@` ile başlıyor
 * (`@context`, `@type`) ve Blade bunları KENDİ YÖNERGESİ sanıyor.
 * Ölçüldü — `'@context'` anahtarı çıktıda şu hâle geldi:
 *
 *     "<?php $__contextArgs = []; if (context()->has(...
 *
 * Yani Laravel 11'in `@context` yönergesi derlenip JSON'un içine
 * girdi ve belge geçersiz oldu. Kaçış yazmak yerine veri şekillendirme
 * PHP'ye alındı; zaten oraya ait.
 *
 * ⚠️ HTTP katmanında, `app/Domain/` değil: adres üretiyor (M-2.7).
 */
class ProductStructuredData
{
    /** @return array<string, mixed> */
    public function uret(Product $urun, string $adres, string $markaAdi): array
    {
        $satilabilir = $urun->variants->filter(fn ($v) => $v->satinAlinabilirMi());

        /*
        | ⚠️ FİYAT SATILABİLİR varyantlardan. Tükenmiş ucuz varyantın
        | fiyatı yazılsaydı arama sonucundaki fiyat ile sayfadaki fiyat
        | tutmazdı — Google bunu yanıltıcı fiyat sayıyor ve zengin
        | sonuçtan düşürüyor.
        */
        $enDusuk = $satilabilir->min('price') ?? $urun->variants->min('price');

        $gorsel = $urun->images->first();

        $veri = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $urun->title,
            'description' => trim(strip_tags((string) $urun->description)) ?: $urun->title,
            'url' => $adres,
            'image' => $gorsel !== null ? [$gorsel->url()] : [],
            'brand' => ['@type' => 'Brand', 'name' => $urun->brand ?: $markaAdi],
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format((float) $enDusuk, 2, '.', ''),
                'priceCurrency' => 'TRY',
                'url' => $adres,
                'availability' => $satilabilir->isNotEmpty()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ];

        /*
        | ⚠️ PUAN YALNIZCA YORUM VARSA. Yorumsuz üründe `aggregateRating`
        | yazmak Google'ın yapısal veri politikasına aykırı ve sayfayı
        | zengin sonuçtan TAMAMEN düşürebiliyor.
        */
        if ((int) $urun->rating_count > 0) {
            $veri['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $urun->rating_avg, 1),
                'reviewCount' => (int) $urun->rating_count,
            ];
        }

        return $veri;
    }
}
