<script setup>
/*
 Müşteri listesi. (4.6AC) — `izin:customer.view` arkasında.

 ⚠️ Bu izin Faz 1'den beri TANIMLIYDI ve üç role verilmişti ama hiçbir
 rota onu kullanmıyordu; sekme gelene kadar ölüydü (4.6S'deki
 `product.view` kusurunun aynısı).

 ⚠️ SALT OKUNUR: yazma ucu yok. Müşteri verisini değiştirmek KVKK
 tarafında ayrı bir sorumluluk (2G) ve buraya sızmamalı.
*/
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'
import { tarih } from '../../Yardimcilar/tarih'

const props = defineProps({ musteriler: Object, ara: String })

const arama = ref(props.ara ?? '')

function suz() {
  router.get('/yonetim/musteriler', { ara: arama.value || undefined }, { preserveState: true })
}

function para(v) { return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL' }
</script>

<template>
  <Head title="Müşteriler" />

  <PanelDuzeni>
    <div class="flex items-center gap-4 mb-6">
      <h1 class="text-2xl font-bold">Müşteriler</h1>

      <form class="ml-auto flex gap-2" @submit.prevent="suz">
        <!-- ⚠️ Arama SOL EŞLEŞME (sunucuda): "iş" araması "Tişört"ü
             getirmemeli — 4.5P ve 4.5S'deki kararın aynısı. -->
        <input
          v-model="arama"
          type="search"
          placeholder="Ad veya e-posta"
          class="rounded-lg border border-stone-300 px-3 py-2 text-sm"
        >
        <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2 text-sm text-white">Ara</button>
      </form>
    </div>

    <!-- ⚠️ Boş liste bir HATA DEĞİL: yeni mağazada müşteri olmaması normal. -->
    <p v-if="musteriler.data.length === 0" class="text-stone-500">
      {{ ara ? 'Aramanıza uyan müşteri yok.' : 'Henüz kayıtlı müşteri yok.' }}
    </p>

    <table v-else class="w-full text-sm">
      <thead class="text-left text-stone-500">
        <tr>
          <th class="py-2">Müşteri</th>
          <th>E-posta</th>
          <th class="text-right">Sipariş</th>
          <th class="text-right">Harcama</th>
          <th>Kayıt</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="m in musteriler.data" :key="m.uuid" class="border-t border-stone-100">
          <td class="py-3">
            <Link :href="`/yonetim/musteriler/${m.uuid}`" class="font-medium text-stone-900 hover:underline">
              {{ m.ad }}
            </Link>
          </td>

          <td>
            {{ m.eposta }}

            <!-- ⚠️ Doğrulama durumu listede: destek ekibi "postam gelmiyor"
                 diyen müşteride ilk buraya bakıyor (4.6W). -->
            <span v-if="!m.dogrulanmis" class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
              doğrulanmadı
            </span>
          </td>

          <td class="text-right">{{ m.siparis }}</td>
          <td class="text-right">{{ para(m.harcama) }}</td>
          <td class="text-stone-500">{{ tarih(m.kayit) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="musteriler.last_page > 1" class="mt-6 flex gap-2">
      <Link
        v-for="b in musteriler.links"
        :key="b.label"
        :href="b.url ?? '#'"
        class="rounded px-3 py-1 text-sm"
        :class="b.active ? 'bg-stone-900 text-white' : 'bg-stone-100'"
        v-html="b.label"
      />
    </div>
  </PanelDuzeni>
</template>
