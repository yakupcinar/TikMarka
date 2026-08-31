<?php

namespace App\Domain\Notification;

use App\Mail\AbandonedOrderMail;
use App\Mail\OrderPaidMail;
use App\Mail\PaymentFailedMail;
use App\Mail\ShipmentMail;
use App\Models\Fulfillment;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Müşteriye giden postaların tek kapısı. (2H)
 *
 * ★ `EventRecorder` ile aynı desen ve aynı sebeple: iş kuralı burada
 * yaşamıyor, yalnızca "şu oldu, haber ver" deniyor.
 *
 * ⚠️ Bu sınıf kiracıdan HABERSİZ (M-2.7). Hangi markada olduğunu sormuyor;
 * kimliği kuyruk altyapısı taşıyor.
 */
class Notifier
{
    /**
     * ⚠️ Sipariş onayı ÖDEME BAŞARILI OLUNCA gider, sipariş oluşunca DEĞİL.
     * Sipariş `pending` doğuyor ve ödemesi hiç tamamlanmayabiliyor (1D).
     */
    public function siparisOnayi(Order $siparis): void
    {
        $this->gonder($siparis->email, new OrderPaidMail($siparis));
    }

    public function odemeBasarisiz(Order $siparis): void
    {
        $this->gonder($siparis->email, new PaymentFailedMail($siparis));
    }

    /**
     * Ödemesi yarım kalmış sipariş hatırlatması. (2F)
     *
     * ⚠️ `odemeBasarisiz` ile AYRI: orada müşteri denedi ve reddedildi,
     * burada hiç denemedi. Aynı mail kullanılsaydı vazgeçen müşteri
     * kartında sorun olduğunu sanırdı.
     */
    public function odemeHatirlatmasi(Order $siparis): void
    {
        $this->gonder($siparis->email, new AbandonedOrderMail($siparis));
    }

    public function kargoBildirimi(Fulfillment $paket): void
    {
        $this->gonder($paket->order?->email, new ShipmentMail($paket));
    }

    /**
     * ★ 2H-K2 — POSTA DÜŞERSE İŞ BOZULMAZ.
     *
     * ⚠️ 1F-K3'ün tekrarı. Mailin gitmemesi kötü; siparişin oluşamaması
     * felaket. Kuyruk sürücüsü erişilemezse istisna yükselip ödemeyi
     * düşürmesin diye yutuluyor.
     *
     * Yutulan tek şey KUYRUĞA ATAMAMA. İşin kendisi düşerse kuyruk zaten
     * tekrar deniyor.
     *
     * ⚠️ `afterCommit` YOK — bilerek. Bu çağrılar transaction DIŞINDA
     * yapılıyor (ödeme sonucu ve sevkiyat, kendi transaction'ları
     * kapandıktan sonra). 1F-K5'teki durum farklıydı: orada sipariş
     * oluşturma transaction'ının İÇİNDEYDİK.
     */
    private function gonder(?string $alici, Mailable $posta): void
    {
        /*
        | ⚠️ Alıcı yoksa sessizce çıkılıyor. `orders.email` her zaman dolu
        | (1D: misafir siparişinin tek iletişim kanalı) ama silinmiş
        | siparişin paketi gibi uç durumlarda ilişki boş dönebiliyor.
        */
        if ($alici === null || $alici === '') {
            return;
        }

        /*
        | ★ AYRILMIŞ UZANTIYA POSTA ÇIKMAZ (B4).
        |
        | ⚠️ Kullanıcının bildirdiği "Address not found" iadesinden çıktı:
        | test siparişi `vazgec@marka-a.localhost` adresiyle verilmiş,
        | sistem GERÇEKTEN posta göndermiş, Gmail alan adını DNS'te
        | bulamamış ve iade bildirimi gönderen hesaba düşmüş.
        |
        | ⚠️ `DeliverableEmail` (4.5C) bunları GEÇİRİYOR ve öyle kalmalı:
        | bütün test verisi `@ornek.test` kullanıyor, kuralı sıkılaştırmak
        | süiti kırardı. Eleme doğrulamada değil GÖNDERİMDE.
        |
        | ⚠️ DNS SORGUSU YOK — liste statik. RFC 6761 bu uzantıları
        | "asla çözülmeyecek" diye ayırmış, yani bakmaya gerek yok.
        | 4.5C'nin "ödeme akışında ağa çıkılmaz" kararı korunuyor
        | (orada tek sorgu 24 saniye sürmüştü).
        |
        | ⚠️ Bedeli sessiz ama gerçek: her deneme gönderen hesapta bir
        | iade biriktiriyor ve zamanla gönderen itibarını düşürüyor.
        */
        if ($this->cozulemez($alici)) {
            return;
        }

        try {
            Mail::to($alici)->queue($posta);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Alan adı tanımı gereği çözülemez mi? (RFC 6761 / RFC 2606)
     *
     * ⚠️ `.local` de listede: mDNS'e ayrılmış, internette çözülmüyor.
     */
    private function cozulemez(string $alici): bool
    {
        $alan = mb_strtolower(ltrim((string) mb_strrchr($alici, '@'), '@'));

        // RFC 6761 — asla çözülmeyecek üst düzey uzantılar.
        foreach (['localhost', 'test', 'invalid', 'example', 'local'] as $uzanti) {
            if ($alan === $uzanti || str_ends_with($alan, '.'.$uzanti)) {
                return true;
            }
        }

        /*
        | ★ RFC 2606 İKİNCİ DÜZEY ADLAR DA BURADA.
        |
        | ⚠️ Uzantı taraması bunları GÖRMÜYOR: `example.com` `.com`
        | uzantısında ve elemeden geçiyordu. Oysa bu üç ad belgeleme
        | için ayrılmış — MX kaydı yok, posta teslim edilmiyor.
        | Yani "ayrılmış uzantı" kuralını yazıp bunları atlamak,
        | kuralı yarım uygulamak olurdu.
        */
        foreach (['example.com', 'example.net', 'example.org'] as $ad) {
            if ($alan === $ad || str_ends_with($alan, '.'.$ad)) {
                return true;
            }
        }

        return false;
    }
}
