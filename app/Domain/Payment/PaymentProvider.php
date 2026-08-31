<?php

namespace App\Domain\Payment;

interface PaymentProvider
{
    public function ad(): string;

    /**
     * @return list<string>
     */
    public function gerekliAnahtarlar(): array;

    public function baslat(PaymentRequest $istek): PaymentInitiation;

    /**
     * Dönüş isteğinden sağlayıcı referansını çıkarır. (1E.5)
     *
     * @param  array<string, mixed>  $veri  sorgu + gövde birleşimi
     */
    public function donusReferansi(array $veri): ?string;

    /**
     * İmzanın taşındığı HTTP başlık adları — ÖNCELİK SIRASIYLA.
     *
     * @return list<string>
     */
    public function imzaBasliklari(): array;

    /**
     * Webhook imzasını doğrular.
     *
     * @param  array<string, mixed>  $yuk
     */
    public function webhookuDogrula(array $yuk, ?string $imza): bool;

    /**
     * Doğrulanmış webhook yükünü bizim dilimize çevirir.
     *
     * @param  array<string, mixed>  $yuk
     */
    public function webhookuCoz(array $yuk): PaymentOutcome;
}
