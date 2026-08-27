<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sepet satırı.
 *
 * ⚠️ Fiyat alanı YOK ve olmayacak — fiyat varyanttan CANLI okunuyor.
 * Kopyalansaydı marka fiyatı düşürdüğünde sepette eski fiyat kalır,
 * müşteri vitrinde 199 görüp sepette 249 öderdi.
 *
 * @property int $quantity
 */
class CartItem extends Model
{
    /** ⚠️ `cart_id` ve `variant_id` listede YOK — ilişki üzerinden konuyor. */
    protected $fillable = [
        'quantity',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    /**
     * Sepet satırının varyantı — SİLİNMİŞ OLANI DA GÖRÜR. (4.6AJ)
     *
     * ★ ÖLÇÜLEN KUSUR: marka ürünü panelden silince müşterinin sepeti
     * KİLİTLENİYORDU. Varyant yumuşak silindiği için ilişki `null`
     * dönüyor, ekran `value="{{ $satir->variant?->uuid }}"` ile BOŞ
     * bir alan basıyor ve müşteri "sil" düğmesine bastığında
     * *"variant uuid alanı zorunludur"* alıyordu. İkinci bariyer de
     * vardı: `satiriBul()` `whereHas('variant')` ile arıyor ve silinmiş
     * varyant o sorguya hiç girmiyordu.
     *
     * Yani müşteri o satırı sepetinden ÇIKARAMIYORDU bile — ürünü
     * silen marka, müşterinin sepetini çalışamaz hâle getiriyordu.
     *
     * ⚠️ PROJENİN KENDİ KURALI BUNU ZATEN SÖYLÜYOR (1E.6): bir kaydı
     * **KAPATAN** yol (kesinleştirme, iptal, iade — ve burada: sepetten
     * çıkarma) silinmişi de görmeli; **AÇAN** yol görmemeli.
     *
     * ⚠️ BU SATIR TEK BAŞINA TEHLİKELİ. Silinmiş varyant görünür olunca
     * `kullanilabilirMi()` ona "satılabilir" derse silinen ürün yeniden
     * satın alınabilir hâle gelir. O yüzden aşağıda `trashed()` kontrolü
     * AÇIKÇA yazılı ve kendi testi var.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        /*
        | ⚠️ `withTrashed()` dönüş tipini genelleştiriyor; Larastan
        | `ProductVariant`'ı kaybediyor. Açıklama şart, yoksa bu ilişkiyi
        | kullanan HER yerde "tanımsız özellik" uyarısı çıkıyor.
        */
        /** @var BelongsTo<ProductVariant, $this> */
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    /**
     * Bu satır hâlâ satın alınabilir mi? (1C-K2)
     *
     * Üç şey değişmiş olabilir: ürün arşivlendi · varyant kapatıldı ·
     * stok bitti. Üçünde de satır SİLİNMİYOR, işaretleniyor — kullanıcı
     * ne kaybettiğini görsün diye.
     */
    public function kullanilabilirMi(): bool
    {
        $varyant = $this->variant;

        if ($varyant === null) {
            return false;
        }

        /*
        | ★ SİLİNMİŞ VARYANT SATILAMAZ — ve bu kontrol 4.6AJ'de AÇIKÇA
        | yazıldı, daha önce ilişkinin kendisi silinmişi görmediği için
        | ÖRTÜLÜ olarak sağlanıyordu.
        |
        | ⚠️ İlişkiye `withTrashed()` eklenince o örtülü koruma KALKTI.
        | Bu satır olmasaydı marka bir ürünü silse bile sepetteki satır
        | "satılabilir" görünmeye devam eder, ödemeye geçiş onu geçirir
        | ve katalogdan kaldırılmış bir ürün satılırdı — hata vermeden.
        |
        | ⚠️ Ürün de yumuşak siliniyor (`ProductService::sil()` ikisini
        | birden siliyor); varyant üzerinden bakmak yetiyor ama ürünün
        | kendi durumu aşağıda ayrıca kontrol ediliyor.
        */
        if ($varyant->trashed()) {
            return false;
        }

        // ⚠️ Aynı tek kapı (1B-K8): 1D'de `stock - rezerve > 0` olunca
        // burası kendiliğinden doğru davranacak.
        return $varyant->satinAlinabilirMi()
            && $varyant->product?->status === ProductStatus::Active;
    }

    /** Stok yetiyor mu — adet bazında. */
    public function stokYetiyorMu(): bool
    {
        $varyant = $this->variant;

        // `kullanilabilirMi()` zaten varyantın varlığını doğruluyor, ama
        // statik analiz iki ayrı çağrı arasında bunu taşıyamıyor.
        return $this->kullanilabilirMi() && $varyant !== null && $varyant->stock >= $this->quantity;
    }

    /**
     * Satırda gösterilecek ürün adı — SİLİNMİŞ ÜRÜNÜNKİ DÂHİL. (4.6AJ)
     *
     * ★ Müşteri neyi sepetinden çıkardığını GÖRMELİ. Silinmiş satırda ad
     * çözülmezse ekranda yalnızca "Ürün" yazıyor ve müşteri hangi
     * kaydı sildiğini bilmiyor.
     *
     * ⚠️ `ProductVariant::product()` ilişkisi BİLEREK açılmadı.
     * O ilişki katalog sorgularının her yerinde kullanılıyor; toptan
     * `withTrashed()` eklemek, silinmiş ürünün vitrinde görünmesi gibi
     * çok daha geniş bir kapıyı sessizce açardı. Burada ihtiyaç DAR:
     * yalnızca sepet satırının adı.
     *
     * ⚠️ Ek sorgu YALNIZCA ölü satırda açılıyor: ürün ilişkisi normal
     * yoldan çözüldüyse o kullanılıyor. Sağlam satırlar ek yük almıyor.
     */
    public function urunAdi(): string
    {
        $varyant = $this->variant;

        if ($varyant === null) {
            return 'Ürün';
        }

        $urun = $varyant->product;

        if ($urun !== null) {
            return $urun->title;
        }

        return (string) (Product::withTrashed()
            ->whereKey($varyant->product_id)
            ->value('title') ?? 'Ürün');
    }
}
