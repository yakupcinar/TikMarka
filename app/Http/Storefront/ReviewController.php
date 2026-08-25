<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\ProductQuery;
use App\Domain\Review\ReviewService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * VİTRİN yorumları. (2E)
 *
 * ⚠️ Listeleme herkese açık, YAZMA `auth:customer` arkasında — misafir
 * yorum yazamıyor. Bu bir SINIR, gizlenmiyor: misafir siparişte kimlik
 * yok, "bu kişi gerçekten aldı mı" sorusu cevaplanamaz.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $yorumlar,
        private readonly ProductQuery $urunler,
    ) {}

    /** Ürünün ONAYLI yorumları. */
    public function index(string $slug): JsonResponse
    {
        $urun = $this->urunler->vitrindeBul($slug);

        if ($urun === null) {
            return response()->json(['message' => 'Ürün bulunamadı.'], 404);
        }

        $sayfa = $this->yorumlar->vitrindeGorunenler($urun)->paginate(20);

        return response()->json([
            'rating' => [
                'average' => $urun->rating_avg,
                'count' => $urun->rating_count,
            ],
            'reviews' => collect($sayfa->items())->map(fn (Review $y) => $this->goster($y)),
            'meta' => [
                'page' => $sayfa->currentPage(),
                'total' => $sayfa->total(),
                'last_page' => $sayfa->lastPage(),
            ],
        ]);
    }

    /** Müşteri yorum yazar — onay bekleyerek. */
    public function store(Request $istek, string $slug): JsonResponse
    {
        $urun = $this->urunler->vitrindeBul($slug);

        if ($urun === null) {
            return response()->json(['message' => 'Ürün bulunamadı.'], 404);
        }

        $veri = $istek->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $musteri = $istek->user();

        if (! $musteri instanceof Customer) {
            return response()->json(['message' => 'Giriş yapmanız gerekiyor.'], 401);
        }

        $yorum = $this->yorumlar->yaz($musteri, $urun, $veri);

        /*
        | ⚠️ Cevapta "onay bekliyor" AÇIKÇA söyleniyor. Söylenmeseydi
        | müşteri yorumunu vitrinde göremeyip kaybolduğunu sanırdı.
        */
        return response()->json([
            'review' => $this->goster($yorum),
            'message' => 'Yorumunuz alındı, onaylandıktan sonra yayınlanacak.',
        ], 201);
    }

    /** @return array<string, mixed> */
    private function goster(Review $yorum): array
    {
        return [
            'uuid' => $yorum->uuid,
            'rating' => $yorum->rating,
            'title' => $yorum->title,
            'body' => $yorum->body,
            'status' => $yorum->status->value,

            /*
            | ⚠️ Ad KISALTILIYOR ("Ahmet Y."), e-posta hiç yok. Tam ad
            | yazılsaydı müşterinin kim olduğu vitrinde herkese açık
            | olurdu (2G'nin mantığı burada da geçerli).
            |
            | ⚠️ `moderation_note` de YOK — o personel içindir.
            */
            /*
            | ⚠️ Kısaltma MODELDE (4.6C): vitrin sayfası da aynı adı
            | gösteriyor. Burada ayrı bir kopya kalsaydı iki yüzey
            | zamanla ayrışırdı.
            */
            'author' => $yorum->vitrinAdi(),
            'published_at' => $yorum->moderated_at?->toIso8601String(),
        ];
    }
}
