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
  { baslik: null, maddeler: [
    { ad: 'Pano', yol: '/yonetim', izin: null },
  ] },
  { baslik: 'Katalog', maddeler: [
    { ad: 'Ürünler', yol: '/yonetim/urunler', izin: ['product.view', 'product.write'] },
    { ad: 'Koleksiyonlar', yol: '/yonetim/koleksiyonlar', izin: ['product.view', 'product.write'] },
    { ad: 'Katalog ayarları', yol: '/yonetim/katalog', izin: ['product.view', 'product.write'] },
  ] },
  { baslik: 'Satış', maddeler: [
    { ad: 'Siparişler', yol: '/yonetim/siparisler', izin: ['order.view'] },
    { ad: 'İadeler', yol: '/yonetim/iadeler', izin: ['order.view'] },
    { ad: 'Müşteriler', yol: '/yonetim/musteriler', izin: ['customer.view'] },
    { ad: 'Yorumlar', yol: '/yonetim/yorumlar', izin: ['product.view', 'product.write'] },
  ] },
  { baslik: 'Ayarlar', maddeler: [
    { ad: 'Mağaza', yol: '/yonetim/magaza', izin: ['settings.view', 'settings.write'] },
    { ad: 'Ödeme', yol: '/yonetim/odeme-ayarlari', izin: ['settings.view', 'settings.write'] },
    { ad: 'Yasal', yol: '/yonetim/yasal', izin: ['settings.view', 'settings.write'] },
    { ad: 'Alan adları', yol: '/yonetim/alan-adlari', izin: ['settings.view', 'settings.write'] },
    { ad: 'Tema', yol: '/yonetim/tema', izin: ['settings.view', 'settings.write'] },
    { ad: 'Personel', yol: '/yonetim/personel', izin: ['staff.view', 'staff.manage'] },
  ] },
]

/* ⚠️ HERHANGİ BİRİ — rotadaki `|` ile aynı anlam. */
const gorunenMenu = computed(() =>
  menu
    .map((grup) => ({
      ...grup,
      maddeler: grup.maddeler.filter(
        (m) => m.izin === null || m.izin.some((i) => izinler.value.includes(i)),
      ),
    }))
    /*
    | ⚠️ BOŞ GRUP DÜŞÜRÜLÜYOR. Düşürülmezse yalnızca sipariş izni olan
    | personel "Katalog" ve "Ayarlar" başlıklarını altları BOŞ hâlde
    | görürdü — menü ona kullanamayacağı bir dünya olduğunu söylerdi.
    */
    .filter((grup) => grup.maddeler.length > 0),
)

/*
| ★ ETKİN SAYFA VURGUSU (4.6AF).
|
| ⚠️ Menüde "neredeyim" işareti YOKTU: personel hangi sayfada olduğunu
| yalnızca başlıktan anlayabiliyordu.
|
| ⚠️ TAM EŞLEŞME ile ÖNEK EŞLEŞMESİ ayrı: `/yonetim` her yolun öneki
| olduğu için Pano SÜREKLİ etkin görünürdü. Kök yalnızca tam eşleşmede
| etkin; geri kalanı önek — `/yonetim/urunler/{uuid}` açıkken de
| "Ürünler" işaretli kalsın diye.
*/
function etkinMi(yol) {
  const simdiki = sayfa.url.split('?')[0]

  if (yol === '/yonetim') {
    return simdiki === '/yonetim'
  }

  return simdiki === yol || simdiki.startsWith(yol + '/')
}

/*
| ★ MOBİL ÇEKMECE (4.6AF).
|
| ⚠️ Yönlendirmeden sonra KENDİLİĞİNDEN kapanmalı: Inertia sayfayı
| değiştirir ama bileşen ayakta kalır, yani çekmece açık kalır ve
| personel yeni sayfayı örten bir menüye bakar.
*/
const menuAcik = ref(false)

