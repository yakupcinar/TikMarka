<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * Şifre sıfırlama bağlantısı. (4.6V)
 *
 * ★ `BrandMail`'den türüyor: müşteri "TıkMarka"dan değil ALIŞVERİŞ
 * YAPTIĞI MARKADAN posta almalı (2H-K3). Laravel'in hazır `ResetPassword`
 * bildirimi kullanılsaydı posta platform adıyla ve İngilizce iskeletle
 * giderdi — müşteri onu tanımaz, çöp kutusuna atardı.
 *
 * ⚠️ `ShouldQueue` (BrandMail'den): SMTP yavaşlığı formu bekletmemeli.
 * Ama bunun bir bedeli var — kuyruk işçisi çalışmıyorsa posta HİÇ
 * gitmez ve kullanıcı sebebini bilemez. Bu projede worker her zaman
 * ayakta (docker-compose) ve 2H-K1'de aynı karar verilmişti.
 */
class PasswordResetMail extends BrandMail
{
    /**
     * @param  string  $adres  imzasız ama JETON TAŞIYAN tam sıfırlama adresi
     * @param  int  $dakika  jetonun geçerlilik süresi
     */
    public function __construct(
        public readonly string $adres,
        public readonly int $dakika,
        public readonly bool $panel = false,
    ) {}

    protected function konu(): string
    {
        /*
        | ⚠️ Konu iki yüzeyde FARKLI: panel personeli ile müşteri aynı
        | e-posta adresini kullanıyor olabilir (aynı kişi hem müşteri hem
        | çalışan). Konu ayrımı olmasaydı hangi hesabın sıfırlandığını
        | anlayamazdı.
        */
        return $this->panel
            ? 'Panel şifrenizi sıfırlayın'
            : 'Şifrenizi sıfırlayın';
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.password-reset',
            with: $this->marka() + [
                'adres' => $this->adres,
                'dakika' => $this->dakika,
                'panel' => $this->panel,
            ],
        );
    }
}
