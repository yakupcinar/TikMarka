<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\LogRecord;
use Throwable;

/**
 * Her günlük satırına "bu hangi istekti" bağlamını ekler.
 *
 * ★ BU SINIF BİR ÖLÇÜMDEN DOĞDU. Günlükteki gerçek bir satır şöyleydi:
 *
 *   [2026-08-19 08:29:28] local.ERROR: [iyzico] email hatalı format ile
 *   gönderilmiştir {"exception":"[object] (PaymentProviderException…
 *
 * Hangi marka, hangi müşteri, hangi sipariş — hiçbiri yok. Yani
 * e-ticarette asıl soru olan *"A markasının müşterisi 14:32'de neden
 * ödeyemedi"* bu günlükle cevaplanamıyordu. Hata teşhis edilemedi;
 * sebebi 4.5C'de gerçek istek atılarak bulundu.
 *
 * ⚠️ MIDDLEWARE DEĞİL, MONOLOG İŞLEYİCİSİ — ve bu bilinçli.
 * Middleware olsaydı kiracının başlatılmasından ÖNCE mi SONRA mı
 * koştuğu sıraya bağlı olurdu; Laravel middleware'leri öncelik
 * listesine göre yeniden sıralıyor ve yazdığın sıra sessizce geçersiz
 * olabiliyor (4H'de ısırdı). İşleyici satır YAZILIRKEN çalışıyor, yani
 * o an kiracı zaten çözülmüş — sıraya hiç bağımlı değil.
 */
final class IstekBaglami
{
    /**
     * Bağlamın yazıldığı yer — satırın SONU değil BAŞI.
     *
     * ⚠️ Varsayılan biçimde `%extra%` en sonda ve önünde yığın izi var:
     * ölçüldü, tek bir hata girdisi **10.351 karakter** ve bağlam onun
     * son 100 karakterinde kalıyordu. Yani teşhis için eklenen bilgi,
     * teşhis edilecek gürültünün ARKASINA düşüyordu. CI anotasyonu
     * dersinin aynısı: bilgi KONUMA göre değil ÖNEME göre yerleşir.
     */
    private const BICIM = "[%datetime%] %channel%.%level_name% %extra%: %message% %context%\n";

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $kayit): LogRecord {
            return $kayit->with(extra: [...$kayit->extra, ...$this->baglam()]);
        });

        foreach ($logger->getHandlers() as $isleyici) {
            if (! method_exists($isleyici, 'setFormatter')) {
                continue;
            }

            /*
            | ⚠️ BAŞKA BİÇİMLENDİRİCİ SEÇİLMİŞSE DOKUNULMUYOR. `json`
            | kanalı makine için yazılıyor (toplayıcı okuyor) ve orada
            | satır biçimi bozulursa bağlam alanları ayrıştırılamaz —
            | üstelik bu HATA VERMEZ, sadece Loki'de her satır tek bir
            | metin olur ve `marka` diye bir alan hiç doğmaz.
            */
            if (! $isleyici instanceof FormattableHandlerInterface) {
                continue;
            }

            if (! $isleyici->getFormatter() instanceof LineFormatter) {
                continue;
            }

            $isleyici->setFormatter(new LineFormatter(self::BICIM, 'Y-m-d H:i:s', true, true));
        }
    }

    /**
     * @return array<string, scalar>
     */
    private function baglam(): array
    {
        $baglam = [];

        /*
        | ⚠️ HER OKUMA TRY İÇİNDE. Bu kod HATA YAZILIRKEN çalışıyor —
        | yani sistemin zaten bozuk olduğu anda. Bağlam toplarken atılan
        | bir istisna, asıl hatanın kaydını da yok eder ve geriye
        | teşhis edilecek hiçbir şey kalmaz.
        */
        try {
            $kiraci = tenant();

            if ($kiraci !== null) {
                $baglam['marka'] = (string) $kiraci->getTenantKey();
            }
        } catch (Throwable) {
            // Bağlam eksik kalsın; satırın kendisi kaybolmasın.
        }

        try {
            $istekId = Context::get('istek_id');

            if (is_string($istekId)) {
                $baglam['istek_id'] = $istekId;
            }
        } catch (Throwable) {
            // aynısı
        }

        return [...$baglam, ...$this->kimlik()];
    }

    /**
     * Oturumdaki kimlik — ÇÖZÜLMÜŞSE.
     *
     * ⚠️ `user()` DEĞİL `hasUser()`: birincisi kullanıcıyı çözmek için
     * VERİTABANINA GİDİYOR. Günlük yazarken sorgu açmak iki şeyi
     * birden riske atar — veritabanı çökmüşse (ki hatanın sebebi
     * genelde odur) günlükleme de çöker, ve sorgu günlüğü açıksa
     * kendini besleyen bir döngü doğar. `hasUser()` yalnızca "daha
     * önce çözüldü mü" diye bakıyor, sorgu açmıyor.
     *
     * ⚠️ E-POSTA YAZILMIYOR — kimlik numarası yeterli. Günlük dosyası
     * KVKK yollarının (`Anonymizer`/`DataExporter`) göremediği bir yer:
     * müşteri "beni unut" dediğinde maskelenmeyen tek kopya orada
     * kalırdı.
     *
     * @return array<string, int>
     */
    private function kimlik(): array
    {
        foreach (['customer-web' => 'musteri', 'staff-web' => 'personel', 'customer' => 'musteri', 'staff' => 'personel'] as $guard => $ad) {
            try {
                if (! Auth::guard($guard)->hasUser()) {
                    continue;
                }

                $kimlik = Auth::guard($guard)->id();

                if (is_int($kimlik) || is_string($kimlik)) {
                    return [$ad => (int) $kimlik];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return [];
    }
}
