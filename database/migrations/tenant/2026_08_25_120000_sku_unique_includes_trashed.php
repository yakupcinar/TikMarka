<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SKU benzersizliği SİLİNMİŞLERİ DE KAPSIYOR. (4.6X.1)
 *
 * ★ 4.6X'te SKU kısmi indekse çevrilmişti (silinen varyantın kodu serbest
 * kalıyordu). O karar KULLANICI TARAFINDAN GERİ ALINDI ve gerekçesi
 * benimkinden güçlü:
 *
 * > SKU markanın DIŞ DÜNYAYLA ORTAK DİLİ — depo, kargo, muhasebe,
 * > pazaryeri entegrasyonu hep onu konuşuyor. Aynı kodun zaman içinde iki
 * > farklı fiziksel ürüne işaret etmesi "bu neydi" sorusunu cevapsız
 * > bırakır. Kodu yeniden kullanmak, eski ürünü yok saymak demek.
 *
 * ⚠️ Benim 4.6X'teki gerekçem "sipariş satırları SKU'yu metin kopyalıyor,
 * geçmiş bozulmaz"dı. Bu DOĞRU ama YETERSİZ: geçmişin bozulmaması,
 * geçmişin okunabilir kalması anlamına gelmiyor. Marka dışarıdaki bir
 * sistemde SKU'yu ararsa iki farklı ürün bulur.
 *
 * ⚠️ `(product_id, options)` KISMİ KALIYOR — bilerek. O bir dış kimlik
 * değil, "hangi birleşim" sorusunun cevabı. Sonsuza kadar rezerve
 * edilseydi marka "Kırmızı / M" varyantını silip bir daha ASLA
 * açamazdı; bu meşru işi engellemek olurdu.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | ⚠️ Kısıt SIKILAŞIYOR: silinmiş bir satırla aynı SKU'ya sahip
        | canlı satır varsa bu ifade PATLAR — ve patlaması doğru. Sessizce
        | geçilseydi kural yürürlükte sanılır, veri ihlalli kalırdı.
        */
        DB::statement('DROP INDEX product_variants_sku_unique');

        DB::statement('ALTER TABLE product_variants
                       ADD CONSTRAINT product_variants_sku_unique UNIQUE (sku)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT product_variants_sku_unique');

        DB::statement('CREATE UNIQUE INDEX product_variants_sku_unique
                       ON product_variants (sku) WHERE deleted_at IS NULL');
    }
};
