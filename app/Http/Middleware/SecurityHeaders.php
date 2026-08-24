<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tarayıcıya güvenlik başlıkları — hiçbiri Laravel'de yoktu. (4.6U)
 *
 * ★ NEDEN UYGULAMA KATMANINDA: `RateLimiter` için 4.6T'de verilen kararın
 * aynısı (M-4.1/3) — Caddy'de hiç `header` yönergesi yok, koruma altyapı
 * yapılandırmasına değil koda bağlı kalsın diye burada duruyor.
 *
 * ⚠️ CSP BİLEREK DAR TUTULUYOR — yalnızca `frame-ancestors`. Geniş bir
 * `default-src`/`script-src` politikası ölçülmeden yazılsaydı ödeme
 * iframe'ini (4.5-K1) kırma riski taşırdı: `paymentPageUrl` iyzico'nun
 * API cevabından DİNAMİK geliyor, sabit bir alan adı olarak izin
 * listesine yazılamaz — yanlış tahmin edilen bir domain, müşterinin
 * ödeme adımının ortasında sessizce boş bir çerçeve görmesi demektir.
 * `frame-ancestors` bu riski taşımıyor: yalnızca BİZİM sayfamızın
 * BAŞKASINCA çerçevelenmesini kapatıyor, bizim iyzico'yu çerçevelememizi
 * ETKİLEMİYOR — ikisi ayrı yön.
 */
class SecurityHeaders
{
    public function handle(Request $istek, Closure $next): Response
    {
        /** @var Response $cevap */
        $cevap = $next($istek);

        /*
        | ★ TIKLAMA HIRSIZLIĞI (clickjacking). Panel özellikle risk
        | altında: biri panel adresini görünmez bir iframe'e koyup üstüne
        | sahte bir düğme bindirebilir, personel gerçek düğmeye
        | bastığını sanırken altındaki gizli "sil"e tıklar.
        |
        | ⚠️ İKİ BAŞLIK BİRDEN: `X-Frame-Options` eski tarayıcılar için,
        | `frame-ancestors` (CSP) modern ve daha esnek olanı. Yalnızca
        | ikincisi yazılsaydı onu desteklemeyen eski bir tarayıcı hiç
        | korunmazdı.
        */
        $cevap->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $cevap->headers->set('Content-Security-Policy', "frame-ancestors 'self'");

        /*
        | ★ MIME KOKLAMA (sniffing). Marka panelden görsel yükleyebiliyor
        | (4.5E) — tarayıcı `image/png` diye sunulan bir dosyayı içeriğine
        | bakıp "aslında HTML/script" diye YENİDEN yorumlayabilir.
        | `nosniff` bunu kapatıyor: sunucunun beyan ettiği tür ne ise o.
        */
        $cevap->headers->set('X-Content-Type-Options', 'nosniff');

        /*
        | ★ REFERRER SIZINTISI — 4.5R'nin İMZALI adresini korur.
        |
        | ⚠️ Ödeme sonuç sayfası (`/odeme/sonuc/{uuid}?...&signature=…`)
        | imza TAŞIYOR. Varsayılan tarayıcı davranışında bu sayfadan
        | çıkan bir bağlantıya tıklanırsa TAM adres (imza dahil) hedef
        | sitenin sunucusuna `Referer` başlığıyla gönderilir. `strict-
        | origin-when-cross-origin` başka kökene yalnızca KÖKENİ
        | (şema+alan adı) gönderiyor — yol ve sorgu asla çıkmıyor.
        */
        $cevap->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        /*
        | ★ HTTPS ZORUNLULUĞU. `M-4`: her ortamda TEK giriş Caddy ve
        | TLS her zaman açık — `.localhost` bile `tls internal` ile
        | şifreli (bkz. Caddyfile). Yani bu başlık hiçbir ortamda
        | "birden HTTP'ye düşme" riski taşımıyor.
        |
        | ⚠️ `preload` YOK: tarayıcıların kalıcı ön yükleme listesine
        | girmek geri alınamaz bir adım (alan adı değişirse bile liste
        | yıllarca kalıcı kalabiliyor). `includeSubDomains` da yok — ileride
        | HTTPS'siz bir alt alan adı açılırsa (ör. içerik teslim ağı) onu
        | sessizce kırmasın diye.
        */
        $cevap->headers->set('Strict-Transport-Security', 'max-age=15552000');

        return $cevap;
    }
}