router.on('navigate', () => { menuAcik.value = false })

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
    <!--
      ★ ÜST BAR + YAN MENÜ (4.6AF)

      ⚠️ MENÜ NEDEN YANA TAŞINDI: 14 madde yatayda SIĞMIYORDU. Ölçüldü —
      menü tek başına 988px, başlığın ihtiyacı 1441px, kapsayıcı 1152px:
      sahip rolünde başlık MASAÜSTÜNDE BİLE 289px taşıyordu. Telefonda
      durum çok daha kötüydü ve panelde toplam 4 kırılma noktası vardı.
      Yatay menü madde sayısıyla ölçeklenmiyor; yan menü ölçekleniyor.
    -->
    <header class="sticky top-0 z-30 bg-yuzey border-b border-kenar">
      <div class="flex h-14 items-center gap-3 px-4 lg:px-6">
        <!--
          ⚠️ `aria-expanded` + `aria-controls` ŞART: çekmece düğmesi
          durumunu yalnızca simgeyle anlatsaydı ekran okuyucu kullanan
          personel menünün açık mı kapalı mı olduğunu bilemezdi.
        -->
        <button
          type="button"
          class="lg:hidden rounded-lg border border-kenar-kontrol w-9 h-9 leading-none hover:bg-zemin"
          :aria-expanded="menuAcik ? 'true' : 'false'"
          aria-controls="panel-menu"
          aria-label="Menüyü aç/kapat"
          @click="menuAcik = !menuAcik"
        >☰</button>

        <Link href="/yonetim" class="font-black tracking-tight truncate">
          {{ marka }} <span class="text-vurgu-metin">Panel</span>
        </Link>

        <div class="ml-auto flex items-center gap-2 text-sm">
          <!-- ⚠️ Dar ekranda ad GİZLENİYOR: üst bardaki tek zorunlu şey
               menü, tema ve çıkış. Ad kalsaydı 375px'te düğmeleri iterdi. -->
          <span v-if="kullanici" class="hidden sm:inline text-metin-2 truncate max-w-[12rem]">{{ kullanici.name }}</span>

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
            class="rounded-lg border border-kenar-kontrol px-3 py-1.5 hover:bg-zemin"
            @click="cikisYap"
          >Çıkış</button>
        </div>
      </div>
    </header>

    <div class="flex">
      <!--
        ⚠️ ÖRTÜ yalnızca dar ekranda: çekmece açıkken arkadaki içeriğe
        tıklamak menüyü kapatmalı. `lg:hidden` olmasaydı masaüstünde
        görünmez bir katman bütün sayfayı tıklanamaz yapardı.
      -->
      <div
        v-if="menuAcik"
        class="fixed inset-0 top-14 z-30 bg-black/50 lg:hidden"
        @click="menuAcik = false"
      />

      <aside
        id="panel-menu"
        class="fixed lg:sticky top-14 bottom-0 lg:bottom-auto left-0 z-40 w-64 shrink-0
               lg:h-[calc(100vh-3.5rem)] overflow-y-auto
               border-r border-kenar bg-yuzey px-3 py-4
               transition-transform duration-200"
        :class="menuAcik ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
      >
        <nav>
          <div v-for="(grup, i) in gorunenMenu" :key="grup.baslik ?? i" class="mb-5">
            <p v-if="grup.baslik" class="mb-1 px-3 text-xs font-semibold uppercase tracking-wider text-soluk">
              {{ grup.baslik }}
            </p>

            <!--
              ⚠️ ÇUBUK `vurgu-metin`, `vurgu` DEĞİL. `--p-vurgu` iki
              temada da aynı koyu turuncu (düğme ZEMİNİ olduğu için
              beyaz yazıyı taşımak zorunda); koyu temada etkin maddenin
              zemini de koyu olunca çubuk 1,99'a düşüyordu — ölçüldü.
              `--p-vurgu-metin` temaya uyuyor: 3,94 / 4,54.
            -->
            <Link
              v-for="madde in grup.maddeler"
              :key="madde.yol"
              :href="madde.yol"
              class="block rounded-lg border-l-4 px-3 py-2 text-sm"
              :class="etkinMi(madde.yol)
                ? 'bg-vurgu-zemin border-vurgu-metin text-metin font-semibold'
                : 'border-transparent text-metin-2 hover:bg-zemin hover:text-metin'"
              :aria-current="etkinMi(madde.yol) ? 'page' : undefined"
            >{{ madde.ad }}</Link>
          </div>
        </nav>
      </aside>

      <!--
        ⚠️ `min-w-0` ŞART. Flex çocuğunun varsayılan en küçük genişliği
        İÇERİĞİ KADARDIR; konmazsa geniş bir tablo ana sütunu şişirir ve
        yatay kaydırma kabı işe yaramaz — sayfanın TAMAMI kayar.
      -->
      <main class="min-w-0 flex-1 px-4 lg:px-6 py-6">
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

        <div class="mx-auto max-w-5xl">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
