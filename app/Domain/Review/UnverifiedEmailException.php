<?php

namespace App\Domain\Review;

use DomainException;

/**
 * Doğrulanmamış e-posta ile yorum yazma denemesi. (4.6W)
 *
 * ⚠️ `NotPurchasedException`'dan AYRI tutuldu: ikisi de 403 döndürse de
 * müşterinin yapması gereken şey farklı. "Satın almadınız" bir çıkmaz;
 * "adresinizi doğrulayın" tek tıkla çözülebilir bir adım. Tek istisnaya
 * bağlansaydı ekran müşteriye yanlış çözümü gösterirdi.
 */
class UnverifiedEmailException extends DomainException {}
