---
paths:
  - "resources/views/storefront/**"
  - "app/Http/Storefront/**"
---

# Vitrin tuzakları

Vitrin **sunucuda render ediliyor** (4-K1) ve bunun tek sebebi SEO.
İki düzen var (`sade`, `vitrinli`) ve hangisinin kullanıldığını MARKA
seçiyor — bir özelliği tek düzene eklemek onu öteki markada YOK ediyor.

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **`@section('ad', ifade)` KISA BİÇİMİ virgülde kırılıyor.** İfadenin
  içinde virgül varsa (`Str::limit($x, 150)`) Blade argümanları yanlış
  bölüyor ve **görünüm derlenemez** hâle geliyor. ⚠️ Belirti sinsi: sayfa
  çalışıyor görünüyor ama Larastan görünümü bulamıyor (`view-string`
  hatası) — 4B'de yarım saat aldı. Blok biçimini kullan
  (`@section('ad') … @endsection`).
- **VARSAYILAN GUARD SAYFA KATMANINDA YANLIŞ.** `config/auth.php`'de
  varsayılan `customer` — yani **sanctum, token**. Sayfalarda kimlik
  OTURUMDA (`customer-web`); `$istek->user()` yazılırsa sanctum sorulur,
  `null` döner ve **giriş yapmış müşteri misafir sayılır**. 4.5I'de
  ölçüldü: sepet müşteriye bağlanmıyor, sipariş `customer_id = null`
  doğuyordu — geliştirme markasında **24 siparişin hepsi**, ödenmişler
  dâhil, sahipsizdi ve "Siparişlerim" sayfası hiçbir zaman dolamazdı.
  ⚠️ API katmanında (`api/*`) varsayılan guard **DOĞRU** — düzeltme tüm
  vitrine değil sayfa katmanına uygulanır. Ölçen test:
  `PanelKapsamTest` sayfa dosyalarında `->user()` arıyor.
