<script setup>
/*
 | Panel düzeni — üst bar, menü, bildirim. (4C)
 |
 | ⚠️ MENÜ İZNE GÖRE ÇİZİLİYOR ama bu YETKİ DEĞİL (4C-K4). Kullanıcı
 | adresi elle yazarsa yine sunucudaki `izin:` middleware'i durduruyor.
 | Buradaki filtre yalnızca kullanamayacağı bir bağlantıyı göstermemek için.
 */
import { computed, ref, onMounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const sayfa = usePage()

const kullanici = computed(() => sayfa.props.auth?.user ?? null)
const izinler = computed(() => sayfa.props.auth?.permissions ?? [])

/*
| TEMA DEĞİŞTİRME (4.6AE)
|
| ⚠️ Seçim `localStorage`'da, sunucuda DEĞİL: panelin teması personelin
| kendi tercihi, markanın ayarı değil. Ayara konsaydı bir personelin
| seçimi bütün ekibi etkilerdi.
|
| ⚠️ Anahtar vitrininkinden AYRI — gerekçesi `panel/app.blade.php`'de.
*/
const koyuMu = ref(false)

function temaHesapla() {
  const secim = document.documentElement.getAttribute('data-tema')

  /*
  | ⚠️ Seçim yoksa SİSTEM tercihi soruluyor. `false` varsayılsaydı gece
  | modundaki makinede düğme "koyuya geç" der ama panel zaten koyu
  | olurdu — ilk tıklama açığa geçirirdi.
  */
  return secim ? secim === 'koyu' : window.matchMedia?.('(prefers-color-scheme: dark)').matches === true
}

onMounted(() => { koyuMu.value = temaHesapla() })

function temaDegistir() {
  const yeni = temaHesapla() ? 'acik' : 'koyu'

  document.documentElement.setAttribute('data-tema', yeni)

  try { localStorage.setItem('tikmarka-panel-tema', yeni) } catch (e) { /* gizli sekme */ }

  koyuMu.value = yeni === 'koyu'
}
const marka = computed(() => sayfa.props.marka?.ad ?? 'Panel')
const bildirim = computed(() => sayfa.props.bildirim ?? {})

/*
 | Menü — her maddenin gerektirdiği izin yanında yazılı.
 |
 | ⚠️ İZİNLER ROTAYLA AYNI OLMALI (4.6S). Menü `product.write` isterken
 | rota `product.view|product.write` isteseydi salt okunur personel
 | sayfayı GÖREBİLİR ama menüde BULAMAZDI — adresi elle yazması
 | gerekirdi.
 */
const menu = [
  { ad: 'Pano', yol: '/yonetim', izin: null },
  { ad: 'Ürünler', yol: '/yonetim/urunler', izin: ['product.view', 'product.write'] },
  { ad: 'Koleksiyonlar', yol: '/yonetim/koleksiyonlar', izin: ['product.view', 'product.write'] },
  { ad: 'Katalog ayarları', yol: '/yonetim/katalog', izin: ['product.view', 'product.write'] },
  { ad: 'Siparişler', yol: '/yonetim/siparisler', izin: ['order.view'] },
  { ad: 'İadeler', yol: '/yonetim/iadeler', izin: ['order.view'] },
  { ad: 'Müşteriler', yol: '/yonetim/musteriler', izin: ['customer.view'] },
  { ad: 'Yorumlar', yol: '/yonetim/yorumlar', izin: ['product.view', 'product.write'] },
  { ad: 'Mağaza', yol: '/yonetim/magaza', izin: ['settings.view', 'settings.write'] },
  { ad: 'Ödeme', yol: '/yonetim/odeme-ayarlari', izin: ['settings.view', 'settings.write'] },
  { ad: 'Yasal', yol: '/yonetim/yasal', izin: ['settings.view', 'settings.write'] },
  { ad: 'Alan adları', yol: '/yonetim/alan-adlari', izin: ['settings.view', 'settings.write'] },
  { ad: 'Personel', yol: '/yonetim/personel', izin: ['staff.view', 'staff.manage'] },
  { ad: 'Tema', yol: '/yonetim/tema', izin: ['settings.view', 'settings.write'] },
]

/* ⚠️ HERHANGİ BİRİ — rotadaki `|` ile aynı anlam. */
const gorunenMenu = computed(() =>
  menu.filter((m) => m.izin === null || m.izin.some((i) => izinler.value.includes(i))),
)

/*
 | ★ SALT OKUNUR UYARISI (4.6S).
 |
 | ⚠️ Düğmeleri tek tek gizlemek yerine üst bara açık bir şerit: personel
 | neden hiçbir şeyi kaydedemediğini BİLMELİ. Gerçek koruma sunucuda
 | (4C-K4); bu yalnızca "neden" sorusunu cevaplıyor.
 */
const yazabilir = computed(() =>
  izinler.value.some((i) => i.endsWith('.write') || i.endsWith('.manage')
    || i.endsWith('.fulfill') || i.endsWith('.refund')),
)

function cikisYap() {
  router.post('/yonetim/cikis')
}
</script>

<template>
  <div class="min-h-screen bg-yuzey-2 text-metin">
    <header class="bg-yuzey border-b border-kenar">
      <div class="mx-auto max-w-6xl px-5 py-3 flex items-center gap-6">
        <Link href="/yonetim" class="font-black tracking-tight">
          {{ marka }} <span class="text-vurgu-metin">Panel</span>
        </Link>

        <nav class="flex gap-4 text-sm">
          <Link
            v-for="madde in gorunenMenu"
            :key="madde.yol"
            :href="madde.yol"
            class="hover:text-vurgu-metin"
          >{{ madde.ad }}</Link>
        </nav>

        <div class="ml-auto flex items-center gap-3 text-sm">
          <span v-if="kullanici" class="text-metin-2">{{ kullanici.name }}</span>

          <!--
            TEMA DÜĞMESİ (4.6AE)

            ⚠️ FORM DEĞİL `type="button"`: tema seçimi sunucuyu
            ilgilendirmiyor. Form olsaydı her tıklama sayfayı yeniden
            yükler ve personel doldurduğu ürün formunu kaybederdi.

            ⚠️ `aria-pressed` ŞART: düğme iki durumlu ve durum yalnızca
            simgeyle anlatılsaydı ekran okuyucu kullanan personel hangi
            temada olduğunu bilemezdi.
          -->
          <button
            type="button"
            class="rounded-full border border-kenar-kontrol w-8 h-8 leading-none hover:bg-zemin"
            :aria-pressed="koyuMu ? 'true' : 'false'"
            aria-label="Koyu temayı aç/kapat"
            title="Koyu tema"
            @click="temaDegistir"
          >{{ koyuMu ? '☀' : '☾' }}</button>
          <button
            type="button"
            class="rounded-lg border border-kenar-kontrol px-3 py-1 hover:bg-zemin"
            @click="cikisYap"
          >Çıkış</button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-5 py-8">
      <p v-if="!yazabilir" class="mb-4 rounded-lg bg-bilgi-zemin border border-bilgi-kenar px-4 py-3 text-sm">
        <strong>Salt okunur yetki.</strong>
        Her şeyi görebilirsiniz ama değişiklik kaydedemezsiniz.
      </p>

      <p v-if="bildirim.mesaj" class="mb-4 rounded-lg bg-basari-zemin border border-basari-kenar px-4 py-3">
        {{ bildirim.mesaj }}
      </p>
      <p v-if="bildirim.hata" class="mb-4 rounded-lg bg-tehlike-zemin border border-tehlike-kenar px-4 py-3">
        {{ bildirim.hata }}
      </p>

      <slot />
    </main>
  </div>
</template>
