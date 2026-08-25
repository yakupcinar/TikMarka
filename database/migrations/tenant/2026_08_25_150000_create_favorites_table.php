<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Müşteri favorileri. (4.6D)
 *
 * ⚠️ MARKA ŞEMASINA (`--path=database/migrations/tenant`): favori
 * müşteriye ve ürüne bağlı, ikisi de marka şemasında yaşıyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $tablo) {
            $tablo->id();

            /*
            | ⚠️ `cascadeOnDelete` İKİSİNDE DE.
            |
            | Müşteri gerçekten silinirse (anonimleştirme değil, silme)
            | favorileri de gitmeli — sahipsiz favori kimseye ait olmayan
            | bir kişisel veridir.
            |
            | ⚠️ Ürün tarafında da aynısı, ama pratikte devreye GİRMİYOR:
            | ürün yumuşak siliniyor (`SoftDeletes`), yani satır duruyor.
            | Kısıt yine de doğru yerde — bir gün gerçekten silinirse
            | favori öksüz kalmasın.
            */
            $tablo->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $tablo->foreignId('product_id')->constrained()->cascadeOnDelete();

            /*
            | ⚠️ AYNI ÜRÜN İKİ KEZ FAVORİLENEMEZ. Kısıt olmasaydı iki
            | sekmeden art arda basan müşteri iki satır açar ve liste aynı
            | ürünü iki kez gösterirdi.
            |
            | ⚠️ Yumuşak silme YOK, o yüzden kısmi indekse de gerek yok
            | (4.6X.1'deki ayrımın tersi): favoriden çıkarmak gerçekten
            | silmek demek, "geçmişi" olan bir kayıt değil.
            */
            $tablo->unique(['customer_id', 'product_id']);

            // ⚠️ `timestampsTz()` — saat dilimi taşımayan damga üretmiyor.
            $tablo->timestampsTz();

            /*
            | ⚠️ Liste sorgusu `customer_id` + tarihe göre çalışıyor;
            | benzersiz kısıt `customer_id`'yi zaten önekliyor ama sıralama
            | için ayrı indeks anlamlı.
            */
            $tablo->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
