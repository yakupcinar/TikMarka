<script setup>
/* Sipariş listesi. (4E) — `izin:order.view` arkasında. */
import { Head, Link, router } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'
import { tarih } from '../../Yardimcilar/tarih'

const props = defineProps({ siparisler: Object, durum: String })

const odemeAdi = { paid: 'Ödendi', pending: 'Bekliyor', failed: 'Başarısız', cancelled: 'İptal', refunded: 'İade' }
const odemeRengi = {
  paid: 'bg-basari-zemin text-basari',
  pending: 'bg-uyari-zemin text-uyari',
  failed: 'bg-tehlike-zemin text-tehlike',
  cancelled: 'bg-yuzey-3 text-metin-2',
  refunded: 'bg-bilgi-zemin text-bilgi',
}
const kargoAdi = { unfulfilled: 'Bekliyor', partial: 'Kısmi', fulfilled: 'Tamam', cancelled: 'İptal' }

function suz(deger) {
  router.get('/yonetim/siparisler', { durum: deger || undefined }, { preserveState: true })
}

function para(v) { return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL' }
</script>

<template>
  <Head title="Siparişler" />

  <PanelDuzeni>
    <div class="flex flex-wrap items-center gap-4 mb-6">
      <h1 class="text-2xl font-bold">Siparişler</h1>

      <select class="ml-auto rounded-lg border border-kenar-kontrol px-3 py-2 text-sm" :value="durum ?? ''" @change="suz($event.target.value)">
        <option value="">Tüm kargo durumları</option>
        <option value="unfulfilled">Bekleyen</option>
        <option value="partial">Kısmi</option>
        <option value="fulfilled">Tamamlanan</option>
      </select>
    </div>

    <div v-if="siparisler.data.length === 0" class="rounded-xl bg-yuzey border border-kenar p-10 text-center text-metin-2 shadow-kart">
      Henüz sipariş yok.
    </div>

    <div class="overflow-x-auto" v-else>
      <table class="min-w-[42rem] w-full bg-yuzey rounded-xl border border-kenar overflow-hidden shadow-kart">
        <thead class="bg-zemin text-left text-xs font-semibold tracking-wide uppercase text-soluk">
          <tr>
            <th class="p-3">Sipariş</th><th class="p-3">Tarih</th><th class="p-3">Ödeme</th>
            <th class="p-3">Kargo</th><th class="p-3">Tutar</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in siparisler.data" :key="s.uuid" class="border-t border-kenar-soft">
            <td class="p-3">
              <Link :href="`/yonetim/siparisler/${s.uuid}`" class="text-base font-medium hover:text-vurgu-metin">
                {{ s.order_number }}
              </Link>
              <!-- ⚠️ STOK AÇIĞI LİSTEDE görünüyor: yalnızca ayrıntıda olsaydı
                   marka onu ancak siparişi açınca fark ederdi. -->
              <div v-if="s.stock_shortfall" class="text-xs text-tehlike">⚠ stok açığı</div>
              <div class="text-xs text-soluk">{{ s.email }}</div>
            </td>
            <td class="p-3 text-sm tabular-nums">{{ tarih(s.placed_at) }}</td>
            <td class="p-3">
              <span class="rounded-full px-2 py-0.5 text-xs" :class="odemeRengi[s.payment_status]">
                {{ odemeAdi[s.payment_status] ?? s.payment_status }}
              </span>
            </td>
            <td class="p-3 text-sm">{{ kargoAdi[s.fulfillment_status] ?? s.fulfillment_status }}</td>
            <td class="p-3 text-base font-medium tabular-nums">{{ para(s.grand_total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="siparisler.links.length > 3" class="mt-4 flex flex-wrap gap-1 text-sm">
      <Link v-for="b in siparisler.links" :key="b.label" :href="b.url ?? ''"
            class="rounded-lg border border-kenar-kontrol px-3 py-1 bg-yuzey"
            :class="{ 'bg-vurgu text-white border-vurgu': b.active, 'opacity-40 pointer-events-none': !b.url }"
            v-html="b.label" />
    </div>
  </PanelDuzeni>
</template>
