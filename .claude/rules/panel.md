---
paths:
  - "resources/js/**"
  - "app/Http/Panel/**"
---

# Panel tuzakları — Inertia, Vue, Vite

Panel vitrinden **ayrı bir sistem** (4-K1): sayfa tarayıcıda render ediliyor,
yani sunucu cevabı ekrandaki metni içermiyor ve derlenmemiş bileşen
tarayıcıya hiç ulaşmıyor. Buradaki tuzakların çoğu bu farktan doğdu.

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **Inertia middleware'i GLOBAL `web` grubuna eklenmez — rota grubuna eklenir.**
  İki Inertia yüzeyi varsa (marka paneli + kontrol düzlemi) ikisi de `web`
  grubunda çalışır ve **kök görünümü sonuncusu belirler**; yani bir yüzey
  diğerinin kabuğuyla render edilebilir. Her yüzey kendi middleware'ini
  kendi grubunda takar (4F'de daraltıldı).
- **Inertia DevTools her isteğe DOSYA YAZIYOR — kapalı tutulmalı.**
  `storage/inertia-devtools/` altına kayıt açıyor ve periyodik damga
  yazıyor; bağlı klasörde `errno=35` ile düşünce panelin **bütün
  sayfaları 500** verdi. ⚠️ Belirti yanıltıcı: hata `file_put_contents`'ten
  geliyor, yığın izinde sayfayı yazan kod hiç görünmüyor. `config/inertia.php`
  → `devtools.enabled = false` (4D).
- **Inertia'da sunucu cevabı EKRANDAKİ METNİ İÇERMEZ.** Sayfa tarayıcıda
  render ediliyor; cevapta yalnızca bileşen adı ve prop'lar var. Panelde
  `assertSee('Henüz ürün yok')` yazmak testi yalancı yapar — `component`
  ve `props` üzerinden iddia kur. ⚠️ Vitrin bunun TERSİ: orada sayfa
  sunucuda render ediliyor (4-K1), metin aramak doğru yöntem.
- **`asset_helper_tenancy` AÇIKKEN Vite varlıkları 404 alıyor.** Paket
  `asset()` çağrılarını `/tenancy/assets/...` yoluna çeviriyor; derlenmiş
  panel paketi orada yok. ⚠️ **Bedeli sessiz:** sunucu 200 ve doğru HTML
  döner, testler (`withoutVite()`) yeşil kalır, ama tarayıcı betiği
  indiremediği için panel **boş sayfa** açılır. Kapatıldı (4C) — marka
  dosyaları zaten açıkça `tenant_asset()` kullanıyor.
- **Panel/Vue değişince `make derle` ŞART.** Derlenmemiş bileşen tarayıcıya
  ulaşmaz; belirti yine boş sayfa. Vitrin etkilenmez (sunucuda render
  edilen Blade, 4-K1).
- **INERTIA AYNI BİLEŞENE GİDERKEN ÖRNEĞİ YENİDEN KURMAZ — `setup()` bir
  daha koşmaz.** Oluşturma ve düzenleme aynı bileşense (`Urunler/Form`),
  setup'ta hesaplanan düz değişken (`const yeniMi = props.urun === null`)
  yönlendirmeden sonra **eski değerinde donar**. 4.5L'de ısırdı: ürün
  oluşturuluyor, yönlendirme doğru, prop'lar doğru geliyor ama varyant ve
  görsel bölümü **hiç görünmüyordu**; sayfa değiştirip geri gelince
  düzeliyordu. ⚠️ Sunucu tarafında ölçüm bunu GÖREMEZ — 4.5G'de
  "yönlendirme çalışıyor" diye kapatılmıştı, ölçülen şey ekran değildi.
  Prop'tan türeyen her şey `computed`; `useForm` başlangıç değerleri de
  `watch` ile yeniden tohumlanmalı, yoksa kutularda **eski kaydın verisi**
  kalır ve kaydedilir.
- **PANEL TESTİ VITE MANİFESTİNE BAĞLIYDI — belirti YALNIZCA CI'DA.**
  Panelin kök görünümü `@vite(...)` çağırıyor; `public/build/manifest.json`
  yoksa sayfa **500** dönüyor. Yerelde derleme çıktısı duruyor, CI'da ise
  `public/build` gitignore'da ve derleme adımı **testlerden SONRA**
  koşuyor. Yani panel testi yazan kişi yerelde yeşil, CI'da kırmızı
  alıyordu — 4.6AC'de sekiz testin sekizi böyle düştü.
  ⚠️ Önce her panel testi `withoutVite()`'i **elle** yazıyordu; artık
  `tests/Pest.php`'de `beforeEach` ile bütün `Tenancy` süiti için açık,
  yani unutulamıyor. Bozuk Vue bileşenini gizlemiyor: CI'daki ayrı "Panel
  derlemesi" adımı onu yakalıyor.
- **TABLOYU SARARKEN KOŞUL YÖNERGESİ KABA TAŞINIR.** `<table v-else>` bir
  `<div>` içine alınınca `v-else` artık `v-if`'in **komşusu değildir** ve Vue
  derlemesi patlar (4.6AF). Derleme bu kez yakaladı; ama koşulsuz bir tabloya
  sonradan `v-if` eklenirse aynı kırılma sessizce geri gelir — ölçen test
  `<table>` etiketinde `v-if|v-else|v-for` arıyor.
- **Inertia sayfa verisi ÖZNİTELİKTE DEĞİL `<script>` içinde.** v2
  `<script data-page="app" type="application/json">` kullanıyor. Testte ham
  metinde `&quot;component&quot;` aramak kırılgan; JSON'u çözüp `component`
  alanına bak.
- **Inertia SSR AÇILMAZ (4-K2).** Ayrı Node süreci uzun ömürlü ve tüm markalar
  için ortaktır; modül seviyesindeki durum istekler arasında paylaşılır
  (*cross-request state pollution*) — yani **marka sızması**. M-2.4'te
  pgBouncer'ı reddetme gerekçesinin aynısı. ⚠️ Yerelde görünmez: geliştirme
  sunucusu aynı anda tek istek işliyor. Ayrıca SSR bozulunca **sessizce**
  istemci render'ına düşüyor: sayfa çalışır, testler yeşil kalır, **SEO
  sessizce gider**.
