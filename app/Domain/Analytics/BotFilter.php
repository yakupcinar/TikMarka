<?php

namespace App\Domain\Analytics;

/**
 * Otomatik trafiği ölçümün dışında tutar. (4.6F)
 *
 * ★ NEDEN GEREKLİ: görüntüleme olayı OKUMA YOLUNDA yazılıyor — her ürün
 * sayfası bir kayıt üretiyor. Ürün sayfası herkese açık olduğu için
 * arama motoru robotları, önizleme çekenler ve tarayıcılar aynı sayfayı
 * defalarca çekiyor. Elenmezse marka "bu ürüne 400 kez bakılmış" diye
 * bir sayı görür ve ona göre karar verir; oysa 400'ün büyük kısmı
 * kimsenin bakmadığı bir sayı olur.
 *
 * ⚠️ SINIF HTTP'DEN HABERSİZ: metin alıyor, `Request` almıyor. Böylece
 * kural HTTP dışından da sınanabiliyor ve kiracıdan da habersiz kalıyor
 * (M-2.7).
 *
 * ⚠️ BU LİSTE TAM DEĞİL ve olamaz — yeni robot her gün çıkıyor. Amaç
 * mükemmel eleme değil, sayının BÜYÜK KISMINI bozan bilinen trafiği
 * çıkarmak. Kesinlik gerekiyorsa çözüm daha uzun liste değil, sunucu
 * günlüğünden ayrı bir ölçüm.
 */
class BotFilter
{
    /**
     * Küçük harfe çevrilmiş kullanıcı aracısında aranan parçalar.
     *
     * ⚠️ `curl` ve `wget` DE BURADA: bizim gerçek HTTP doğrulama
     * koşularımız da bu yüzden olay üretmiyor. Bu doğru davranış ama
     * bilinmeli — "curl ile denedim, sayaç artmadı" bir arıza değil.
     *
     * @var list<string>
     */
    private const IMZALAR = [
        'bot', 'crawl', 'spider', 'slurp', 'archiver', 'scraper',
        'facebookexternalhit', 'whatsapp', 'telegram', 'skypeuripreview',
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'lighthouse',
        'curl', 'wget', 'python-requests', 'go-http-client', 'okhttp',
        'java/', 'axios', 'node-fetch', 'httpclient', 'postman',
        'pingdom', 'uptime', 'monitoring', 'preview',
    ];

    /**
     * Bu istek ölçüme girmeli mi?
     *
     * ⚠️ BOŞ kullanıcı aracısı da eleniyor. Gerçek tarayıcı her zaman
     * gönderiyor; göndermeyen bir istemci ya bir betik ya da başlığı
     * bilerek gizleyen bir araç. İkisi de "müşteri ürüne baktı" değil.
     */
    public function sayilirMi(?string $kullaniciAracisi): bool
    {
        $ajan = mb_strtolower(trim($kullaniciAracisi ?? ''));

        if ($ajan === '') {
            return false;
        }

        foreach (self::IMZALAR as $imza) {
            if (str_contains($ajan, $imza)) {
                return false;
            }
        }

        return true;
    }
}
