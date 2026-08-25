<?php

namespace App\Domain\Catalog;

/**
 * Görsel PİKSEL olarak çok büyük — dosya boyutundan bağımsız. (4.6AA)
 *
 * ⚠️ Dosya boyutu sınırı BU KORUMANIN YERİNE GEÇMEZ. Sıkıştırma bombası
 * denen şey tam olarak budur: birkaç yüz kilobaytlık bir PNG, açıldığında
 * gigabaytlarca bellek isteyebilir. GD görseli her zaman AÇARAK işliyor,
 * yani piksel sayısı dosya boyutundan önce gelen sınırdır.
 */
class ImageTooLargeException extends CatalogRuleException
{
    public function __construct(
        public readonly int $genislik,
        public readonly int $yukseklik,
        public readonly int $enFazlaPiksel,
    ) {
        parent::__construct(sprintf(
            'Görsel çok büyük: %d×%d piksel. En fazla %.0f megapiksel olabilir.',
            $genislik,
            $yukseklik,
            $enFazlaPiksel / 1_000_000,
        ));
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['image' => [$this->getMessage()]];
    }
}
