<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\HomeSections;
use App\Domain\Catalog\ProductQuery;
use App\Domain\Search\ProductSearch;
use App\Domain\Settings\ThemeSettings;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Vitrin ana sayfası — markanın müşterisinin gördüğü ilk ekran. (4A)
 *
 * ★ 4-K3'ÜN UYGULAMASI: bu controller API controller'ını ÇAĞIRMIYOR,
 * doğrudan `app/Domain/` servislerini kullanıyor.
 *
 * ```
 * HomeController → ProductQuery / ProductSearch      ✅
 * HomeController → CatalogController                 ❌ ASLA
 * ```
 *
 * ⚠️ İkincisi yapılsaydı HTTP üstünden kendi kendimize istek atmış olurduk:
 * yavaş, kimlik bağlamı kayıp, hata ayıklaması cehennem. Kuralın işe
 * yaraması, iş mantığının Faz 1'den beri Domain'de durmasına borçlu.
 */
class HomeController extends Controller
{
    /**
     * Ana sayfada gösterilen ürün sayısı.
     *
     * ⚠️ Sayfalama YOK ve bu bilinçli: 4A vitrin İSKELETİ. Sayfalama
     * ürün listesi sayfasıyla birlikte 4B'de geliyor. Sınırsız bırakmak
     * ise 10.000 ürünlü bir markada sayfayı çökertirdi.
     */
    public const LIMIT = 24;

    public function __construct(
        private readonly ProductQuery $sorgu,
        private readonly ProductSearch $arama,
        private readonly ThemeSettings $tema,
        private readonly HomeSections $bolumler,
    ) {}

    public function __invoke(Request $istek): View
    {
        $kelime = $istek->query('q');
        $aramaVar = is_string($kelime) && trim($kelime) !== '';

        /*
        | ⚠️ Arama da `forStorefront()` üzerinden geçen sorguyu kullanıyor:
        | taslak, arşiv ve satılamayan ürün aramada da çıkmıyor (1B-K10).
        | Ayrı bir sorgu yazılsaydı arama, vitrinin göstermediği ürünleri
        | gösterirdi — CatalogController'daki kararın aynısı.
        */
        $sorgu = $aramaVar
            ? $this->arama->ara((string) $kelime)
            : $this->sorgu->forStorefront();

        $urunler = $sorgu
            ->with(['images', 'variants'])
            ->limit(self::LIMIT)
            ->get();

        /*
        | ★ Görünüm adı TEMADAN seçiliyor ama METİN BİRLEŞTİRMEYLE DEĞİL.
        |
        | ⚠️ Önce `'storefront.'.$duzen.'.anasayfa'` yazılmıştı; PHPStan
        | seviye 8 uyardı (`view-string` beklenirken `string`) ve uyarı
        | HAKLIYDI: ayardan gelen metnin görünüm yoluna girmesi demek,
        | o metin bir gün doğrulanmadan geçerse sunucudaki BAŞKA bir Blade
        | dosyasının render edilmesi demek.
        |
        | `match` ile eşleme sabit: ayar hangi değeri taşırsa taşısın
        | buradan yalnızca bizim yazdığımız yollardan biri çıkabiliyor.
        | 4G'de yeni düzen eklenince buraya bir kol eklenecek.
        */
        $gorunum = match ($this->tema->duzen()) {
            'vitrinli' => 'storefront.vitrinli.anasayfa',
            default => 'storefront.sade.anasayfa',
        };

        /*
        | ★ ANA SAYFA BÖLÜMLERİ (B1) — arama YOKKEN.
        |
        | ⚠️ Arama sırasında bölüm çizilmiyor: müşteri bir şey aradıysa
        | ekranın cevabı o olmalı. "Popüler" ve "yeni gelenler" arama
        | sonucunun altına konsaydı sonuç kaybolur, üstüne konsaydı
        | müşteri aradığını bulamaz.
        |
        | ⚠️ GUARD AÇIKÇA (4.5I): sayfa katmanında kimlik OTURUMDA.
        | `$istek->user()` yazılsaydı varsayılan guard (sanctum) sorulur,
        | `null` döner ve giriş yapmış müşteri MİSAFİR sayılırdı — yani
        | "sizin için seçtiklerimiz" hiç kimseye çıkmazdı.
        */
        $musteri = $istek->user('customer-web');

        return view($gorunum, [
            'urunler' => $urunler,
            'bolumler' => $aramaVar
                ? []
                : $this->bolumler->bolumler($musteri instanceof Customer ? $musteri : null),
            'arama' => $aramaVar ? trim((string) $kelime) : null,
        ]);
    }
}
