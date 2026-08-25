<?php

namespace App\Models;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Müşterinin favorilediği ürün. (4.6D)
 *
 * ⚠️ `$fillable` BOŞ ve bu bilinçli: bir favorinin iki alanı da SAHİPLİK
 * bilgisi (`customer_id`, `product_id`). Dışarıdan gelen veriyle
 * doldurulabilseydi müşteri başkasının adına favori ekleyebilirdi
 * (1A.5 deseni).
 *
 * ⚠️ `uuid` YOK: favori dışarıdan adreslenmiyor. Ekleme/çıkarma ÜRÜN
 * slug'ı üzerinden yapılıyor, yani favorinin kendi kimliğine hiç gerek
 * duyulmuyor — olsaydı korunması gereken fazladan bir yüzey olurdu.
 */
class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
