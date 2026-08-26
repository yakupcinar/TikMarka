<script setup>
/*
 | Ürün raporu — görüntüleme → sepet → satış. (4.6F)
 |
 | ⚠️ Olaylar 1F'den beri yazılıyordu ama markanın onları göreceği hiçbir
 | yer yoktu. Ölçüm var, ekran yok.
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const props = defineProps({
  satirlar: { type: Array, required: true },
  gun: { type: Number, required: true },
  donemler: { type: Array, required: true },
  ciroGorunur: { type: Boolean, required: true },
})

function para(v) {
  return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL'
}

/*
| ⚠️ DÖNÜŞÜM PAYDASI SIFIR OLABİLİR. Bölme korunmazsa `Infinity` çıkar
| ve ekranda "∞%" yazar; marka bunu bir başarı sanır.
|
| ⚠️ SATIŞ > GÖRÜNTÜLEME İSE ORAN HESAPLANMIYOR — ve bu kural gerçek
| ekrana bakarken bulundu: "Basic Tişört" 9 görüntülemeden 11 satışla
| **%122** gösteriyordu. Matematik doğru, sonuç saçma; sebep görüntüleme
| ölçümünün 4.6F'ye kadar eksik olması. Marka bunu "her bakan iki tane
| aldı" diye okurdu.
|
| ⚠️ Sayıyı DÜZELTMİYORUZ (tavan koymak gibi) — bilinmeyeni bilinir
| göstermek daha kötü. Oran YOK, sebebi üstteki şeritte yazılı.
*/
function oran(pay, payda) {
  if (!payda || pay > payda) return null
  return Math.round((pay / payda) * 100)
}

/*
| ★ ÖLÇÜMÜN EKSİK OLDUĞU DÖNEMİ EKRAN SÖYLÜYOR.
|
| ⚠️ Görüntüleme 4.6F'ye kadar YALNIZCA API ucundan sayılıyordu; müşterinin
| gezdiği sayfa hiç kayıt üretmiyordu. Yani eski satırlarda "0 görüntüleme,
| 4 satış" gibi imkânsız huniler var. Ekran bunu söylemezse marka raporu
| bozuk sanır — ya da daha kötüsü, ürünün hiç ilgi görmediği sonucunu
| çıkarır.
*/
const eksikOlcumVar = computed(() =>
  props.satirlar.some((s) => s.satisAdedi > s.goruntuleme),
)
</script>

<template>
  <PanelDuzeni>
    <div class="flex flex-wrap items-center gap-4 mb-6">
      <h1 class="text-2xl font-bold">Ürün raporu</h1>

      <div class="ml-auto flex flex-wrap gap-1 text-sm">
        <Link
          v-for="d in donemler"
          :key="d"
          :href="`/yonetim/rapor?gun=${d}`"
          class="rounded-lg border border-kenar-kontrol px-3 py-1"
          :class="d === gun ? 'bg-vurgu-zemin border-vurgu-metin font-semibold' : 'bg-yuzey hover:bg-zemin'"
        >{{ d }} gün</Link>
      </div>
    </div>

    <p v-if="eksikOlcumVar" class="mb-4 rounded-lg bg-uyari-zemin border border-uyari-kenar px-4 py-3 text-sm">
      <strong>Görüntüleme sayıları bu dönemin tamamını kapsamıyor.</strong>
      Ürün sayfası görüntülemeleri yakın zamana kadar sayılmıyordu; aşağıda
      satışı görüntülemesinden fazla görünen satırlar bundan kaynaklanıyor
      ve o satırlarda dönüşüm oranı hesaplanmıyor. Sepet ve satış sayıları
      etkilenmedi.
    </p>

    <div class="overflow-x-auto">
      <table class="min-w-[42rem] w-full bg-yuzey rounded-xl border border-kenar overflow-hidden shadow-kart">
        <thead class="bg-zemin text-left text-xs font-semibold tracking-wide uppercase text-soluk">
          <tr>
            <th class="p-3">Ürün</th>
            <th class="p-3 text-right">Görüntüleme</th>
            <th class="p-3 text-right">Sepete ekleme</th>
            <th class="p-3 text-right">Satış</th>
            <th class="p-3 text-right">Dönüşüm</th>
            <th v-if="ciroGorunur" class="p-3 text-right">Ciro</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in satirlar" :key="s.slug" class="border-t border-kenar-soft">
            <td class="p-3">
              <a :href="`/urun/${s.slug}`" target="_blank" rel="noopener"
                 class="text-base font-medium hover:text-vurgu-metin">{{ s.baslik }}</a>
            </td>
            <td class="p-3 text-right text-sm tabular-nums">{{ s.goruntuleme }}</td>
            <td class="p-3 text-right text-sm tabular-nums">{{ s.sepeteEkleme }}</td>
            <td class="p-3 text-right text-base font-medium tabular-nums">{{ s.satisAdedi }}</td>

            <!--
              ⚠️ Payda sıfırsa TİRE, "%0" DEĞİL. Sıfır "kimse almadı"
              demek olurdu; oysa bilinen bir şey yok.
            -->
            <td class="p-3 text-right text-sm tabular-nums">
              <span v-if="oran(s.satisAdedi, s.goruntuleme) !== null">
                %{{ oran(s.satisAdedi, s.goruntuleme) }}
              </span>
              <span v-else class="text-soluk">—</span>
            </td>

            <td v-if="ciroGorunur" class="p-3 text-right text-base font-medium tabular-nums">
              {{ para(s.ciro) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="!satirlar.length" class="rounded-xl bg-yuzey border border-kenar p-10 text-center text-metin-2 shadow-kart">
      Bu dönemde gösterilecek ürün yok.
    </p>

    <!--
      ⚠️ Ciro gizliyse SEBEBİ yazılıyor. Yazılmasaydı personel sütunun
      kaybolduğunu görür ve arıza sanardı.
    -->
    <p v-if="!ciroGorunur" class="mt-4 text-sm text-soluk">
      Ciro sütunu yalnızca finansal veri yetkisi olan personele gösteriliyor.
    </p>
  </PanelDuzeni>
</template>
