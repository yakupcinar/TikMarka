<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * E-posta doğrulama bağlantısı. (4.6W)
 *
 * ★ `BrandMail`'den türüyor (2H-K3): posta marka adıyla gider.
 *
 * ⚠️ `ShouldQueue` (BrandMail'den): kayıt formunu SMTP beklemesin.
 * Bedeli 4.6V'dekiyle aynı — kuyruk işçisi durursa posta hiç gitmez.
 */
class EmailVerificationMail extends BrandMail
{
    /**
     * @param  string  $adres  İMZALI ve süreli doğrulama adresi
     * @param  int  $dakika  bağlantının geçerlilik süresi
     */
    public function __construct(
        public readonly string $adres,
        public readonly int $dakika,
    ) {}

    protected function konu(): string
    {
        return 'E-posta adresinizi doğrulayın';
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-verification',
            with: $this->marka() + [
                'adres' => $this->adres,
                'dakika' => $this->dakika,
            ],
        );
    }
}
