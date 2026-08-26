# TıkMarka

[![CI](https://github.com/yakupcinar/TikMarka/actions/workflows/ci.yml/badge.svg)](https://github.com/yakupcinar/TikMarka/actions/workflows/ci.yml)

Tek markanın kendi müşterisine sattığı e-ticaret uygulaması (D2C) — **çok kiracılı**
kurulmuş: aynı kod tabanı N markaya hizmet eder, her marka kendi alan adında, kendi
verisiyle.

Pazaryeri **değil**. Satıcı hesabı, komisyon, hakediş, sepetin satıcılara bölünmesi yok.

> 🚧 **Geliştirme aşamasında.** Şu an Faz 0 (altyapı). Ayrıntılı yol haritası: [`PLAN.md`](PLAN.md)

---

## Mimari

```
  tarayıcı
     │ https://markam.com
     ▼
   caddy ────────▶ app (php-fpm 8.4) ──┬──▶ postgres 17    tek db, marka başına şema
   TLS · public/   Laravel 12          ├──▶ redis          cache + kuyruk
                                       └──▶ mailpit        yerel mail (dev)
                   worker ─────────────┘
                   queue:work · aynı imaj
```

**Kiracı ayrımı şemayla yapılır, `tenant_id` kolonuyla değil.**

```
  tek PostgreSQL veritabanı
  ├── public          merkez: kiracılar, alan adları, abonelikler
  ├── tenant_amarka   products · orders · customers · settings
  └── tenant_bmarka   (aynı tablolar, ayrı şema)

  İstek geldiğinde alan adına bakılır ve search_path o markanın şemasına
  kilitlenir. Diğer şema o istek süresince yok hükmündedir — sorgu yanlış
  yazılsa bile başka markanın verisi görünmez.
```

Kararların gerekçeleri: [`docs/pre-setup.md`](docs/pre-setup.md) ·
Veri modeli: [`docs/domain-model.md`](docs/domain-model.md) ·
Tek sayfalık özet: [`docs/summary.md`](docs/summary.md)

---

## Kurulum

Gereken tek şey **Docker**. Yerel PHP, Composer veya PostgreSQL kurulumu gerekmiyor.

```bash
git clone https://github.com/yakupcinar/TikMarka.git
cd T-kMarka
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

Sonra: **https://marka-a.localhost**

> Sertifika uyarısı normaldir — yerelde Caddy'nin kendi iç otoritesi kullanılıyor
> (Let's Encrypt `.localhost` adreslerine sertifika veremez).

**Servisler**

| Adres | Ne |
|---|---|
| https://marka-a.localhost · https://marka-b.localhost | Kiracı alan adları |
| http://localhost:8025 | Mailpit — giden mailler burada yakalanır |
| `localhost:5433` | PostgreSQL (host portu 5433, konteyner içi 5432) |

---

## Komutlar

```bash
docker compose exec app composer lint       # biçimlendir (Pint)
docker compose exec app composer analyse    # statik analiz (Larastan, seviye 8)
docker compose exec app composer test       # testler (Pest)
```

Testler ayrı bir veritabanında (`tikmarka_test`) ve **PostgreSQL üzerinde** koşar —
SQLite'ta değil, çünkü şema, `citext`, `jsonb` ve `SELECT FOR UPDATE` orada yok.

---

## Teknoloji

| Katman | Seçim | Neden |
|---|---|---|
| Backend | PHP 8.4 · Laravel 12 | — |
| Veritabanı | PostgreSQL 17 | şema bazlı kiracılık · `jsonb` · `citext` · satır kilidi |
| Kiracılık | `stancl/tenancy` (şema modu) | izolasyon yapıdan gelir, kod disiplininden değil |
| Cache / kuyruk | Redis | — |
| Ters vekil | Caddy | özel alan adları için on-demand TLS |
| Kalite | Pint · Larastan (8) · Pest | — |

Arayüz teknolojisi henüz **seçilmedi** — backend çalışır hâle gelene kadar erteleniyor
(karar M-3). Geliştirme boyunca "gözümüz" testler.

---

## Durum

```
  Faz 0  altyapı + kiracılık zemini    ▓▓▓▓▓▓▓░░░   0.1–0.4 bitti
  Faz 1  çekirdek mağaza               ░░░░░░░░░░
  Faz 2  olgunlaşma                    ░░░░░░░░░░
  Faz 3  satılabilirlik                ░░░░░░░░░░
  Faz 4  arayüz                        ░░░░░░░░░░
  Faz 5  entegrasyonlar                ░░░░░░░░░░
  Faz 6  dağıtım                       ░░░░░░░░░░
```

---

## Lisans

Tüm hakları saklıdır. Kod herkese açık olarak görüntülenebilir; kullanım, kopyalama
veya dağıtım için izin gerekir.

## İyileştirme

* Vitrinde bir ürünü sepete aldım sonra Panelde ürünü sildim şimdi vitrin sepetinde variant uuid alanı zorunludur diye bir hata aldım, ürün üstü silik bir şekilde isimsiz duruyordu, Bu yaşanabilecek bir durum gerçekte bunu çözmemiz lazım, ama anlamadığım şey şu biz soft delete atmıyor muyuz silerken ne yapıyoruz ne yapmamız doğru araştır e ticaretleri karar verelim.

* Yeni ürün açtım adı "a" 2 varyant verdim depolaması farklı fiyatları 50 100 olacak şekilde ana sayfaya baktım ürünün fiyatı 50 diye gözüküyor bence bu mantıklı sonuçta ürünün en düşük depolu hali öyle; sonra ürüne tıkladım ve içindeki deposu büyük olana tıkladım fiyat hala 50 gösterdi ama fiyatı 100 tl bu fiyat tıkladığım varyanta göre fiyatı alsın standart ana sayfada en ucuz varyantı göstersin.

* Yeni ürün açtım adı "a" 2 varyant verdim depolaması farklı fiyatları 50 100 olacak şekilde ana sayfaya baktım ürünün fiyatı 50 diye gözüküyor bence bu mantıklı sonuçta ürünün en düşük depolu hali öyle; sonra ürüne tıkladım ve içindeki deposu büyük olana tıkladım fiyat hala 50 gösterdi ama fiyatı 100 tl bu fiyat tıkladığım varyanta göre fiyatı alsın standart ana sayfada en ucuz varyantı göstersin ama ürü

* Mailime bu düştü ama neden bu düştü anlamadım işlemleri mi sanki zaten maili doğru kaydettiğim hesaptan yapıyorum bu şaşırttı şimdi: "Address not found Your message wasn't delivered to vazgec@marka-a.localhost because the domain marka-a.localhost couldn't be found. Check for typos or unnecessary spaces and try again"

* Şirket Panelinde aşağıdan sayfa atlamak pagination next yazıyor sadece sayılar 1 2 3 ... istiyorum.

* Vitrinde ödemeyi yapıp siparişi veriyorum çıkan ekran biraz beklemem ödeme onaylanana kadar iyzicodan mı bekliyor onayı sonra yeniledim sayfayı o zaman ödeme başarılı dedi, siparişim hazırlanıyor ve afiyet olsun gibi mesajlar gördüm ama bizim ürünlrimiz de yemek yok neden öyle dedin düzelt oraları ayrıca ödeme sonrası bekleme sekmesinde hesabım yazısı yerine giriş, gözüküyor onu da düzeltelim.

* ~~UI/UX yenilemesi (modern SaaS estetiği)~~ → **4.6AG'de kapandı**

> Sezgi doğruydu ve ölçülebildi: panelde **gölge 0**, **yazılmış odak
> stili 0**, 25 sayfaya **18 hover**, **4 farklı yarıçap** (vitrinde 6) ve
> tipografide **16px hiç yok** — her şey 14px, sonra doğrudan 24px'e
> atlıyordu. Hiyerarşi yoktu.
>
> Yapılanlar belirteç üzerinden, 25 dosyaya tek tek dokunmadan: **ölçek**
> (16px geri geldi, 12px üstveriye çekildi, tutarlar hizalandı),
> **yarıçap** (3 basamak), **derinlik** (açık temada gölge, koyu temada
> yüzey basamağı) ve **etkileşim** (yazılmış odak halkası, geçiş,
> `prefers-reduced-motion`).
>
> ⚠️ **İki istek bilerek yapılmadı, gerekçesiyle:** panele **bento-grid**
> konmadı (bento pazarlama kalıbı; panel bir veri aracı ve bento mobilde
> çok sütun sorununu geri getirir) ve **palet yeniden seçilmedi**
> (4.6AD/AE/AF'de her renk tarayıcıda ölçülüp eşiğe oturtulmuştu).
>
> ⚠️ **Yoğunluk korundu** — marka kararı: tipografi büyüdü, satır dolgusu
> büyümedi. 50+ kayıtlı bir listede "nefes payı" ile "ekranda kaç satır
> görüyorum" doğrudan çelişiyor.
>
> Tasarım önce tuvalde gösterildi, sonra uygulandı.



* ~~samil.localhost açılmıyor~~ → **4.6Z'de kapandı** (Caddyfile artık joker kullanıyor; yeni marka için elle ekleme gerekmiyor)

* ~~Görseller WebP'ye çevrilmiyor~~ → **4.6AA'da kapandı** (üstelik 2–5 MB arası dosyalar hiç yüklenemiyormuş, o da düzeldi)

* ~~Ödeme ekranından çıkış yolu yok~~ → **4.6Z'de kapandı** (iptal düğmesi + ürünler sepete dönüyor)

> ⚠️ **Otomatik iptal YAPILMADI** — bilerek. Müşteri meşru sebeplerle
> ayrılıyor (sözleşmeyi okumak, karta bakmak, banka SMS'i beklerken
> uygulama değiştirmek); otomatik iptal bunların hepsini sipariş kaybına
> çevirirdi. Terk edilen siparişi rezervasyon süresi zaten topluyor.

* ~~Vitrinde favorileme yok~~ → **4.6D'de kapandı**

* ~~Ödeme dönüş ekranında duruma göre bağlantı yok~~ → **4.6Y'de kapandı**

* ~~Panelde Müşteri sekmesi~~ → **4.6AC'de kapandı** (`customer.view` izni Faz 1'den beri tanımlıydı ama hiçbir rota kullanmıyordu — o da düzeldi)

* ~~Mobil-tablet uyumu ve dark mode~~ → **4.6AB'de (vitrin) ve 4.6AE'de (panel) kapandı**

> ⚠️ Panel ayrı bir sistem (Tailwind) ve vitrinin temasından bilerek
> bağımsız — bu yüzden ayrı blokta yapıldı. Tema seçimi de ayrı
> saklanıyor: panel bizim arayüzümüz, vitrin markanın.
>
> ✅ **Görsel doğrulama artık yapılıyor.** 4.6AB'de "araç yerel sertifikalı
> adrese ulaşamıyor" diye yapılamamıştı; **ngrok tüneli** bunu çözdü ve
> 4.6AD/4.6AE'de renkler tarayıcıda tek tek ölçüldü. Ölçüm, görsel
> kararları ilk kez **sınanabilir sayıya** çevirdi — bloktan önce var olan
> bir kusur (düğme yazısı 3,56) böyle bulundu.

* Lazy Loading (Tembel Yükleme): Ana sayfada çok fazla ürün listeleneceği için aşağı kaydırdıkça ürünlerin yüklenmesini sağla. Bu, uygulamanın açılış hızını uçurur.

* Ana sayfadaki ürünler direkt her kullanıcı önüne konmuş bir algoritma mantığı yapalım sizin ilginizi çekebilecekler, popüler ürünler(en çok tıklanan ürünler), yeni gelen ürünler, etc. yani ana sayfa yapalım e ticaret applerinde yaptıkları gibi.



> Açık kusurlar ve fikirler. Biten maddeler **silinmiyor** — aşağıdaki
> "Yapıldı" bölümüne taşınıyor ki tekrar kontrol edilebilsin.



### Açık kusurlar

**Güvenlik — ucuzdan pahalıya, sırayla ele alınıyor** (kullanıcı isteği: "ucuzdan pahalıya sırayla ekle")

* [x] ~~Kupon/yorum/iade uçlarında hız sınırlaması yok~~ → **4.6T'de kapandı**
* [x] ~~Güvenlik başlıkları yok~~ → **4.6U'da kapandı**
* [x] ~~Şifre sıfırlama akışı yok~~ → **4.6V'de kapandı** (Gmail SMTP bağlandı)
* [x] ~~E-posta doğrulama yok~~ → **4.6W'de kapandı** — güvenlik listesi tamam

> ⚠️ Doğrulama **yumuşak kapı**: ödeme engellenmiyor, yorum yazma
> engelleniyor. Gerekçe ölçüldü — `/odeme` kimlik istemiyor (misafir
> ödemesi açık), yani ödemeye kapı koymak hesap açanı cezalandırır ve
> saldırganı durdurmaz.
>
> ⚠️ Personel kapsam dışı: oradaki gerçek ihtiyaç doğrulama değil
> **davet akışı** (personel kendi şifresini kurar) — ayrı blok.

**Merkez yönetim**

* ⚠️ **Geliştirmede** yeni marka alan adı hâlâ `docker/Caddyfile`'a elle ekleniyor — Let's Encrypt `.localhost` adreslerine sertifika veremediği için on-demand TLS yerelde devreye giremiyor. **Üretimde gerekmiyor** (4.5N).

### Fikirler → **Faz 4.6 olarak planlandı** (`PLAN.md`)

| # | Fikir | Blok |
|---|---|---|
| 1 | ~~Varyantlar sıralı kutucuklardan seçilsin…~~ → **4.6A'da yazıldı, 4.6A.1'de İKİNCİ DÜZENE de uygulandı** | **4.6A** |
| 2 | ~~Ürün sayfasının altında benzer ürünler, beğenilenler~~ → **4.6E'de kapandı** ("beğenilenler" yerine **çok satanlar** — beğeni verisi 4.6F'de gelecek) | **4.6E** |
| 3 | Ürüne tıklamayı sayma, kullanıcı başına veri; panelde bölüm | **4.6F** |
| 4 | ~~Vitrinde ürün favorileme~~ → **4.6D'de kapandı** | **4.6D** |
| 5 | Büyük e-ticaret sitelerinin niş özelliklerini tespit et | **4.6G** |

**Planlama sırasında ölçümle bulunan iki eksik de fazın içine alındı:**

| Eksik | Blok |
|---|---|
| ~~Vitrinde **kategori gezinme sayfası yok**~~ → **4.6B'de kapandı** | **4.6B** |
| ~~**Yorumlar vitrinde hiç görünmüyor**~~ → **4.6C'de kapandı** | **4.6C** |

> ⚠️ Ayrıca ölçüldü: `ProductViewed` olayı **yalnızca API'den** yazılıyor,
> vitrin sayfası hiç kaydetmiyor — bugünkü sayılar eksik. Ve
> `Anonymizer`/`DataExporter` **olayları kapsamıyor**; müşteri başına
> davranış verisi tutmadan önce KVKK tarafı genişletilmeli (4.6F).

---

## Yapıldı

> ⚠️ Buradaki maddeler **ölçüldü ve kırma denemesinden geçti**, ama liste
> silinmiyor: kendin de kontrol edebilesin diye nerede sınayacağın yazılı.

| Kusur | Nerede kontrol edilir | Blok |
|---|---|---|
| Kurallı koleksiyon "kural bir nesne olmalı" diyordu | Panel → Koleksiyonlar → tür "Kurallı" seç, koşul editörü **oluşturma formunda** açılır | 4.5H |
| Koleksiyonların vitrinde kullanıldığı yer yoktu | `/koleksiyonlar` ve `/koleksiyon/{slug}`; başlıktaki bağlantı yalnızca aktif koleksiyon varsa görünür | 4.5H |
| Kategori kuralı yazınca koleksiyon 404 veriyordu | Kural değerinde kategori artık **listeden seçiliyor**; kategorisi silinse bile sayfa düşmüyor | 4.5H.1 |
| Adres kaydı "başlık alanı zorunludur" diyordu, ekranda yeri yoktu | Vitrin → Hesabım → Adresler → "Ev, İş…" alanı | 4.5G |
| `a@a` doğrulamayı geçiyor, iyzico reddediyordu | Ödemede `a@a` yaz → **sipariş oluşmaz**, "alan adı geçersiz görünüyor" | 4.5G |
| Ödeme hatası tarayıcıya ham JSON dönüyordu | Ödeme başlatılamadığında ekranda **Türkçe mesaj**; API'ye hâlâ JSON | 4.5G |
| Vitrinde verdiğim siparişleri göremiyordum | Giriş yap → sipariş ver → Hesabım → **Siparişlerim**'de görünür | 4.5I |
| Kayıtlı adres ödemede sorulmuyordu / "line" uyarıları veriyordu | Adres kaydet → Ödeme → **liste + seçim**, "Başka adrese gönder" formu açar | 4.5I |
| Kayıtlı adres seçiliyken "shipping.full_name metin olmalıdır" uyarısı | Adres seç → Öde → **doğrudan ödeme ekranına** gider | 4.5I.1 |
| Panelden sipariş durumu güncellenemiyor, iade açılamıyor | Panel → Siparişler → sipariş → **"Siparişi tamamla"** ve **"İade talebi aç"** | 4.5L |
| Manuel koleksiyona ürün eklenemiyor | Panel → Ürünler → ürün → **"Koleksiyonlar"** kutucukları (koleksiyon ayrıntısındaki seçici de duruyor) | 4.5L |
| İkinci varyant bozuk sayfa açıyor, eksen eklenemiyor | Panel → Ürünler → ürün → **"Varyant eksenleri"** → Renk seç → kaydet → her renk için varyant | 4.5L |
| Vitrinde iade seçeneği yok | Vitrin → Hesabım → sipariş → **"İade"** bölümü (adet + cayma/kusurlu + açıklama) | 4.5K |
| Sepet sayacı sepetle uyuşmuyor, bekleyen siparişler birikiyor | Vitrin → giriş → sepete ekle → **rozet ile sepet aynı** · Hesabım → bekleyen siparişte **"Ödemeyi tamamla" / "iptal et"** | 4.5J |
| Sipariş panele düşmemiş / saati yanlış | Vitrin → Hesabım → sipariş saati artık **mağaza saat diliminde** (panel zaten doğruydu) | 4.5M |
| Yeni marka isteğini onay/red edeyim | Merkez → `/yonetim/markalar` → başvuru **`pending`** görünür → **Onayla / Reddet** (onayda deneme başlar) | 4.5N |
| Stok yetmeyince ödeme ham JSON döndürüyor | Sepet → stok bağlıyken öde → **sepete döner, Türkçe mesaj** (JSON yok) | 4.5O |
| Tünelden ödeme tamamlanıyor mu | `make kaldir` → gerçek 3DS/SMS akışı — kullanıcı doğruladı: **ödeme tamam, vitrinde "ödendi"** (dönüş ekranı 4.5R'de düzeltildi) | 4.5M |
| Eksen kaydetmeden varyant ekleyince bozuk sayfa | Panel → ürün → eksen seçmeden **düğmeler kapalı**; boş değerle denersen *"Her varyant ekseni için bir değer seçin."* | 4.5P |
| Ürün oluşturunca varyant/görsel gelmiyor | Panel → Ürünler → Yeni → Oluştur → bölümler **aynı anda** gelir | 4.5L · 4.5P |
| Panelde arama kelime ortasından eşleşiyor | Panel → Ürünler → ara: **`iş` boş döner**, `cüz` → Deri Cüzdan | 4.5P |
| Ödeme sonrası çerçevede açılamayan sayfa | Ödemeyi tamamla → çerçeve kapanır, **"Siparişiniz alındı"** ekranı gelir (POST → 303 → imzalı sayfa) | 4.5R |
| Eksen kaydetmeden varyant tablosuna yazılabiliyor | Panel → ürün → eksen kaydedilmeden **SKU/fiyat/stok kutuları kapalı** | 4.5S |
| 5 eksen birden kaydedilince seçenekler gelmiyor | Panel → ürün → 3'ten fazla işaretlenemiyor; denersen *"Bir üründe en fazla 3 eksen olabilir…"* | 4.5S |
| Merkez marka araması kelime ortasından eşleşiyor | `localhost/yonetim/markalar` → **`ark` boş döner**, `marka` → üç marka | 4.5S |
| Kupon/yorum/iade uçlarında hız sınırlaması yoktu | Kuponu 11 kez art arda dene → **11.'de "Too Many Requests"** | 4.6T |
| Güvenlik başlıkları yoktu (clickjacking, MIME koklama, referrer sızıntısı) | Herhangi bir sayfada tarayıcı geliştirici konsolu → **Network** → başlıklarda `X-Frame-Options: SAMEORIGIN` görünür | 4.6U |
| Şifre sıfırlama akışı yoktu | Vitrin → Giriş → **"Şifremi unuttum"** · Panel → `/yonetim/giris` → **"Şifremi unuttum"** | 4.6V |
| E-posta doğrulama yoktu | Kayıt sonrası posta gelir · **Hesabım** sayfasında şerit + "yeniden gönder" · doğrulamadan **yorum yazılamaz**, alışveriş etkilenmez | 4.6W |
| Aynı SKU verilince ham veritabanı hatası çıkıyordu | Panel → ürün → varyant → var olan bir SKU yaz → **SKU kutusunun altında Türkçe uyarı** (ham 500 değil) | 4.6X |
| Silinmiş bir varyantın SKU'su neden "dolu" belli değildi | Silinen varyantın kodu **rezerve kalıyor** (dış sistemlerle ortak dil) ve mesaj bunu açıkça söylüyor: *"silinmiş bir varyanta ait"* | 4.6X.1 |
| Ödeme dönüş ekranında duruma göre bağlantı yoktu | Ödeme başarılıysa **"Siparişimi görüntüle"** (yalnızca hesap sahibine) · başarısızsa **"Ürünleri sepete geri koy"** — alınamayan ürün varsa adıyla söylüyor | 4.6Y |
| Yeni marka alan adı Caddyfile'a elle ekleniyordu | `tenant:create` sonrası site **doğrudan açılıyor** (joker) — `samil.localhost` ile dene | 4.6Z |
| Ödeme ekranından çıkışın temiz yolu yoktu (stok 60 dk kilitli kalıyordu) | Ödeme sayfası → **"Ödemeden vazgeç ve sepete dön"** — sipariş iptal, stok serbest, ürünler sepette | 4.6Z |
| 2–5 MB arası görseller sessizce reddediliyordu (ekranda `validation.uploaded`) | Panel → ürün → görsel ekle → **5 MB'a kadar kabul ediliyor**, hata olursa Türkçe mesaj | 4.6AA |
| Görseller olduğu gibi saklanıyordu | Yüklenen her görsel **WebP**'ye çevriliyor, en uzun kenar 2048'e iniyor — ölçüldü: 4,83 MB → 0,34 MB | 4.6AA |
| Yorumlar vitrinde hiç görünmüyordu (uçlar ve moderasyon vardı) | Vitrin → ürün sayfası → **Yorumlar** bölümü · satın alıp teslim almış müşteri form görüyor, göremiyorsa **sebebi yazıyor** | 4.6C |
| Vitrinde favorileme yoktu | Ürün sayfası → **♡ Favorilere ekle** · Hesabım → **Favorilerim** · KVKK: anonimleştirmede siliniyor, veri dökümünde listeleniyor | 4.6D |
| Kategoriye vitrinden ulaşılamıyordu | Üst menü → **Kategoriler** · `/k/{slug}` · üst kategori **alt ağacın ürünlerini** gösteriyor · ürün sayfasında kategori yolu | 4.6B |
| Varyant seçicisi yalnızca bir düzendeydi (`vitrinli` markalar düz liste görüyordu) | Ürün sayfası → **eksen kutucukları** her iki düzende de | 4.6A.1 |
| Vitrin koyu tema ve mobil uyumu yoktu | Üst barın sağında **☾/☀ düğmesi** · sistem tercihi de okunuyor · telefon/tablet kırılma noktaları | 4.6AB |
| Ürün sayfasının altında öneri yoktu | **Benzer ürünler** (kategori → marka → en yeniler) ve **Çok satanlar** (ödenmiş siparişlerden) | 4.6E |
| Fiyat ve bağlantılar marka renginde, koyu temada okunmuyordu (kontrast 2,02) | Marka renginin **okunur varyantı** sunucuda hesaplanıyor · fiyat normal metin renginde · her çizgi WCAG eşiğinde | 4.6AD |
| Panel menüsü 14 maddeyle **masaüstünde bile 289px taşıyordu**, telefonda tablolar sayfayı yatay kaydırıyordu | Menü **yan menüye** taşındı (gruplu, etkin sayfa işaretli) · dar ekranda çekmece · 14 tablo kaydırma kabında | 4.6AF |
| Panelde hiyerarşi yoktu: her şey 14px, gölge/odak stili/geçiş yok, 4 farklı yarıçap | Asıl veri **16px**, sütun başlığı etiket, tutarlar hizalı · 3 yarıçap basamağı · açık temada gölge, koyu temada yüzey basamağı · yazılmış odak halkası | 4.6AG |
| Panelde sayfalama düğmelerinde **`pagination.next`** yazıyordu (Türkçe çeviri dosyası hiç yoktu) | Düğmelerde **« Önceki / Sonraki »** | 4.6AF.1 |
| Panel telefonda kullanılamıyordu: 14 sayfanın 5'i yatay kayıyor, ızgaralar içeriği 118px'e sıkıştırıyordu | 375px'te **14 sayfanın 14'ü** temiz · ızgaralar mobilde alt alta, masaüstünde 2–3 sütun | 4.6AF.1 |
| Panelde koyu tema yoktu (renk 25 dosyada **532 sabit sınıf**) | Panel üst barında **☾/☀ düğmesi** · sistem tercihi de okunuyor · renkler belirteçten geliyor | 4.6AE |
| Panelde müşteri sekmesi yoktu (`customer.view` izni ölüydü) | Panel → **Müşteriler** → müşteri → siparişler, favoriler, başarısız ödemeler · salt okunur | 4.6AC |
| Ürün oluşturunca varyant sayfasına gitmiyor | `POST /yonetim/urunler` → `302 → /yonetim/urunler/{uuid}` — **ölçüldü, zaten doğruydu** | 4.5G |


yeni ürün ekledim sku numarası hata verdi sku numarası 'a' idi neyden kaynaklı verdi öyle bir sku numarası gerçekten hali hazırda bir üründe var mı bi kısaca baksana sadece merak ettim ondan mı öyle oldu,

Bu mimaride kaç tane db kullanıldı ve neden.