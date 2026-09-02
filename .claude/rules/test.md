---
paths:
  - "tests/**"
---

# Test yazma tuzakları

Bu projede tuzakların ÇOĞU bir test yalan söylediği için bulundu.
Buradaki maddelerin ortak yanı şu: **test yardımcısı, ölçmek istediğin
şeyi ortadan kaldırıyor.**

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **Uçtan uca testte kimlik MODELDEN okunmaz.** İsteğin gövdesine giren her
  kimlik (uuid, sürüm no, satır id) bir önceki **uçtan** gelmeli. `$varyant->uuid`
  yazmak testi yeşil tutar ama "istemci bu değeri nereden bulacak" sorusunu
  sormaz. 1D.6'da iki ölü uç bu yüzden 232 testin altından geçti: vitrin varyant
  `uuid`'sini döndürmüyordu ve vitrinde yasal metin ucu hiç yoktu — yani gerçek
  müşteri sipariş **veremiyordu**. İki kiracıda gerçek HTTP koşusu yakaladı.
- **Test istemcisi ÇEREZ TAKİP EDİYOR — "oturum kapandı" iddiası bununla
  ölçülemez.** 4.5D'de ölçüldü: çapraz marka denemesinden sonra test, A'nın
  da kapandığını "gösteriyordu"; `curl` ile **eski** çerez elle
  gönderilince A açık kaldı. Test, sunucunun davranışını değil kendi çerez
  takibini ölçüyormuş. Oturum geçersizliğini ölçmek istiyorsan **eski
  çerezi elle** gönder.
- **Testte GERÇEK DNS SORGUSU yapılmaz.** `SystemDnsChecker` ağa çıkıp
  zaman aşımını bekliyor: tek test **24 saniye** sürdü (4.5C). Bundan
  kötüsü test **ağa bağımlı** olur — ağ yoksa kırılır ve ölçtüğü şey bizim
  kodumuz değil internet olur. `FakeDnsChecker` bağla (3H'de bunun için
  yazıldı).
- **`assertRedirect()` HEDEFSİZ çağrılırsa yönlendirmenin NEREYE gittiğini
  ölçmez.** 4.5'te iki kez ısırdı: ödeme akışı sağlayıcıya yönlendirmekten
  kendi sayfamıza döndü ve **hiçbir test kırılmadı**; ödeme sayfasındaki
  sözleşme bağlantısı ham JSON'a gidiyordu ve test yalnızca bağlantı
  METNİNİ arıyordu. Hedefi yaz.
