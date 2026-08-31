<?php

namespace App\Domain\Payment;

use RuntimeException;

class PaymentProviderException extends RuntimeException
{
    /** @param  array<string, mixed>  $ayrintilar */
    public function __construct(
        public readonly string $saglayici,
        string $mesaj,
        public readonly array $ayrintilar = [],
    ) {
        parent::__construct("[{$saglayici}] {$mesaj}");
    }
}
