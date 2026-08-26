<script setup>
/*
 | Ürün listesi. (4D)
 |
 | ⚠️ Bu sayfa `izin:product.write` ARKASINDA. Menüde gizlemek bir
 | kolaylıktı; adresi elle yazan yetkisiz personel sunucudan 403 alıyor.
 */
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'
import Sayfalama from '../../Components/Sayfalama.vue'

const props = defineProps({
  urunler: Object,
  arama: String,
})

const kelime = ref(props.arama ?? '')

function ara() {
  router.get('/yonetim/urunler', { q: kelime.value || undefined }, { preserveState: true })
}

const durumRengi = {
  active: 'bg-basari-zemin text-basari',
  draft: 'bg-yuzey-3 text-metin-2',
  archived: 'bg-uyari-zemin text-uyari',
}

const durumAdi = { active: 'Yayında', draft: 'Taslak', archived: 'Arşiv' }

function para(deger) {
  if (deger === null || deger === undefined) return '—'
  return Number(deger).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL'
}
</script>

<template>
  <Head title="Ürünler" />

  <PanelDuzeni>
    <div class="flex flex-wrap items-center gap-4 mb-6">
      <h1 class="text-2xl font-bold">Ürünler</h1>

      <form class="ml-auto flex gap-2" @submit.prevent="ara">
        <input
          v-model="kelime"
          type="search"
          placeholder="Ürün ara"
          class="rounded-lg border border-kenar-kontrol px-3 py-2 text-sm"
        >
        <button type="submit" class="rounded-lg border border-kenar-kontrol px-3 py-2 text-sm bg-yuzey">Ara</button>
      </form>

      <Link
        href="/yonetim/urunler/yeni"
        class="rounded-lg bg-vurgu text-white px-4 py-2 text-sm font-semibold"
      >Yeni ürün</Link>
    </div>

    <!-- ⚠️ Boş liste "hata" gibi görünmemeli: yeni marka için NORMAL durum. -->
    <div v-if="urunler.data.length === 0" class="rounded-xl bg-yuzey border border-kenar p-10 text-center text-metin-2 shadow-kart">
      <p v-if="arama">“{{ arama }}” için ürün bulunamadı.</p>
      <p v-else>Henüz ürün yok. İlk ürününüzü ekleyin.</p>
    </div>

    <div class="overflow-x-auto" v-else>
      <table class="min-w-[42rem] w-full bg-yuzey rounded-xl border border-kenar overflow-hidden shadow-kart">
        <thead class="bg-zemin text-left text-xs font-semibold tracking-wide uppercase text-soluk">
          <tr>
            <th class="p-3">Ürün</th>
            <th class="p-3">Durum</th>
            <th class="p-3">Varyant</th>
            <th class="p-3">Stok</th>
            <th class="p-3">Fiyat</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="urun in urunler.data" :key="urun.uuid" class="border-t border-kenar-soft">
            <td class="p-3">
              <Link :href="`/yonetim/urunler/${urun.uuid}`" class="text-base font-medium hover:text-vurgu-metin">
                {{ urun.title }}
              </Link>
            </td>
            <td class="p-3">
              <span class="rounded-full px-2 py-0.5 text-xs" :class="durumRengi[urun.status]">
                {{ durumAdi[urun.status] }}
              </span>
            </td>
            <!-- ⚠️ Varyantsız ürün SATILAMAZ; sayı sıfırsa uyarı veriyoruz. -->
            <td class="p-3 text-sm">
              <span v-if="urun.variant_count === 0" class="text-uyari">yok — satılamaz</span>
              <span v-else>{{ urun.variant_count }}</span>
            </td>
            <td class="p-3 text-sm tabular-nums">{{ urun.stock }}</td>
            <td class="p-3 text-base font-medium tabular-nums">{{ para(urun.min_price) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Sayfalama :baglantilar="urunler.links" />
  </PanelDuzeni>
</template>
