<?php

namespace App\Domain\Catalog;

/**
 * Aynı SKU ile ikinci bir CANLI varyant açılmak isteniyor. (4.6X)
 *
 * ★ SKU marka genelinde benzersiz ve bu doğru: stok kodu deponun,
 * kargonun ve muhasebenin ortak dili. İki varyant aynı kodu taşısaydı
 * "hangi ürün sevk edildi" sorusunun cevabı olmazdı.
 *
 * ⚠️ Ama kısıt tek başına YETMİYORDU — `DuplicateVariantException`'ın
 * (4.5L) yaşadığının aynısı: Domain'de hiçbir kontrol yoktu, panelde ham
 * `UniqueConstraintViolationException` görünüyordu. Gerçek kullanımda
 * yakalandı.
 *
 * ⚠️ Mesaj "başka bir varyantta kullanılıyor" diyor, "silinmiş bir
 * varyantta" DEMİYOR: 4.6X'ten sonra silinmiş varyantın SKU'su serbest,
 * yani çakışma yalnızca CANLI bir varyantla olabilir.
 */
class DuplicateSkuException extends CatalogRuleException
{
    public function __construct(public readonly string $sku)
    {
        parent::__construct("Bu stok kodu (SKU) başka bir varyantta kullanılıyor: {$sku}");
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['sku' => [$this->getMessage()]];
    }
}
