---
paths:
  - "resources/css/**"
  - "resources/views/**/*.blade.php"
  - "resources/js/**/*.vue"
---

# Tasarım tuzakları — tema, renk, kontrast, yerleşim

Bu dosya yalnızca stil/görünüm dosyalarına dokunulduğunda yükleniyor.
Buradaki her madde en az bir kez **hata vermeden yanlış sonuç** üretti:
koyu temada görünmeyen metin, ölçülmemiş kontrast, mobilde sıkışan ızgara.

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **KOYU TEMA EKLERKEN SİSTEM KURALI KULLANICI SEÇİMİNİ EZMEMELİ.**
  `@media (prefers-color-scheme: dark)` bloğu `:root:not([data-tema="acik"])`
  ile korunmazsa, gece modundaki telefonda "açık tema" seçimi hiç
  çalışmaz. ⚠️ Bunu ölçen test **belirteçleri tanımlayan bloğa** bakmalı,
  sayfada bir yerde geçmesine değil: aynı ifade başka kurallarda da
  geçebiliyor ve kırma denemesi tutmuyor (4.6AB'de yaşandı).
  ⚠️ Tema betiği CSS'ten ÖNCE ve senkron olmalı; sonra gelirse sayfa
  açık temayla boyanıp koyuya atlıyor (FOUC).
  ⚠️ Marka rengi (`--marka`) koyu temada YENİDEN TANIMLANMAZ — marka
  kimliği kaybolur. Okunması gereken metin ondan değil `--metin`'den
  gelmeli.
- **SABİT RENK KURAL GÖVDESİNDE KALIRSA KOYU TEMADA O KURAL AÇIK KALIR —
  ve bu SESSİZDİR.** Sayfanın çoğu koyu, bir kutu beyaz; ya da daha
  kötüsü koyu metin koyu zeminde **görünmez** olur (4.6AB'de iki kuralda
  tam bu vardı). Renkleri belirtece çevirdikten sonra kural gövdelerinde
  hex kalmadığını ÖLÇ. ⚠️ Ölçerken CSS yorumlarını ayıkla: yorumdaki renk
  kodu hiçbir şey boyamıyor, testi boşuna kırar.
- **RENK BELİRTECE ÇEVİRİRKEN TARAYICI VARSAYILANLARI TARAMAYA GİRMEZ.**
  4.6AB'de "kural gövdesinde sabit renk kalmadı" testi yeşildi ama genel
  bir `a { color }` kuralı **hiç yoktu**: stillenmemiş bağlantılar
  tarayıcının varsayılan mavisine düşüyordu (`#0000ee`) — koyu temada
  kontrast **1.72**. ⚠️ Belirti sinsi: hiçbir kural YANLIŞ değil, EKSİK.
  Yazılmış renkleri taramak yetmiyor; **temel öğelerin (bağlantı, form
  kontrolü) kendi kuralı var mı** diye ayrıca bak.
- **KONTROL SINIRI ile AYRAÇ AYNI BELİRTEÇ OLMAZ.** WCAG 1.4.11 form
  kontrolünün sınırı için **3:1 zorunlu** tutuyor; dekoratif ayraç sakin
  kalabilir. Tek belirteç kullanılırsa ya kontroller görünmez ya ayraçlar
  gürültülü olur. 4.6AD'de ölçüldü: tasarımdaki **her** çizgi 3:1'in
  altındaydı, arama kutusunun sınırı 1.43'tü — az gören müşteri kutuyu
  bulamıyordu.
- **Tailwind v4'te `@theme inline` ŞART — düz `@theme` çalışma anında temayı
  KIRAR.** Düz biçim değişkenin **değerini kopyalıyor**: üretilen sınıf açık
  temanın rengini taşır ve `data-tema` değişince **hiçbir şey olmaz**. `inline`
  değişkene **referans** bırakır. ⚠️ Belirti tamamen sessiz — derleme başarılı,
  sayfa açılır, yalnızca renk değişmez (4.6AE).
- **SABİT RENK SINIFINA `dark:` İKİZİ YAZMAK ÇÖZÜM DEĞİL.** Panelde 532 sabit
  Tailwind renk sınıfı vardı; her birine ikiz yazmak 532 karar demekti ve biri
  unutulduğunda **hata vermeden** okunmaz kalırdı. Renk **belirteçten** okunur,
  belirteç temaya göre değişir (4.6AE).
  ⚠️ `text-white` **bağlama duyarlıdır**: koyu vurgu zemini üstünde beyaz
  DOĞRUDUR: toptan çevirmek onu bozar. Yalnızca açık temada koyu zeminle
  eşleşenler belirtece taşınır.
- **KOYU TEMA MEVCUT KUSURU GÖRÜNÜR KILAR — bulunan her kusur o bloğun değildir.**
  4.6AE'de düğme yazısının kontrastı 3,56 ölçüldü; değer **bloktan önce de**
  öyleydi. Koyu tema onu ortaya çıkardı, yaratmadı. Vurgu `#ea580c` → `#c2410c`.
- **TABLOYU KABA ALMAK TEK BAŞINA YETMEZ — `min-w-0` da gerekir.** Flex
  çocuğunun varsayılan en küçük genişliği İÇERİĞİ kadardır; ana sütuna
  `min-w-0` konmazsa geniş tablo sütunu şişirir, `overflow-x-auto` kabı hiç
  daralmaz ve **sayfanın tamamı** yatay kayar (4.6AF).
- **ETKİN/SEÇİLİ DURUMDA ZEMİN İLE ÜSTÜNDEKİ METİN TERS YÖNDE ÇEKİYOR.**
  Zemin görünür oldukça vurgulu metnin kontrastı düşüyor. 4.6AF'de açık
  temada en açık tonda bile **4,47** çıktı — eşiğin altında. Çözüm vurgulu
  metinden vazgeçip **güçlü metin + vurgu çubuğu** kullanmak: metin 13,32,
  çubuk 3,94 (WCAG 1.4.11 non-text eşiği).
  ⚠️ Çubuk için `--p-vurgu` DEĞİL `--p-vurgu-metin`: birincisi düğme ZEMİNİ
  olduğu için iki temada da aynı koyu turuncu ve koyu temada **1,99**'a
  düşüyor. Vurgunun "zemin" ve "ön plan" biçimleri AYRI belirteçtir.
- **PEKİŞTİRME AMAÇLI TİNT'İ DE ÖLÇ — WCAG sayı vermiyor diye ölçümsüz
  kalmasın.** 4.6AF'de etkin maddenin zemini koyu temada yüzeyle **1,04**
  kontrasttaydı, yani hiç görünmüyordu; durum çubukla anlatıldığı için
  WCAG ihlali değildi ama **pekiştirme görünmüyorsa hiç yok demektir**.
  Testler zemini yüzeye karşı hiç ölçmediği için eski değeri geri koymak
  hiçbir testi düşürmüyordu.
- **KOŞULSUZ ÇOK SÜTUNLU IZGARA MOBİLDE İÇERİĞİ EZER — bedeli taşma değil
  SIKIŞMA.** `grid-cols-2` kırılma noktası olmadan yazılınca 375px'te de iki
  sütun kalıyor. 4.6AF.1'de ölçüldü: Personel'de iki tablo **118px'lik iki
  sütuna** giriyordu. ⚠️ Yatay taşmayı ölçen tarama bunu **göremez** —
  sıkışan içerik taşmıyor, sadece okunmuyor.
  ⚠️ Ararken `grep -v "sm:\|md:\|lg:"` YETMEZ: aynı satırda meşru bir
  `sm:grid-cols-4` varsa çıplak `grid-cols-2` elenir. Öneki **hemen soldan**
  oku.
- **`flex-1` DARALMAYA İZİN VERMEZ — `min-w-0` gerekir.** `min-w-0` ile aynı
  aile (ana sütun, 4.6AF): flex çocuğunun varsayılan en küçük genişliği
  içeriği kadardır, `flex-1` bunu değiştirmiyor. Girdi kendi içeriği kadar
  yer kaplayıp satırı taşırıyor (4.6AF.1).
- **`el.focus()` `:focus-visible`'I TETİKLEMİYOR — odak stilini ÖLÇTÜĞÜNÜ
  sanan test yanlış şey ölçer.** 4.6AG'de iki yüzeyde birden "odak halkası
  yok" okundu; gerçek **Tab** ile ölçünce panelde tarayıcının varsayılan
  halkası çıktı. Gerçek durum farklıydı: halka VAR ama **yazılmamış** —
  rengi ve kalınlığı bizde değil, tarayıcıdan tarayıcıya değişiyor ve koyu
  yüzeye karşı kontrastı garanti değil. ⚠️ Bir erişilebilirlik bulgusunu
  kaydetmeden önce **gerçek klavye girdisiyle** doğrula; `.focus()` ile
  ölçüp "WCAG ihlali" yazmak yanlış kayıt üretir.
  ⚠️ Kuralı yazarken `:focus` DEĞİL `:focus-visible`: `:focus` fareyle
  tıklanan her düğmeye halka takar ve marka bunu arıza sanar.
- **KOYU TEMADA GÖLGE YOK HÜKMÜNDEDİR — derinlik YÜZEY AÇIKLIĞIYLA
  anlatılır.** Gölge kontrastla görünür; koyu zeminde koyu gölge
  görünmez. İki temaya da gölge konsaydı açık temada derinlik olur, koyu
  temada **hiçbir şey** olmazdı — ve bu hata vermezdi (4.6AG). Bizde
  `yuzey / yuzey-2 / yuzey-3` zaten var; gölge belirteci koyu temada
  `none`.
- **TAILWIND'İN ÖLÇEĞİNİ EZ, SINIFLARA DOKUNMA.** 4.6AG'de `--radius-lg`
  ve `--radius-xl` yeniden tanımlanınca **163 kullanım tek yerden**
  güncellendi. Sınıf sınıf dokunulsaydı biri unutulduğunda hata vermeden
  eski değerde kalırdı. ⚠️ **Çıplak `rounded` bunun DIŞINDA:** o sınıf
  değişkene değil sabit `.25rem`'e bağlı, yani belirteci değiştirmek onu
  düzeltmiyor — ölçek kurarken çıplak biçimi ayrıca ara.
- **`transition: all` YASAK.** Yerleşim özellikleri de animasyona girer ve
  tablo satırları kayarken sürüklenir. Yalnızca etkileşimde değişen
  özellikleri say (renk, kenar, gölge, dönüşüm, opaklık). ⚠️ Yanına
  `prefers-reduced-motion` koruması: hareket duyarlılığı olan personel
  için animasyon rahatsızlık değil **engel** (4.6AG).
- **"FERAHLATALIM" BİR TASARIM TERCİHİ DEĞİL, İŞ KARARIDIR.** Sipariş
  listesinde 50+ kayıt olabiliyor; nefes payı ile "bir ekranda kaç satır
  görüyorum" doğrudan çelişiyor. 4.6AG'de marka yoğunluğu seçti:
  tipografi büyüdü, **dolgu büyümedi**. Kararı ölçen test var — yoksa
  ileride biri sessizce geri alır.
- **DERİNLİK EKLERKEN "HER KABA GÖLGE" ÖNCEKİ BİR KARARI SESSİZCE GERİ
  ALIR.** Ürün kartı 4.6AD'de bilerek çerçevesiz bırakılmıştı (sakin D2C
  dili); 4.6AH'de gölge dağıtılsaydı o karar kaybolurdu ve kimse fark
  etmezdi. Gölge yalnızca **gerçekten yükseltilmiş** yüzeye (sticky bar,
  özet paneli, açılır liste). Korunacak kararın kendi testi olmalı.
