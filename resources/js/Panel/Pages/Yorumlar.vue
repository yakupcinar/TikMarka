<script setup>
/*
 | Yorum moderasyonu. (4.5F)
 |
 | ⚠️ Yorum ONAYLANMADAN vitrinde görünmüyor ve ortalamaya girmiyor (2E).
 | Bu ekran o kuyruğun tek çıkışı — ekran olmadığı sürece hiçbir yorum
 | vitrine çıkamıyordu.
 */
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'
import Sayfalama from '../Components/Sayfalama.vue'

const props = defineProps({ yorumlar: Object, durum: String, bekleyen: Number })

const not = ref({})

function suz(deger) { router.get('/yonetim/yorumlar', { durum: deger }, { preserveState: true }) }
function onayla(y) { router.post(`/yonetim/yorumlar/${y.uuid}/onayla`) }
function reddet(y) { router.post(`/yonetim/yorumlar/${y.uuid}/reddet`, { note: not.value[y.uuid] ?? '' }) }

const durumAdi = { pending: 'Bekleyen', approved: 'Onaylı', rejected: 'Reddedilen' }
</script>

<template>
  <Head title="Yorumlar" />

  <PanelDuzeni>
    <div class="flex flex-wrap items-center gap-4 mb-6">
      <h1 class="text-2xl font-bold">Yorumlar</h1>

      <!-- ⚠️ Bekleyen sayısı her sekmede görünüyor: marka onay kuyruğunu
           unutmamalı, yoksa vitrinde hiç yorum çıkmaz. -->
      <span v-if="bekleyen > 0" class="rounded-full bg-uyari-zemin text-uyari px-3 py-1 text-xs">
        {{ bekleyen }} yorum onay bekliyor
      </span>

      <select class="ml-auto rounded-lg border border-kenar-kontrol px-3 py-2 text-sm" :value="durum" @change="suz($event.target.value)">
        <option v-for="(ad, d) in durumAdi" :key="d" :value="d">{{ ad }}</option>
      </select>
    </div>

    <div v-if="yorumlar.data.length === 0" class="rounded-xl bg-yuzey border border-kenar p-10 text-center text-metin-2 shadow-kart">
      Bu durumda yorum yok.
    </div>

    <div v-for="y in yorumlar.data" :key="y.uuid" class="rounded-xl bg-yuzey border border-kenar p-5 mb-4 shadow-kart">
      <div class="flex items-center gap-3 mb-2">
        <span class="text-uyari">{{ '★'.repeat(y.rating) }}<span class="text-soluk-2">{{ '★'.repeat(5 - y.rating) }}</span></span>
        <strong v-if="y.title">{{ y.title }}</strong>
        <span class="text-xs text-soluk">{{ y.product }}</span>
        <!-- ⚠️ Panelde TAM AD, vitrinde kısaltılmış (2E). -->
        <span class="text-xs text-soluk">· {{ y.customer }} · {{ y.created_at }}</span>
      </div>

      <p class="text-sm text-metin-2 mb-3">{{ y.body }}</p>

      <p v-if="y.moderation_note" class="text-xs text-soluk mb-2">Not: {{ y.moderation_note }}</p>

      <div v-if="y.status === 'pending'" class="flex gap-2 items-center">
        <button type="button" class="rounded-lg bg-vurgu text-white px-4 py-2 text-sm font-semibold" @click="onayla(y)">
          Onayla
        </button>
        <input v-model="not[y.uuid]" placeholder="Ret gerekçesi (isteğe bağlı)" class="flex-1 rounded-lg border border-kenar-kontrol px-3 py-2 text-sm">
        <button type="button" class="rounded-lg border border-tehlike-kenar text-tehlike px-4 py-2 text-sm" @click="reddet(y)">
          Reddet
        </button>
      </div>

      <span v-else class="text-xs rounded-full bg-yuzey-3 px-2 py-0.5">{{ durumAdi[y.status] }}</span>
    </div>

    <Sayfalama :baglantilar="yorumlar.links" />
  </PanelDuzeni>
</template>
