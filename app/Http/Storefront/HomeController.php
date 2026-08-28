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
     * ⚠️ B2'ye kadar sayfalama YOKTU ve 24'ten sonrası sessizce
     * kayboluyordu. Artık sayfa başına sayı — kaydırdıkça yükleniyor,
     * JavaScript kapalıysa "Daha fazla" bağlantısı çalışıyor.
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

        /*
        | ★ SAYFALAMA (B2) — ve bu bir KUSUR DÜZELTMESİ.
        |
        | ⚠️ Önce `limit(24)->get()` yazıyordu: 25. ürün ana sayfadan
        | HİÇ görünmüyordu ve bunu söyleyen bir şey de yoktu. Katalog
        | büyüdükçe ana sayfa sessizce eksik gösteriyordu.
        |
        | ⚠️ `withQueryString()` ŞART: arama yapılmışken sayfa 2'ye
        | geçen bağlantı `?q=` olmadan üretilirdi ve müşteri aramasını
        | kaybederdi.
        */
        $urunler = $sorgu
            ->with(['images', 'variants'])
            ->paginate(self::LIMIT, ['*'], 'sayfa')
            ->withQueryString();

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
            /*
            | ⚠️ BÖLÜMLER YALNIZCA İLK SAYFADA.
            |
            | Arama varsa hiç çizilmiyor (ekranın cevabı arama olmalı).
            | Sayfa 2+'de de çizilmiyor: bölümler bir KARŞILAMA öğesi,
            | listenin devamı değil. Çizilseydi "Daha fazla"ya basan
            | müşteri aynı "çok satanlar"ı ikinci kez görürdü ve her
            | sayfa gereksiz üç sorgu daha açardı.
            */
            'bolumler' => ($aramaVar || $urunler->currentPage() > 1)
                ? []
                : $this->bolumler->bolumler($musteri instanceof Customer ? $musteri : null),
            'arama' => $aramaVar ? trim((string) $kelime) : null,
        ]);
    }
}
