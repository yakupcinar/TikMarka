<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\ProductQuery;
use App\Domain\Review\ReviewService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vitrinde yorum yazma — SAYFA katmanı. (4.6C)
 *
 * ★ Uçlar 2E'de, panel moderasyonu 4.5F'de yazılmıştı ama müşterinin
 * yorum yazabileceği bir EKRAN hiç yoktu: özellik vardı, ulaşılamıyordu.
 *
 * ⚠️ API'deki [ReviewController] ile aynı işi yapmıyor, AYRI olmak
 * zorunda: orada kimlik sanctum token'ında, burada OTURUMDA. Guard adı
 * açıkça yazılmazsa varsayılan (`customer`, token) sorulur ve giriş
 * yapmış müşteri misafir sayılır — 4.5I'de ölçüldü.
 */
class ReviewPageController extends Controller
{
    public function __construct(
        private readonly ReviewService $yorumlar,
        private readonly ProductQuery $urunler,
    ) {}

    public function yaz(Request $istek, string $slug): RedirectResponse
    {
        $urun = $this->urunler->vitrindeBul($slug);

        if ($urun === null) {
            throw new NotFoundHttpException('Ürün bulunamadı.');
        }

        $veri = $istek->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        // ⚠️ GUARD AÇIKÇA: gerekçesi sınıf yorumunda.
        $musteri = $istek->user('customer-web');

        if (! $musteri instanceof Customer) {
            return back()->with('hata', 'Yorum yazmak için giriş yapmalısınız.');
        }

        try {
            $this->yorumlar->yaz($musteri, $urun, $veri);
        } catch (DomainException $hata) {
            /*
            | ★ TARAYICIYA HTML, API'YE JSON.
            |
            | ⚠️ Genel işleyiciler bu istisnaları JSON'a çeviriyor
            | (`bootstrap/app.php`) ve o API için DOĞRU. Burada
            | yakalanmasaydı müşteri ham JSON görürdü — 4A · 4B · 4.5G ·
            | 4.5O'da dört kez yaşanan hatanın aynısı.
            |
            | ⚠️ Mesaj istisnadan geliyor, burada YENİDEN YAZILMIYOR:
            | "satın almadınız", "zaten yazdınız" ve "e-postanızı
            | doğrulayın" birbirinden farklı şeyler ve müşterinin yapması
            | gereken de farklı.
            */
            return back()->withInput()->with('hata', $hata->getMessage());
        }

        /*
        | ⚠️ "Onay bekliyor" AÇIKÇA söyleniyor. Söylenmeseydi müşteri
        | yorumunu vitrinde göremeyip kaybolduğunu sanır, ikinci kez
        | yazmayı dener ve "zaten yorum yazdınız" uyarısı alırdı.
        */
        return redirect()
            ->route('vitrin.urun', ['slug' => $slug])
            ->with('mesaj', 'Yorumunuz alındı, onaylandıktan sonra yayınlanacak.');
    }
}
