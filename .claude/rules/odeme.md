---
paths:
  - "app/Domain/Payment/**"
  - "app/Http/Storefront/Payment*"
  - "resources/views/storefront/**/odeme*"
---

# Ödeme tuzakları

Ödeme akışında bir hatanın bedeli iki katı: müşteri parasını ödemiş
olabilir ve **bağlı stok 60 dakika kimseye satılamaz**. Buradaki
maddelerin çoğu dış servisin cevabını yanlış okumaktan doğdu.

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **Dış servisin "başarılı" demesi, İSTEDİĞİNİ yaptığı anlamına gelmez.**
  iyzico iadesinde `status: success` döndü ama `price` istenenden düşüktü
  (249,90 istendi, 200 döndü; sebep kesinleşmedi). Kayıtta tam iade
  yazarken müşteriye eksik para gitmiş olurdu. Kural: cevabın **durumuna
  değil sonucuna** bak — tutar, adet, kimlik neyse onu karşılaştır.
- **"Çağrı başarısız" ile "işlem başarısız" AYRI ŞEYLERDİR.** Dış servisler
  ikisini de aynı alanla bildirebiliyor. iyzico yetersiz bakiyede servis
  düzeyinde de `status: failure` döndürüyor; ama `paymentStatus` alanı
  cevapta VAR — yani çağrı başarılı, ödeme başarısız. Ayrım yapılmayınca
  başarısız ödemenin bildirimi 502 aldı: sipariş `pending` kaldı, bağlı
  stok 60 dakika kimseye satılamadı ve müşteri neden reddedildiğini
  öğrenemedi. Kural: cevapta **işlemin kendi durumu** varsa o bir
  *sonuçtur*, hata değil.
- **DOĞRULAMAMIZ DIŞ SERVİSTEN GEVŞEK OLAMAZ.** Laravel'in `email` kuralı
  `a@a` ve `a@aa` kabul ediyor; iyzico reddediyor (*"email hatalı format
  ile gönderilmiştir"*). ⚠️ Bedeli ZAMANLAMA: doğrulama geçtiği için
  **sipariş oluşuyor**, stok bağlanıyor ve ödeme ondan SONRA patlıyor —
  bağlı stok 60 dakika kimseye satılamıyor. `App\Rules\DeliverableEmail`
  alan adında nokta + en az iki harflik TLD arıyor. ⚠️ DNS sorgusu
  YAPILMIYOR: ödeme akışında ağa çıkmak isteği yavaşlatır ve ağ
  kesintisinde satışı durdururdu (4.5C'de tek sorgu 24 sn sürmüştü).
- **Ödeme formu IFRAME içinde — sağlayıcının HAZIR BETİĞİ kullanılmaz.**
  iyzico hem `checkoutFormContent` (yapıştırılacak `<script>`) hem
  `paymentPageUrl` veriyor. Betik seçilseydi sağlayıcının JavaScript'i
  **bizim kökenimizde** çalışır ve kart alanları bizim DOM'umuzda olurdu —
  PCI kapsamını daraltma amacının tersi. Doğrusu `paymentPageUrl` +
  `&iframe=true` ile ADRESİ gömmek (4.5-K1). ⚠️ Dönüş sayfası
  **çerçeveden çıkmalı** (`window.top`, `window.parent` değil); çıkmazsa
  müşteri "sipariş alındı" ekranını küçük bir çerçevede görür.
- **ÇERÇEVEDEN ÇIKIŞ BETİĞİ, İÇİNDE BULUNDUĞU ADRESE GERİ GİDEMEZ.**
  Sağlayıcı dönüşü `POST` ve referans **gövdede**; `window.top.location =
  window.location.href` üst pencereyi **referanssız bir GET**'e götürüyor
  ve müşteri, ödemesi başarılı olmasına rağmen **404** görüyor (4.5R).
  Doğrusu POST'u **303 ile GET'lenebilir bir sonuç adresine**
  yönlendirmek; o adres imzalı olmalı, yoksa uuid'i ele geçiren başkasının
  ödeme durumunu okur. ⚠️ **Sahte sağlayıcı bunu İKİ KEZ gizledi**
  (1E.7.3 · 4.5R): referansı adres çubuğuna koyduğu için testler `?ref=`
  ile koşuyor ve betik çalışıyordu. Dönüş akışını sınayan test
  **sağlayıcının gerçek şekliyle** (POST + gövde) da koşmalı.
- **GENİŞ BİR CSP, DİNAMİK İFRAME ADRESİNİ SESSİZCE KIRAR.** Ödeme sayfası
  kendi iframe'inde iyzico'yu gösteriyor (4.5-K1) ve o adres iyzico'nun API
  cevabından **dinamik** geliyor — sabit bir alan adı olarak `frame-src`
  izin listesine yazılamaz. `default-src`/`script-src` içeren bir
  `Content-Security-Policy` eklenseydi (4.6U), yanlış tahmin edilen bir
  domain müşterinin ödeme adımının ortasında **sessizce boş bir çerçeve**
  görmesi demekti. ⚠️ `frame-ancestors` bu riski TAŞIMIYOR: yalnızca
  BİZİM sayfamızın BAŞKASINCA çerçevelenmesini kapatıyor, bizim
  başkasını çerçevelememizi etkilemiyor — ikisi ayrı yön. Clickjacking
  koruması eklenecekse dinamik iframe barındıran bir projede yalnızca
  `frame-ancestors`/`X-Frame-Options` kullan, `default-src` ekleme.
- **KENDİNİ YENİLEYEN EKRANIN SINIRI VE DEPO KORUMASI OLMALI.** Ödeme
  kalıcı olarak `pending` kalabiliyor; sınırsız yenileme müşterinin
  sekmesini sonsuza kadar döndürür. ⚠️ Sayaç `sessionStorage`'daysa
  **gizli sekmede istisna atabilir** — o durumda sayaç tutulamaz ve sayfa
  yine sonsuza kadar yenilenir. Depo yoksa otomatik yenileme **hiç
  başlamamalı**. ⚠️ Terminal durumda sayaç temizlenmeli, yoksa aynı
  tarayıcı oturumundaki İKİNCİ ödemede yenileme hiç çalışmaz (4.6AK).
