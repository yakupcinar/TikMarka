<?php

namespace App\Domain\Privacy;

use App\Models\Address;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Müşterinin kendi verisinin dökümü. (2G-K5)
 *
 * ⚠️ Makine okunur — Magento ve WooCommerce de öyle veriyor.
 *
 * ⚠️ Yalnızca KENDİ verisi. Sorgular müşteriye daraltılmış (1A.5); başka
 * müşterinin satırı sonuç kümesine hiç girmiyor.
 */
class DataExporter
{
    /** @return array<string, mixed> */
    public function musteriDokumü(Customer $musteri): array
    {
        return [
            'olusturulma' => now()->toIso8601String(),

            'hesap' => [
                'ad' => $musteri->name,
                'eposta' => $musteri->email,
                'telefon' => $musteri->phone,
                'pazarlama_izni' => $musteri->accepts_marketing,
                'kayit_tarihi' => $musteri->created_at?->toIso8601String(),
            ],

            'adresler' => $musteri->addresses->map(fn (Address $a): array => [
                'baslik' => $a->title,
                'ad_soyad' => $a->full_name,
                'telefon' => $a->phone,
                'sehir' => $a->city,
                'ilce' => $a->district,
                'mahalle' => $a->neighborhood,
                'adres' => trim($a->line1.' '.($a->line2 ?? '')),
                'posta_kodu' => $a->postal_code,
            ])->all(),

            /*
            | ★ FAVORİLER (4.6D). KVKK "hangi verim var" sorusunu
            | cevaplıyor; favori de müşteri başına tutulan bir veri.
            |
            | ⚠️ Silinmiş ürünün favorisi de YAZILIYOR (`listele`'nin
            | tersine): burada soru "ne gösterelim" değil "elimizde ne
            | var". Gizlemek, veriyi eksik bildirmek olurdu.
            */
            'favoriler' => Favorite::where('customer_id', $musteri->id)
                ->with('product')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Favorite $f): array => [
                    'urun' => $f->product?->title,
                    'eklenme' => $f->created_at?->toIso8601String(),
                ])->all(),

            'siparisler' => Order::where('customer_id', $musteri->id)
                ->with('items')
                ->get()
                ->map(fn (Order $s): array => [
                    'numara' => $s->order_number,
                    'tarih' => $s->placed_at?->toIso8601String(),
                    'odeme_durumu' => $s->payment_status->value,
                    'sevkiyat_durumu' => $s->fulfillment_status->value,
                    'toplam' => $s->grand_total,
                    'kdv' => $s->tax_total,
                    'teslimat_adresi' => [
                        'ad_soyad' => $s->shipping_full_name,
                        'telefon' => $s->shipping_phone,
                        'sehir' => $s->shipping_city,
                        'ilce' => $s->shipping_district,
                        'adres' => $s->shipping_line1,
                    ],
                    'satirlar' => $s->items->map(fn (OrderItem $k): array => [
                        'urun' => $k->product_title,
                        'sku' => $k->sku,
                        'adet' => $k->quantity,
                        'birim_fiyat' => $k->unit_price,
                        'satir_toplami' => $k->line_total,
                    ])->all(),
                ])
                ->values()
                ->all(),

            /*
            | ★ DAVRANIŞ KAYITLARI (4.6F).
            |
            | ⚠️ KVKK'nın sorusu "ne gösterelim" değil "ELİMİZDE NE VAR".
            | Bu kayıtlar müşteriye bağlı olduğu sürece onun verisi ve
            | dökümde yer almak zorunda — 4.6D'de favori için verilen
            | kararın aynısı.
            |
            | ⚠️ `payload` OLDUĞU GİBİ yazılıyor. İçinde kişisel veri
            | olmaması gerekiyor (1F-K4) ama döküm "bizde ne var"ı
            | anlatıyor; süzülseydi kural ihlal edilmiş olsa bile müşteri
            | bunu göremezdi. Yani bu alan aynı zamanda 1F-K4'ün
            | DENETİMİ.
            |
            | ⚠️ Sınır KONMUYOR. Sipariş dökümünde de yok; eksik döküm
            | eksik cevaptır. Hacim sorun olursa çözüm kırpmak değil
            | dökümü akışa çevirmek.
            */
            'davranis_kayitlari' => Event::where('customer_id', $musteri->id)
                ->orderBy('occurred_at')
                ->get()
                ->map(fn (Event $o): array => [
                    'tur' => $o->type->value,
                    'zaman' => $o->occurred_at?->toIso8601String(),
                    'ayrinti' => $o->payload,
                ])
                ->values()
                ->all(),
        ];
    }
}
