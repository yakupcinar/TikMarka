<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\ProductQuery;
use App\Domain\Favorite\FavoriteService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vitrin favorileri. (4.6D)
 *
 * ⚠️ GUARD ADI HER YERDE AÇIKÇA `customer-web`. Varsayılan guard
 * `customer` (sanctum, TOKEN) ve sayfa katmanında kimlik OTURUMDA; adı
 * yazılmasaydı giriş yapmış müşteri MİSAFİR sayılırdı (4.5I'de ölçüldü).
 */
class FavoritePageController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favoriler,
        private readonly ProductQuery $urunler,
    ) {}

    /** Favoriye ekler ya da çıkarır. */
    public function degistir(Request $istek, string $slug): RedirectResponse
    {
        /*
        | ⚠️ `vitrindeBul` — yayınlanmamış ürün favorilenemiyor. Ham
        | `Product::where('slug')` yazılsaydı adresi bilen biri taslak
        | ürünü favorileyebilir ve varlığını doğrulamış olurdu (1B-K10).
        */
        $urun = $this->urunler->vitrindeBul($slug);

        if ($urun === null) {
            throw new NotFoundHttpException('Ürün bulunamadı.');
        }

        $musteri = $istek->user('customer-web');

        if (! $musteri instanceof Customer) {
            return back()->with('hata', 'Favorilere eklemek için giriş yapmalısınız.');
        }

        $favorideMi = $this->favoriler->degistir($musteri, $urun);

        /*
        | ⚠️ `back()` — ürün sayfasına SABİT yönlendirme DEĞİL. Düğme hem
        | ürün sayfasında hem favoriler listesinde var; sabit yazılsaydı
        | listeden çıkaran müşteri ürün sayfasına savrulurdu.
        */
        return back()->with('mesaj', $favorideMi
            ? 'Ürün favorilerinize eklendi.'
            : 'Ürün favorilerinizden çıkarıldı.');
    }

    /** Müşterinin favori listesi. */
    public function liste(Request $istek): View
    {
        /** @var Customer $musteri */
        $musteri = $istek->user('customer-web');

        return view('storefront.favoriler', [
            'favoriler' => $this->favoriler->listele($musteri),
        ]);
    }
}