- **`UploadedFile::fake()` MIME TÜRÜNÜ DE UYDURUYOR.** Uzantıdan
  türetiyor; yani "içeriği PHP ama adı .png" senaryosunu **ölçemezsin** —
  doğrulama `image/png` görür ve test yeşil kalır. İçerik tabanlı tür
  kontrolünü sınayan testte **gerçek dosya** yaz ve `new UploadedFile(...)`
  ile gönder (4G'de ölçüldü).
- **Test yardımcısı İKİNCİ dosyada kullanılacaksa `tests/Pest.php`'ye taşınır.**
  Tek test dosyasında tanımlı kalırsa diğer dosya **tek başına** koşturulunca
  "tanımsız fonksiyon" verir — tüm süitte görünmeyen, dosya yükleme sırasına
  bağlı sessiz bağımlılık. 4E'de `iadeyeHazirSiparis` ve `inertiaVerisi`
  bu yüzden taşındı.
  ⚠️ **Aynı madalyonun öteki yüzü: ADI ÇAKIŞMASIN.** Test dosyasındaki
  fonksiyonlar **global** — başka bir test dosyasında aynı ad varsa iki dosya
  birlikte yüklenince PHP *"cannot redeclare"* ile ölür. 4.5H'de yaşandı
  (`koleksiyonluMagaza` iki dosyada); **tek dosya koşarken testler yeşildi**,
  gösteren Larastan oldu (`invoked with 0 parameters, 1 required` — imza
  ÖTEKİ dosyadan okunuyordu). Yardımcı yazmadan önce `grep -rn "function ad" tests/`.
- **`sevkiyatlikSiparis()` PARA İADESİNE HAZIR DEĞİL.** Ödemeyi servisten
  yapıyor, **tahsil edilmiş `Payment` kaydı açmıyor**; `RefundService`
  `firstOrFail()` ile onu arıyor ve bulamayınca **404** dönüyor. ⚠️ Belirti
  yanıltıcı: hata mesajı değil Laravel'in 404 sayfası geliyor, yani "rota
  yok" sanılıyor. Para iadesi testinde `iadeyeHazirSiparis()` kullan.
- **`getJson` ŞİFRELENMEMİŞ ÇEREZİ DÜŞÜRÜYOR — istek çerezsiz gidiyor.**
  Ölçüldü (4A): aynı istek `get()` ile çerezi taşıyor, `getJson()` ile çerez
  torbası **boş** geliyor ve hata yok. Ayrıca iki yardımcı iki farklı şey
  yapıyor: `withCookie()` değeri **şifreliyor**, `withUnencryptedCookie()`
  düz gönderiyor. Çerez okuyan testte `get()` + elle `Accept` başlığı kullan.
  ⚠️ `postJson`'ın `Accept` başlığını sessizce eklemesiyle (2E) aynı aile:
  **test yardımcısı, ölçmek istediğin şeyi ortadan kaldırıyor.**
- **`actingAs()` VARSAYILAN GUARD'I DA DEĞİŞTİRİYOR — guard hatasını
  GİZLER.** 4.5I'de iki kez ısırdı: (1) kök sebebi ölçmek için yazdığım
  test `actingAs` ile **hatalı kodla yeşil geçti**; gerçek `/giris`
  POST'uyla ölçünce düştü. (2) `GomuluOdemeTest`'te bir güvenlik testi
  `actingAs($musteri, 'customer')` (TOKEN) kullanıyordu ama ölçtüğü şey
  bir SAYFA rotasıydı — yıllardır yanlış şeyi ölçüyormuş, düzeltme onu
  ortaya çıkardı. Kural: kimliğin **hangi guard'dan** çözüldüğünü ölçen
  testte `actingAs` KULLANILMAZ, gerçek giriş isteği atılır.
  (`postJson`'ın `Accept` eklemesi ve `getJson`'ın çerezi düşürmesiyle
  aynı aile: **test yardımcısı ölçmek istediğin şeyi ortadan kaldırıyor**.)
- **`postJson`/`getJson` ÇEREZLERİ VARSAYILAN GÖNDERMEZ.** Laravel'in test
  istemcisinde `prepareCookiesForJsonRequest()` yalnızca `withCredentials()`
  çağrıldıysa çerez taşıyor — `getJson`'ın çerezi düşürmesiyle (4A) aynı
  aile, farklı sebep. 4.6T'de API kupon testinde ısırdı: `postJson` ile
  gönderilen istek çerezsiz gittiği için sepet hep "bulunamadı" (404)
  dönüyordu. Çözüm: `->withCredentials()->withUnencryptedCookie(...)`.
- **ÇAPRAZ MARKA TESTİNDE OTURUM TEMİZLENMEZSE YANLIŞ ŞEY ÖLÇÜLÜR.**
  Test istemcisi çerez takip ediyor; A'da açılan oturum B'ye taşınıyor ve
  `EnsureSessionTenant` isteği **asıl kontrolden önce** kesiyor. 4.6W'de
  imzanın markaya bağlılığını ölçen test 403 yerine 302 aldı — koruma
  çalışıyordu ama ölçülen koruma 4.5D'de zaten ölçülen BAŞKASIYDI.
  `flushSession()` ile gerçek senaryoya (postadan tıklayan, o markada
  oturumu olmayan kişi) dön.
- **`test()` KULLANAN YARDIMCI `tests/Pest.php`'DE OLMAK ZORUNDA.**
  Statik analiz Pest'in bağlamasını göremiyor ve `phpstan.neon`'daki
  istisna YALNIZCA o dosya için tanımlı; başka bir test dosyasına
  yazılırsa Larastan *"call to an undefined method"* veriyor. Yardımcıyı
  iki dosya kullanmıyor olsa bile kural burada teknik olarak zorunlu.
- **DOMAIN KONTROLÜ VERİTABANI KISITINI MASKELER — kısıtı ölçen test
  DOMAIN'İ ATLAMALI.** 4.6X.1'de ölçüldü: kısıtı geri gevşeten migration
  değişikliği **hiçbir testi düşürmedi**, çünkü Domain isteği veritabanına
  hiç ulaştırmıyor. Oysa kısıt Domain'in yedeği değil SON SAVUNMASI —
  yarış durumunda iki eşzamanlı istek de kontrolü geçebilir, tohumlayıcı
  ve komut satırı Domain'i hiç kullanmayabilir. Ölçen test servisi değil
  **doğrudan tabloyu** kullanmalı (`DB::table(...)->insert(...)`).
- **YAPISAL TEST "KAP VAR MI"YI ÖLÇER, "EKRANDA NE OLUYOR"U DEĞİL.**
  4.6AF'nin on bir testi yeşilken 375px'te 14 sayfanın 5'i hâlâ yatay
  kayıyordu. Sözleşme testleri gerileme koruması; **yerleşimin kendisi
  gerçek tarayıcıda, gerçek genişlikte gezilerek** doğrulanır. Panel giriş
  gerektirdiği için bu adım atlanabiliyor — atlanırsa blok yarım kalır
  ("bitti kaydı bittiğinin kanıtı değildir" kuralının yerleşim biçimi).
- **`tests/Pest.php`'YE TAŞIMA KURALI YAZILI OLMASINA RAĞMEN İKİ KEZ
  TEKRARLANDI** (`panelSayfalari()` 4.6AG · `vitrinliMarka()` 4.6AH).
  Yeni bir test dosyası açarken kullanacağın her yardımcının **nerede
  tanımlı olduğuna** bak; tek dosyada duruyorsa önce taşı, sonra kullan.
  ⚠️ Taşırken tam nitelikli ad kullanılıyorsa sınıfın gerçek ad alanını
  doğrula: `SettingGroup` `App\Domain\Settings` değil **`App\Enums`**
  altında ve yanlış yazıldığında 14 test birden düşüyor.
- **AYNI DEĞERİ TAŞIYAN FIXTURE, İKİ FORMÜLÜ AYIRT EDEMEZ.** `seciciUrunu()`
  bütün varyantları aynı fiyatta açıyor; "tüm varyantların min'i" ile
  "satılabilir varyantların min'i" aynı sayı çıkıyor ve fiyatı yanlış
  kaynaktan alan kırma denemesi **hiçbir testi düşürmüyor** (B3). İki yolu
  ayıran testte fixture'ın o iki yolda **farklı** sonuç vermesini sağla.
