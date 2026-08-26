<script setup>
/*
 Müşteri ayrıntısı. (4.6AC)

 ⚠️ HASSAS KOLON HİÇ GELMİYOR: `password` sunucuda sorguya bile
 girmiyor (`CustomerInsight::KOLONLAR`). Modelin `$hidden` listesine
 güvenmek yetmez — 4F'de marka dökümüne bcrypt hash'leri tam böyle
 girmişti.

 ⚠️ Başarısız ödemelerde SAĞLAYICININ RET GEREKÇESİ YOK: banka "limit
 yetersiz" ya da "fraud şüphesi" diyebiliyor ve bu müşterinin kartına
 dair bir bilgi. Vitrinde de aynı sebeple gizleniyor (4.5R).
*/
import { Head, Link } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'
import { tarih } from '../../Yardimcilar/tarih'

defineProps({
  musteri: Object,
  ozet: Object,
  siparisler: Array,
  favoriler: Array,
  basarisizOdemeler: Array,
})

const odemeAdi = {
  paid: 'Ödendi', pending: 'Bekliyor', failed: 'Başarısız',
  cancelled: 'İptal', refunded: 'İade', partially_refunded: 'Kısmi iade',
}

function para(v) { return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL' }
</script>

<template>
  <Head :title="musteri.ad" />

  <PanelDuzeni>
    <Link href="/yonetim/musteriler" class="text-sm text-soluk hover:underline">← Müşteriler</Link>

    <h1 class="mt-2 text-2xl font-bold">{{ musteri.ad }}</h1>

    <p class="text-metin-2">
      {{ musteri.eposta }}
      <span v-if="!musteri.dogrulanmis" class="ml-2 rounded-sm bg-uyari-zemin px-2 py-0.5 text-xs text-uyari">
        e-posta doğrulanmadı
      </span>
      <span v-if="musteri.telefon" class="ml-2">· {{ musteri.telefon }}</span>
    </p>

    <p class="mt-1 text-sm text-soluk">
      Kayıt: {{ tarih(musteri.kayit) }} ·
      Pazarlama izni: {{ musteri.pazarlama ? 'var' : 'yok' }}
    </p>

    <!-- ── ÖZET ────────────────────────────────────────────────────── -->
    <div class="mt-6 grid grid-cols-1 gap-4 min-[380px]:grid-cols-2 sm:grid-cols-4">
      <div class="rounded-xl border border-kenar p-4">
        <div class="text-2xl font-bold">{{ ozet.siparis }}</div>
        <!-- ⚠️ "TAMAMLANAN" yazıyor ve bu canlı doğrulamada bulundu: özet
             yalnızca ödenmiş siparişi sayıyor ama aşağıdaki liste bekleyen
             ve iptal olanları da gösteriyor. "Sipariş" yazsaydı marka
             sayılarla listeyi karşılaştırıp çelişki sanardı. -->
        <div class="text-sm text-soluk">tamamlanan sipariş</div>
      </div>

      <div class="rounded-xl border border-kenar p-4">
        <div class="text-2xl font-bold">{{ para(ozet.harcama) }}</div>
        <!-- ⚠️ SINIR AÇIKÇA YAZILIYOR: iade edilen kısım düşülmüyor.
             Yazılmasaydı marka bu sayıyı net ciro sanardı. -->
        <div class="text-sm text-soluk">toplam (iadeler düşülmedi)</div>
      </div>

      <div class="rounded-xl border border-kenar p-4">
        <div class="text-2xl font-bold">{{ ozet.favori }}</div>
        <div class="text-sm text-soluk">favori</div>
      </div>

      <div class="rounded-xl border border-kenar p-4">
        <div class="text-2xl font-bold" :class="ozet.basarisiz > 0 ? 'text-tehlike' : ''">{{ ozet.basarisiz }}</div>
        <div class="text-sm text-soluk">başarısız ödeme</div>
      </div>
    </div>

    <!-- ── SİPARİŞLER ──────────────────────────────────────────────── -->
    <h2 class="mt-8 text-lg font-semibold">Siparişler</h2>

    <!-- ⚠️ Liste TÜM durumları gösteriyor: destek ekibi "ödemem geçmedi"
         diyen müşteriye ancak bekleyen ve iptal olanları da görerek yardım
         edebiliyor. Yukarıdaki sayı ise yalnızca tamamlananları sayıyor. -->
    <p v-if="siparisler.length > 0" class="text-sm text-soluk">
      Son {{ siparisler.length }} sipariş — bekleyen ve iptal edilenler dâhil.
    </p>

    <p v-if="siparisler.length === 0" class="text-soluk">Bu müşterinin siparişi yok.</p>

    <div class="overflow-x-auto" v-else>
      <table class="min-w-[42rem] mt-2 w-full text-sm">
        <tbody>
          <tr v-for="s in siparisler" :key="s.uuid" class="border-t border-kenar-soft">
            <td class="py-3">
              <Link :href="`/yonetim/siparisler/${s.uuid}`" class="font-medium hover:underline">{{ s.numara }}</Link>
              <!-- ⚠️ Ürün adları SİPARİŞ SATIRINDAN (kopya): ürün silinse
                   bile müşterinin ne aldığı görünüyor (1D). -->
              <div class="text-xs text-soluk">{{ s.urunler.join(' · ') }}</div>
            </td>
            <td class="text-soluk">{{ tarih(s.tarih) }}</td>
            <td>{{ odemeAdi[s.odeme] ?? s.odeme }}</td>
            <td class="text-right">{{ s.adet }} adet</td>
            <td class="text-right font-medium">{{ para(s.tutar) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── FAVORİLER ───────────────────────────────────────────────── -->
    <h2 class="mt-8 text-lg font-semibold">Favoriler</h2>

    <p v-if="favoriler.length === 0" class="text-soluk">Favori ürünü yok.</p>

    <ul v-else class="mt-2 space-y-1 text-sm">
      <li v-for="(f, i) in favoriler" :key="i" class="flex gap-3 border-t border-kenar-soft py-2">
        <span class="flex-1">{{ f.urun }}</span>
        <span class="text-soluk">{{ tarih(f.tarih) }}</span>
      </li>
    </ul>

    <!-- ── BAŞARISIZ ÖDEMELER ──────────────────────────────────────── -->
    <h2 class="mt-8 text-lg font-semibold">Başarısız ödeme denemeleri</h2>

    <p v-if="basarisizOdemeler.length === 0" class="text-soluk">Başarısız ödeme denemesi yok.</p>

    <ul v-else class="mt-2 space-y-1 text-sm">
      <li v-for="(o, i) in basarisizOdemeler" :key="i" class="flex gap-3 border-t border-kenar-soft py-2">
        <span class="flex-1">{{ o.numara }}</span>
        <span>{{ para(o.tutar) }}</span>
        <span class="text-soluk">{{ o.saglayici }}</span>
        <span class="text-soluk">{{ tarih(o.tarih) }}</span>
      </li>
    </ul>
  </PanelDuzeni>
</template>