- **KİMLİĞİ OKUMAK İLE VERİYİ ÇÖZMEK AYRI ŞEYLER — ikisi de tek kapıdan
  geçmeli.** 4B'de "sepet kimliğini yalnızca `CartToken` okur" kuralı
  kondu ve ölçüldü; ama sepeti **çözen** yol serbest kaldı.
  `StorefrontViewData` (üst bardaki rozet) doğrudan `misafirSepetiBul()`
  çağırıyordu, sayfa ise `CartResolver` kullanıyordu. 4.5J'de ısırdı ve
  **iki yönü de sessizdi**: bayat misafir çerezi varken rozet dolu / sepet
  boş; giriş yapmış müşterinin dolu sepetinde rozet **hiç çıkmıyor**.
  ⚠️ Yapısal testi yazarken kapsamı **dar** tut: ilk hâli girişteki meşru
  birleştirmeyi (misafir token'ını bilerek okur) ve **kendi yorum
  metnini** ihlal sayıyordu — eşleşme çağrının kendisinde olmalı
  (`->metot(`), ham metinde değil.
- **SUNUCUDA RENDER EDİLEN YÜZEY SAATİ KENDİ ÇEVİRMELİ.** `app.timezone`
  UTC (ve öyle KALMALI); Blade `format()` onu olduğu gibi basıyor, yani
  vitrin müşteriye **üç saat geride** saat gösteriyordu. Panel Inertia
  olduğu için tesadüfen doğruydu (`new Date(iso).toLocaleString()`
  tarayıcıda çeviriyor) ve iki yüzeyin farkı "sipariş panele yanlış
  saatle düşmüş" gibi göründü (4.5M). ⚠️ Çözüm `config/app.php`'yi
  değiştirmek **DEĞİL**: `now()` sorguya ofissiz metin bağlanıyor ve
  rezervasyon süreleri kayıyor — kırma denemesiyle ölçüldü, `ZamanDilimiTest`
  düştü. Doğrusu **gösterim** saat dilimi ayarı + `setTimezone()`; değer
  beyaz listeden geçmeli, yoksa geçersiz ayar sayfayı 500'e düşürür.
- **İSİMSİZ POST ROTASI + `route()` = FORM YANLIŞ ADRESE GİDER.** 4.6V'de
  ısırdı: sıfırlama formunun `action`'ı `route('vitrin.sifre.sifirla')`
  yazıyordu, o **GET** rotasının adıydı; POST rotası isimsiz ve başka
  adresteydi. Müşteri postadaki bağlantıyı açtı, şifreyi yazdı ve **405**
  aldı. ⚠️ **Yedi testin hiçbiri göremedi**: hepsi doğrudan doğru adrese
  POST ediyordu (`$this->post('/sifre-sifirla', …)`) — formun NEREYE
  gittiğini kimse sormamıştı. Kural: bir formu sınayan test **sayfayı
  render edip `action`'ı okumalı** ve tam oraya göndermeli.
  ⚠️ Regex'i `method="post"` ile daralt — düzenin başlığındaki arama
  formu (`method="get"`) sayfada ÖNCE geliyor ve ilk eşleşme odur; yoksa
  test düzeltilmiş kodda da 405 verir. "Form alanları doğrulamayla hizalı
  olmalı" tuzağının ADRES tarafı: orada eksik olan ALAN'dı, burada ADRES.
- **ÜRÜN SAYFASINA EKLENEN HER ŞEY İKİ DÜZENİ DE KAPSAMALI.** Vitrinin iki
  düzeni var (`sade`, `vitrinli`) ve hangisinin kullanıldığını **marka
  belirliyor** (tema bir ayar, 4-K5). Yalnızca birine eklenen özellik,
  öteki düzeni seçmiş markanın müşterisinde **hiç yok**. 4.6A'da ısırdı:
  varyant seçicisi yalnızca `sade`'ye uygulanmıştı, `vitrinli` kullanan
  marka (geliştirme markası dâhil) eski düz açılır listeyi görmeye devam
  ediyordu. ⚠️ Altı testin hiçbiri göremedi çünkü hepsi VARSAYILAN
  düzende koşuyordu — kapsamı ölçen test düzeni tek tek DEĞİŞTİRMELİ.
  ⚠️ Çözüm kopyalamak değil ORTAK PARÇA (`partials/`): kopya, aynı
  hatanın bir sonraki tekrarını hazırlar.
- **SİLİNEN KAYDIN SEPETTEKİ İZİ YÖNETİLEBİLİR KALMALI — yoksa sepet
  KİLİTLENİR.** 4.6AJ'de ölçüldü: varyant yumuşak silinince ilişki `null`
  dönüyor, ekran `value="{{ $satir->variant?->uuid }}"` ile **boş** alan
  basıyor ve müşteri satırı **çıkaramıyor**; üstelik `satiriBul()`
  `whereHas('variant')` ile aradığı için ikinci bir bariyer daha var.
  Kural 1E.6'nın sepet hâli: **kapatan yol** (sepetten çıkarma) silinmişi
  görmeli, **açan yol** (sepete ekleme, ödemeye geçme) görmemeli.
  ⚠️ Strateji "sessizce sil" DEĞİL "işaretle": müşterinin sepetinden bir
  şeyi habersiz çıkarmak "ürünüm nerede" sorusunu doğurur.
- **ÖNERİ/POPÜLERLİK BÖLÜMÜ EŞİKSİZ KURULMAZ.** Az veriyle üretilen liste
  popülerlik değil **gürültü** ölçüyor: B1'de ölçüldü, markada 20
  görüntüleme vardı ve eşiksiz bir "en çok tıklanan" bölümü **tek
  tıklamayı** popüler ilan ederdi. Aynı şekilde katalogun tamamı son 30
  günde eklendiyse "yeni gelenler" **katalogun kendisidir** ve müşteri
  aynı ürünleri iki kez görür. Kural: **verisi olmayan bölüm hiç
  çizilmez** — boş göstermek de yanlış göstermek kadar kötü değil, ama
  yanlış göstermek en kötüsü.
- **KİŞİYE ÖZEL LİSTE ORTAK ÖNBELLEĞE KONMAZ.** B1'de kırma denemesiyle
  ölçüldü: kişisel bölüm `Cache::remember` ile ortak anahtara konsaydı
  **bir müşterinin önerileri başkasına** gösterilirdi. Bu çok kiracılık
  sızması değil, **aynı marka içinde müşteriler arası** sızma — kiracı
  öneki onu engellemiyor.
- **BÖLÜMLER ARASI TEKRARI ENGELLEMEK BAŞLIĞI YALAN YAPABİLİR.** "Çok
  satanlar"dan gerçek en çok satanı, başka bölümde geçtiği için çıkarmak
  o başlığın vaadini bozar (B1). Tekrar bir kusur değil; küçük katalogda
  rahatsız ediyorsa çözüm eşikleri yükseltmek, listeyi çarpıtmak değil.
- **BİR ÖZELLİĞİ İKİNCİ DÜZENE TAŞIMAK, DESTEKLEYİCİ İŞARETLERİNİ DE
  TAŞIMAKTIR.** 4.6A'da varyant seçicisi yalnızca `sade`'deydi; 4.6A.1 onu
  `vitrinli`'ye taşıdı ama betiğin aradığı `data-fiyat` ve
  `data-ekle-dugme` işaretlerini taşımadı — kusur 4.6AL'ye kadar **canlı**
  kaldı (marka-a `vitrinli` kullanıyor). ⚠️ Bedeli fiyatın güncellenmemesi
  değildi: `data-ekle-dugme` yokken düğme hiç kapanmıyor ve müşteri
  **boş `variant_uuid`** gönderebiliyordu; üstelik `null` üzerindeki
  TypeError ondan **sonraki** uyarı mantığını da öldürüyordu.
- **KANCA LİSTESİNİ ELLE YAZMA, BETİKTEN OKU.** Bir betiğin
  `document.querySelector('[data-...]')` ile aradığı işaretleri ölçen test,
  listeyi betikten çıkarmalı; elle yazılırsa betiğe yeni kanca eklenince
  liste bayat kalır ve test yine yalan söyler (4.6AL).
- **ÇİZİLEN SAYFAYI OKUYAN İDDİA `<script>` BLOKLARINI AYIKLAMALI.**
  4.6AL'de üç kırma denemesi tutmadı: sayfadaki betik kancaları **adıyla**
  arıyor (`querySelector('[data-ekle-dugme]')`), yani aranan dizge
  öznitelik silinse bile HTML'de duruyordu. ⚠️ 4.6AE'nin kardeşi — orada
  iddia kuralı ANLATAN yorumu okuyordu, burada kuralı ARAYAN betiği.
- **"HER GÖRSELE `lazy`" YANLIŞ — ekranın üstündekini GECİKTİRİR.** Tarayıcı
  `lazy` görselde önce yerleşimi hesaplayıp sonra indirmeye başlıyor; yani
  en çok görülen görselleri yavaşlatmış olursun. Sayfanın **ilk
  ızgarasının ilk satırı** `eager`, gerisi `lazy` (B2). ⚠️ Her ızgara kendi
  ilk satırını istekli yüklerse kazancın çoğu gider — istekli sayısı
  ızgaraya değil SAYFAYA ait bir karardır.
- **SONSUZ KAYDIRMA SEO'YU ÖLDÜRÜR — bağlantı GERÇEK kalmalı.** Vitrin
  sunucuda render ediliyor (4-K1) ve tek sebebi arama motorunun sayfayı
  görebilmesi; ürünler yalnızca JavaScript'le gelseydi ilk sayfadan
  sonrası taranamaz olurdu. Doğrusu gerçek bir `<a href="?sayfa=2">` ve
  onu **üstlenen** bir betik (B2): JS kapalıysa tıklanıyor, motor
  tarayabiliyor.
  ⚠️ `withQueryString()` unutulursa arama sayfa 2'de kayboluyor.
- **BLADE `@context`'İ KENDİ YÖNERGESİ SANIYOR — JSON-LD BLADE'DE ÜRETİLMEZ.**
  Derleyici `@` ile başlayan her adı yönerge diye deniyor; JSON-LD'nin
  `"@context"` anahtarı derlemede `<?php $__contextArgs = []; …` oluyor.
  ⚠️ Belirti tamamen sessiz: sayfa açılıyor, hata çıkmıyor, üretilen yapısal
  veri **geçersiz**. `@section('ad', ifade)` tuzağıyla aynı aile. Üretimi
  PHP sınıfına taşı (B3 · `ProductStructuredData`).
- **`public/robots.txt` ÇOK KİRACILIKTA YANLIŞ.** Statik dosya markaya göre
  değişemiyor: her marka aynı `Sitemap:` satırını görüyordu (B3). `robots.txt`
  ve `sitemap.xml` kiracı rotasıdır.
- **`limit()` İLE LİSTELEMEK SESSİZ BİR KESİNTİDİR.** Ana sayfa `limit(24)`
  ile çiziliyordu: 25. ürün hiç görünmüyordu ve bunu söyleyen bir şey de
  yoktu (B2). Sayfalanabilir bir liste `paginate()` ister; `limit()`
  yalnızca gerçekten "ilk N" istendiğinde doğrudur.

## Yapı

```
app/Platform/   merkez şema (Tenant)          app/Models/    marka şeması modelleri
app/Tenancy/    kiracılık KOMUTLARI           app/Http/      Platform · (Panel · Storefront)
app/Domain/     iş mantığı — kiracıdan habersiz
```

⚠️ Kiracılık **tek klasörde toplanmıyor** — `app/Tenancy/` yalnızca komutları
tutuyor (142 satır). Kiracılığa dokunan yerlerin tamamı:
`config/tenancy.php` (paket ayarı, tohumlayıcı sınıfı) · `routes/tenant.php`
(kapı görevlisi middleware zinciri) · `bootstrap/app.php` (takma adlar,
istisna eşlemeleri) · `tests/Pest.php` (kiracı kurulumu ve temizlik).
Bir kiracılık davranışı ararken bu beşine bak.

`app/Domain/` içindeki hiçbir dosya `Tenancy` sınıflarını import etmez ve
"hangi kiracıdayım" diye sormaz (M-2.7). **Ölçüldü:** `app/Domain/` içinde
`App\Tenancy`, `tenant(`, `tenancy(` geçişi sıfır.

**İş kuralı controller'a yazılmaz.** Kural: bir kontrol, HTTP dışından
(artisan komutu · kuyruk işi · tohumlayıcı) atlanabiliyorsa `app/Domain/`'e
girer. Controller yalnızca çevirir: isteği al, servisi çağır, cevabı biçimle.

Testler: `tests/Feature/` → `RefreshDatabase` var. `tests/Tenancy/` → **yok**
(transaction, şema oluşturmayı bozuyor); temizlik `tests/Pest.php`'de.

## Çalışma biçimi

- Belgeler ve kod yorumları **Türkçe**, tanımlayıcılar İngilizce.
- Bir madde bitince: `lint` + `analyse` + `test` üçü de yeşil olmadan commit yok.
- Plan canlıdır: gerçek planla çelişirse **plan güncellenir**, gerekçesiyle.
- Commit mesajlarına co-author/imza satırı **eklenmez**.
