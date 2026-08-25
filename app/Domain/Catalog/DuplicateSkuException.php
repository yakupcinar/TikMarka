<?php

namespace App\Domain\Catalog;

/**
 * Aynı SKU ile ikinci bir varyant açılmak isteniyor. (4.6X · 4.6X.1)
 *
 * ★ SKU marka genelinde benzersiz ve SİLİNENLER DE SAYILIYOR: kod
 * markanın dış dünyayla ortak dili (depo, kargo, muhasebe, pazaryeri).
 * Yeniden kullanılsaydı aynı kod zaman içinde iki farklı fiziksel ürüne
 * işaret ederdi — yani eski ürünü yok saymış olurduk.
 *
 * ⚠️ MESAJ İKİ DURUMU AYIRIYOR ve bu kozmetik değil. Çakışma silinmiş bir
 * varyantlaysa marka o SKU'yu ekranda ARAYAMAZ — kayıt katalogda
 * görünmüyor. "Başka bir varyantta kullanılıyor" denseydi marka olmayan
 * bir şeyi arar, bulamaz ve hatayı sistem arızası sanardı. Gerçek
 * kullanımda tam bu yaşandı.
 */
class DuplicateSkuException extends CatalogRuleException
{
    public function __construct(
        public readonly string $sku,
        public readonly bool $silinmisVaryantta = false,
    ) {
        parent::__construct($silinmisVaryantta
            ? "Bu stok kodu (SKU) silinmiş bir varyanta ait: {$sku}. Stok kodları geçmişe dönük olarak korunuyor — silinen bir ürünün kodu yeniden kullanılamaz. Farklı bir kod seçin."
            : "Bu stok kodu (SKU) başka bir varyantta kullanılıyor: {$sku}");
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['sku' => [$this->getMessage()]];
    }
}
