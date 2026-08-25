<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\VariantSelector;
use App\Domain\Favorite\FavoriteService;
use App\Domain\Review\ReviewService;
use App\Domain\Settings\ThemeSettings;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ürün detay sayfası. (4B)
 *
 * ★ 4-K3: API controller'ı değil `app/Domain/` sorgusu çağrılıyor.
 */
class ProductPageController extends Controller
{
    public function __construct(
        private readonly ProductQuery $sorgu,
        private readonly ThemeSettings $tema,
        private readonly VariantSelector $secici,
        private readonly ReviewService $yorumlar,
        private readonly FavoriteService $favoriler,
    ) {}

    public function __invoke(Request $istek, string $slug): View
    {
        // ⚠️ GUARD AÇIKÇA (4.5I): sayfa katmanında kimlik OTURUMDA.
        $musteri = $istek->user('customer-web');

        /*
        | ⚠️ `vitrindeBul()` — panel sorgusu DEĞİL. Taslak, arşiv ve
        | satılamayan ürün vitrinde 404 vermeli; panel sorgusu kullanılsaydı
        | yayınlanmamış ürünün sayfası adresi bilen herkese açık olurdu
        | (1B-K10).
        */
        $urun = $this->sorgu->vitrindeBul($slug);

        if ($urun === null) {
            throw new NotFoundHttpException('Ürün bulunamadı.');
        }

        /*
        | ⚠️ `options.values` de yükleniyor (4.6A): varyant seçicisi eksen
        | adlarını ve değerlerini gösteriyor. Yüklenmezse her eksen için
        | ayrı sorgu açılırdı — ürün sayfası N+1'e düşerdi.
        */
        $urun->load(['images', 'variants', 'options.values']);

        /*
        | ⚠️ Görünüm adı `match` ile SABİT metne çevriliyor, birleştirmeyle
        | değil: ayardan gelen metnin görünüm yoluna girmesi, o metin bir
        | gün doğrulanmadan geçerse sunucudaki BAŞKA bir Blade dosyasının
        | render edilmesi demek (4A'da PHPStan da uyarmıştı).
        */
        $gorunum = match ($this->tema->duzen()) {
            'vitrinli' => 'storefront.vitrinli.urun',
            default => 'storefront.sade.urun',
        };

        return view($gorunum, [
            'urun' => $urun,

            /*
            | ★ VARYANT SEÇİCİSİ (4.6A) — veri DOMAIN'den.
            |
            | ⚠️ "Bu değer seçilebilir mi" sorusu satılabilirlik kuralına
            | bağlı (`stock − committed` + aktiflik). Ekran kendi hesabını
            | yapsaydı müşteri, başka bir siparişe BAĞLI stoğu seçer ve
            | sepete eklerken hata alırdı — 4.5J'deki "iki formül"
            | tuzağının aynısı.
            */
            'secici' => $this->secici->coz($urun),
            'listeEsigi' => VariantSelector::LISTE_ESIGI,

            /*
            | ★ YORUMLAR (4.6C). Uçlar 2E'de, moderasyon 4.5F'de vardı ama
            | müşterinin yorumları GÖREBİLECEĞİ bir yer hiç yoktu.
            |
            | ⚠️ Sayfalama YOK, ilk 20 gösteriliyor. Sunucuda render edilen
            | bir sayfada (4-K1) yorum sayfalaması ürün adresine sorgu
            | parametresi eklemek demek ve o adres SEO'da ürünün kendisiyle
            | yarışırdı. Daha fazlası gerekirse ayrı bir iş.
            */
            'yorumlar' => $this->yorumlar->vitrindeGorunenler($urun)->limit(20)->get(),

            /*
            | ★ "YORUM YAZABİLİR MİYİM" sorusunu EKRAN CEVAPLAMIYOR, DOMAIN
            | cevaplıyor (`yazmaEngeli`).
            |
            | ⚠️ Ekran kendi kontrolünü yazsaydı iki formül olurdu ve
            | zamanla ayrışırlardı — 4.5J'de sepet rozeti ile sepetin
            | kendisi tam bu yüzden farklı sonuç veriyordu.
            |
            | ⚠️ Misafirde sorgu HİÇ çalışmıyor: `null` müşteri için engel
            | sorulmuyor, ekran doğrudan "giriş yapın" diyor.
            */
            'yorumEngeli' => $musteri instanceof Customer
                ? $this->yorumlar->yazmaEngeli($musteri, $urun)?->getMessage()
                : null,
            'musteriGirisli' => $musteri instanceof Customer,

            /*
            | ★ FAVORİ DURUMU (4.6D) — düğme iki durumlu, hangisinde
            | olduğunu sunucu söylüyor.
            |
            | ⚠️ Misafirde sorgu HİÇ çalışmıyor: `false` sabit. Koşulsuz
            | sorulsaydı her misafir ziyaretinde gereksiz bir sorgu açılır
            | ve ürün sayfası herkese açık olduğu için bu yük TÜM
            | trafiğe binerdi.
            */
            'favorideMi' => $musteri instanceof Customer
                && $this->favoriler->favorideMi($musteri, $urun),
        ]);
    }
}
