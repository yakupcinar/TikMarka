<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\ProductQuery;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vitrinde kategori gezinme. (4.6B)
 *
 * ★ ÖLÇÜLEN EKSİK: marka kategori ağacı kuruyordu (1B), ürünleri
 * kategoriye bağlıyordu, ama müşteri kategoriye HİÇBİR YERDEN
 * ulaşamıyordu. Ekmek kırıntısı API cevabında vardı; tıklanacak bir
 * sayfa yoktu.
 *
 * ⚠️ Adres `/k/{slug}` — 1B'de kararlaştırıldı ve kategori YOLU
 * İÇERMİYOR: kategori ağaçta taşınınca adres kırılmasın diye.
 */
class CategoryPageController extends Controller
{
    /** Bir sayfada gösterilen en fazla ürün. */
    public const LIMIT = 48;

    public function __construct(private readonly ProductQuery $sorgu) {}

    /** Kategori ağacı. */
    public function index(): View
    {
        return view('storefront.kategoriler', [
            'kategoriler' => $this->urunuOlanlar(),
        ]);
    }

    public function show(string $slug): View
    {
        $kategori = Category::query()->where('slug', $slug)->first();

        if ($kategori === null) {
            throw new NotFoundHttpException('Kategori bulunamadı.');
        }

        /*
        | ★ ALT AĞAÇ DÂHİL (`kategoriyeGore`, 2C). Üst kategoriye tıklayan
        | müşteri boş sayfa görmemeli: "Giyim"de doğrudan ürün olmasa bile
        | "Giyim / Tişört" altındakiler listelenmeli.
        */
        $urunler = $this->sorgu->forStorefront()
            ->tap(fn ($q) => $this->sorgu->kategoriyeGore($q, $kategori))
            ->limit(self::LIMIT)
            ->get();

        return view('storefront.kategori', [
            'kategori' => $kategori,

            // ⚠️ Zincir MODELDEN — API ekmek kırıntısıyla aynı formül.
            'zincir' => $kategori->zincir(),

            /*
            | ⚠️ Alt kategoriler de gösteriliyor: ağacın ortasındaki bir
            | kategoride müşteri ancak böyle DERİNE inebilir. Yalnızca
            | ürün listelenseydi yaprak olmayan kategoriler çıkmaz sokak
            | olurdu.
            */
            'altlar' => Category::query()
                ->where('parent_id', $kategori->id)
                ->orderBy('name')
                ->get(),

            'urunler' => $urunler,
        ]);
    }

    /**
     * ÜRÜNÜ OLAN kategoriler — alt ağaç dâhil.
     *
     * ⚠️ Boş kategori listede GÖSTERİLMİYOR. 4.5H'de koleksiyon için
     * verilen kararın aynısı: müşteriye tıklanacak ama hiçbir şey
     * göstermeyen bir bağlantı sunmak, mağazayı bozuk gösterir.
     *
     * ⚠️ "Boş" derken ALT AĞAÇ sayılıyor: kendi ürünü olmayan ama altında
     * ürün bulunan üst kategori GÖRÜNÜYOR — yoksa ağacın gövdesi kaybolur
     * ve yapraklara hiç ulaşılamazdı.
     *
     * ⚠️ Tek sorgu: her kategori için ayrı sayım yapılsaydı kategori
     * sayısı kadar sorgu açılırdı (N+1).
     *
     * @return Collection<int, Category>
     */
    private function urunuOlanlar(): Collection
    {
        /** @var list<int> $doluIdler */
        $doluIdler = $this->sorgu->forStorefront()
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->all();

        if ($doluIdler === []) {
            return collect();
        }

        /*
        | ⚠️ Dolu kategorilerin ATALARI da listeye giriyor. `path` ön ek
        | taraması yerine ata id'leri toplanıyor: `path` "/1/5/12/"
        | biçiminde, yani zincir zaten satırda duruyor ve ek sorgu
        | gerekmiyor.
        */
        $dolular = Category::query()->whereIn('id', $doluIdler)->get();

        $gorunurIdler = $dolular
            ->flatMap(fn (Category $k) => [...$k->ataIdleri(), $k->id])
            ->unique()
            ->all();

        return Category::query()
            ->whereIn('id', $gorunurIdler)
            ->orderBy('path')
            ->get()
            ->collect();
    }
}
