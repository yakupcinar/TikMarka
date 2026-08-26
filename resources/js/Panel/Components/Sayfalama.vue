<script setup>
/*
 | Panel sayfalama — YALNIZCA SAYILAR. (4.6AI)
 |
 | ★ İSTEK: "sadece sayılar 1 2 3 istiyorum". Önce düğmelerde ham
 | `pagination.next` yazıyordu (çeviri dosyası hiç yoktu, 4.6F.1'de
 | eklendi); istek bundan ibaret değildi, ileri/geri metinleri de
 | istenmiyordu.
 |
 | ⚠️ ORTAK PARÇA, KOPYA DEĞİL. Aynı sayfalama DÖRT sayfada
 | tekrarlanıyordu (Siparişler · Ürünler · Müşteriler · Yorumlar) ve
 | ikisi farklı sınıflar kullanıyordu. 4.6A'nın dersi: kopya, aynı
 | hatanın bir sonraki tekrarını hazırlıyor.
 |
 | ⚠️ `v-html` KULLANILMIYOR. Laravel'in etiketleri HTML varlığı
 | taşıyor (`&laquo;`) ve eski kod bu yüzden `v-html` yazmıştı; sayı
 | ve "..." düz metin olduğu için gerek kalmadı. Sunucudan gelen
 | metni HTML olarak basmamak her hâlükârda daha iyi.
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  baglantilar: { type: Array, required: true },
})

/*
| Laravel `links` dizisinde İLK öğe "önceki", SON öğe "sonraki" olur;
| aradakiler sayfa numaraları ve gerektiğinde "..." ayracıdır.
|
| ⚠️ Konuma göre (ilkini/sonuncusunu at) DEĞİL, İÇERİĞE göre eleniyor:
| dil değiştiğinde ya da Laravel dizinin şeklini değiştirdiğinde konum
| varsayımı sessizce yanlış öğeyi atardı.
*/
const sayfalar = computed(() =>
  props.baglantilar.filter((b) => /^\d+$/.test(String(b.label).trim()) || String(b.label).trim() === '...'),
)
</script>

<template>
  <!--
    ⚠️ Tek sayfa varsa hiç çizilmiyor: tek bir "1" düğmesi bilgi
    taşımıyor, yalnızca yer kaplıyor.
  -->
  <nav v-if="sayfalar.length > 1" class="mt-4 flex flex-wrap gap-1" aria-label="Sayfalar">
    <template v-for="(b, i) in sayfalar" :key="`${b.label}-${i}`">
      <!--
        ⚠️ "..." bir BAĞLANTI DEĞİL. Laravel onu da `links` içinde
        veriyor ama `url` alanı boş; bağlantı olarak çizilseydi
        tıklanabilir görünüp hiçbir yere gitmezdi.
      -->
      <span v-if="b.label === '...'" class="px-2 py-1 text-sm text-soluk">…</span>

      <Link
        v-else
        :href="b.url ?? ''"
        class="rounded-lg border px-3 py-1 text-sm tabular-nums"
        :class="b.active
          ? 'bg-vurgu-zemin border-vurgu-metin font-semibold'
          : 'bg-yuzey border-kenar-kontrol hover:bg-zemin'"
        :aria-current="b.active ? 'page' : undefined"
      >{{ b.label }}</Link>
    </template>
  </nav>
</template>
