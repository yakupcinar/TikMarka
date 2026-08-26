<script setup>
/* İade talepleri. (4E) — görmek `order.view`, karar vermek `order.refund`. */
import { Head, Link } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'
import { tarih } from '../../Yardimcilar/tarih'

defineProps({ talepler: Object })

const durumAdi = {
  requested: 'Talep edildi', approved: 'Onaylandı', rejected: 'Reddedildi',
  received: 'Teslim alındı', completed: 'Tamamlandı',
}
const durumRengi = {
  requested: 'bg-uyari-zemin text-uyari', approved: 'bg-bilgi-zemin text-bilgi',
  rejected: 'bg-yuzey-3 text-metin-2', received: 'bg-bilgi-zemin text-bilgi',
  completed: 'bg-basari-zemin text-basari',
}
</script>

<template>
  <Head title="İadeler" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-6">İade talepleri</h1>

    <div v-if="talepler.data.length === 0" class="rounded-xl bg-yuzey border border-kenar p-10 text-center text-metin-2">
      İade talebi yok.
    </div>

    <div class="overflow-x-auto" v-else>
      <table class="min-w-[42rem] w-full bg-yuzey rounded-xl border border-kenar overflow-hidden">
        <thead class="bg-zemin text-left text-sm text-metin-2">
          <tr><th class="p-3">Sipariş</th><th class="p-3">Tür</th><th class="p-3">Ürün</th><th class="p-3">Durum</th><th class="p-3">Tarih</th></tr>
        </thead>
        <tbody>
          <tr v-for="t in talepler.data" :key="t.uuid" class="border-t border-kenar-soft">
            <td class="p-3">
              <Link :href="`/yonetim/iadeler/${t.uuid}`" class="font-medium hover:text-vurgu-metin">{{ t.order_number }}</Link>
            </td>
            <!-- ⚠️ CAYMA mı AYIPLI mı: kargo bedelinin geri verilip
                 verilmeyeceğini bu belirliyor (2B-K1). -->
            <td class="p-3 text-sm">{{ t.is_withdrawal ? 'Cayma' : 'Ayıplı ürün' }}</td>
            <td class="p-3 text-sm">{{ t.item_count }} adet</td>
            <td class="p-3">
              <span class="rounded-full px-2 py-0.5 text-xs" :class="durumRengi[t.status]">{{ durumAdi[t.status] ?? t.status }}</span>
            </td>
            <td class="p-3 text-sm">{{ tarih(t.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </PanelDuzeni>
</template>
