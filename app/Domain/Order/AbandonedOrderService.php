<?php

namespace App\Domain\Order;

use App\Domain\Notification\Notifier;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AbandonedOrderService
{
    public const BEKLEME_DAKIKA = 60;

    public const SON_GECERLILIK_SAAT = 72;

    public function __construct(private readonly Notifier $bildirimler) {}

    /**
     * Hatırlatma bekleyen siparişler.
     *
     * @return Builder<Order>
     */
    public function bekleyenler(): Builder
    {
        $simdi = now();

        return Order::query()

            ->where('payment_status', PaymentStatus::Pending)
            ->whereNull('abandoned_reminded_at')

            // Rezervasyon süresi dolmuş olanlar.
            ->where('created_at', '<=', $simdi->copy()->subMinutes(self::BEKLEME_DAKIKA))

            // ★ Üst sınır — gerekçesi sabitte.
            ->where('created_at', '>=', $simdi->copy()->subHours(self::SON_GECERLILIK_SAAT))

            ->where('email', '!=', '')
            ->orderBy('id');
    }

    /**
     * Hatırlatmaları gönderir.
     *
     * @return int gönderilen sayısı
     */
    public function hatirlat(): int
    {
        $sayac = 0;

        foreach ($this->bekleyenler()->cursor() as $siparis) {
            if ($this->hatirlatBir($siparis)) {
                $sayac++;
            }
        }

        return $sayac;
    }

    /**
     * @return bool gönderildiyse true
     */
    public function hatirlatBir(Order $siparis): bool
    {
        $etkilenen = DB::table('orders')
            ->where('id', $siparis->id)
            ->whereNull('abandoned_reminded_at')
            ->update(['abandoned_reminded_at' => now()]);

        if ($etkilenen === 0) {
            return false;
        }

        $this->bildirimler->odemeHatirlatmasi($siparis);

        return true;
    }
}
