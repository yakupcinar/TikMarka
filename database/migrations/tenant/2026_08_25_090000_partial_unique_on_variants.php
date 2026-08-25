<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Varyant benzersizliği artık YALNIZCA CANLI satırlar için. (4.6X)
 *
 * ★ SORUN ÖLÇÜLDÜ. `sku` ve `(product_id, options)` kısıtları
 * `deleted_at`'e bakmıyordu; yumuşak silinen varyant kimliğini SONSUZA
 * KADAR işgal ediyordu. Marka bir varyantı silip aynı SKU ile yenisini
 * açmak istediğinde ham `UniqueConstraintViolationException` alıyordu —
 * gerçek kullanımda yaşandı.
 *
 * ⚠️ Projenin kendi kuralı bu yönü söylüyor: "bir kaydı KAPATAN yol
 * silinmişi de görmeli; AÇAN yol görmemeli". Varyant açmak bir AÇAN
 * yoldur, yani silinmişi görmemeli.
 *
 * ⚠️ SERBEST BIRAKMAK GÜVENLİ Mİ — ölçüldü, evet:
 *   · Sipariş satırları SKU'yu METİN olarak kopyalıyor (bir fotoğraf),
 *     yani geçmiş bu değişiklikten etkilenmiyor.
 *   · Kod tabanında SKU ile kayıt arayan TEK BİR yer yok
 *     (`where('sku'…)` sıfır sonuç) — yani iki satırın (biri silinmiş)
 *     aynı SKU'yu taşıması hiçbir sorguyu yanıltmıyor.
 *   · Varyantı geri alan (`restore()`) bir yol da yok.
 *
 * ⚠️ Kısıt GEVŞEMİYOR, DARALIYOR: canlı satırlar arasında benzersizlik
 * aynen duruyor. Bu yüzden mevcut veriden hiçbiri yeni indeksi ihlal
 * edemez.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | ⚠️ Laravel'in şema kurucusu KISMİ indeks yazamıyor; ham SQL
        | gerekiyor. `search_path` kiracıya kurulu olduğu için şema adı
        | yazılmıyor (M-2.1).
        */
        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT product_variants_sku_unique');
        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT product_variants_product_id_options_unique');

        DB::statement('CREATE UNIQUE INDEX product_variants_sku_unique
                       ON product_variants (sku) WHERE deleted_at IS NULL');

        DB::statement('CREATE UNIQUE INDEX product_variants_product_id_options_unique
                       ON product_variants (product_id, options) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX product_variants_sku_unique');
        DB::statement('DROP INDEX product_variants_product_id_options_unique');

        /*
        | ⚠️ Geri alma BAŞARISIZ OLABİLİR ve bu doğru: kısmi indeks
        | yürürlükteyken silinmiş bir satırla aynı SKU'ya sahip canlı
        | satır oluşmuş olabilir. Eski kısıt onu kabul etmez. Sessizce
        | veri düşürmektense migration'ın patlaması yeğdir.
        */
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_sku_unique UNIQUE (sku)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_product_id_options_unique UNIQUE (product_id, options)');
    }
};
