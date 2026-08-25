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

* "Mevcut projemizdeki çalışan tüm fonksiyonları, state yönetimini ve component bağlantılarını aynen korumanı istiyorum. Hiçbir işlevsel kodu silme veya değiştirme.

Şu anki arayüz bana çok standart ve sıradan geliyor. Senden bir "Kıdemli UI/UX Tasarımcısı" gibi düşünmeni ve projemizi modern bir SaaS uygulaması estetiğine kavuşturmanı istiyorum.

Projenin teknik altyapısına göre en verimli ve temiz çözümü seçmeyi tamamen sana bırakıyorum: Projede Tailwind mi, CSS Variables (Değişkenleri) mı yoksa düz CSS mi kullanmak daha mantıklıysa seçimi sen yap ve o dilde devam et.

Senden ricam, token bütçemizi korumak için tüm kodu baştan yazmak yerine sadece arayüzü elitleştirecek şu estetik dokunuşları TEK SEFERDE (toplu olarak) yapman:

1. Sayfa Düzeni (Layout) ve Boşluklar: Elemanların nefes alması için whitespace (beyaz boşluk) dengesini kur, padding ve margin değerlerini modern web trendlerine uygun şekilde optimize et. İçerikleri bento-grid veya temiz katmanlı yapılarla hizala.

2. Renk Paleti ve Tipografi: Gözü yormayan, soft-contrast (yumuşak kontrastlı) modern bir renk paleti (Primary, Secondary, Background, Surface) belirle. Font boyutları ve ağırlıkları arasındaki hiyerarşiyi netleştir.

3. Modern Detaylar: Buton, input ve kart tasarımlarında sert köşeler yerine modern yumuşatılmış köşeler (border-radius) kullan. Elemanlara derinlik katmak için yumuşak, katmanlı gölgeler (soft shadows) ekle. Etkileşimi artırmak için hover efektleri (smooth transitions) tanımla.

ne düşünüyorsun bu fikrime ona göre ilerleyeceğiz. ayrıca vermemi istediğin bir skill var mı bunu çalıştırırken daha iyi sonuç almamıza yarayacak"


* samil.localhost domaini ekledim marka a'dan ama onla ulaşamıyorum sayfaya doğrulandı diye gösteriyor acaba kodunda eksik mi var ya da cadye dış servisine izin vermek mi lazım manuel ya da localhost tanımladığım için mi ikinci,

* Şirket panellerinde resim ekleme var, paneli kullanan genelde jpeg veya png yükleyeceği için laravelde bunu otomatik sıkıştırılmış optimize edilmiş WebP formatına dönüştüren bir kod bloğu yazalım yoksa.

* Vitrinde kullanıcı ödemeye gidince yukardan menülerden ödemeden çıkabiliyor ama bu ödemeyi iptal ettirmiyor arkada siparişlerde tutuyor en iyisi biz oraya iptal et butonu koyalım sayfada uygun bir yere ödemeyi direkt iptal etsin ama ürünü sepete geri koyalım, ayrıca hatta onu da geçtim direkt başka sayfaya giderse ödemeyi iptal edelim yukardaki panellerden bir şeye tıklasa bile hiç bir daha siparişlerde ödemeyi tamamla veya iptal etle uğraşmayalım sen ne düşünüyorsun önce etrafa bakalım e ticaretler nasıl işlemiş sonra bakarız.

* Vitrin için hesabı olan kullanıcılara favorileme seçeneği koyalım ürünlere ürün içerisinde.

* Vitrin sipariş ekle, ödemeye git web in web gelsin iyzico servisi doğru bilgileri gir ödeme yap tam bu sırada web in web iyzico servisinin olduğu yerde url erişilemiyor yazısı çıkıyor bunu ben nasıl iyziconun panelinden ayarlarım farklı senaryolar için (bu standart değil mi ödeme geçerli ise mesajı direkt iyzico göstermesi gerekmez mi sandboxta mı yok bu özellik).
// Sanırsam ben ödeme bitince iyzicoya kullanıcıyı hangi adrese atacağını söylemediğim için bu oluyor o zaman şöyle yapalım ödemeniz başarılı veya başarız etc. bir adres ekleyelim sayfada duruma göre ödeme başarılı/başarısız diyecek -> başarılı ise sipariş detayına gidebilmesi için altı çizili bir yazı ekleyelim ->başarısza sepete geri git altı çizili bir yazı ekleyelim (sepet kaybolmasın ödeme başarısızsa)

* Şirket paneline Müşteri diye bir sekme ekleyip o kullanıcının siparişlerini(aldığı ürünler toplam harcama vb.), favorilerini, başarız ödeme denemeleri.

* Appimizin frontunu mobil-tablet için de uygun hale getirelim, html şablonu olsun blade olsun, etc. buralarda genel ve ince ayarlara gidelim plan yapalım ayrıca dark mode ekleyelim sağ üst bir yere.

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
| 1 | Varyantlar sıralı kutucuklardan seçilsin; eksen 5'ten fazla değer içeriyorsa açılır liste; stokta olmayan tıklanamaz | **4.6A** |
| 2 | Ürün sayfasının altında benzer ürünler, beğenilenler | **4.6E** |
| 3 | Ürüne tıklamayı sayma, kullanıcı başına veri; panelde bölüm | **4.6F** |
| 4 | Vitrinde ürün favorileme | **4.6D** |
| 5 | Büyük e-ticaret sitelerinin niş özelliklerini tespit et | **4.6G** |

**Planlama sırasında ölçümle bulunan iki eksik de fazın içine alındı:**

| Eksik | Blok |
|---|---|
| Vitrinde **kategori gezinme sayfası yok** (4.5H kapsam testinde bilerek `null`) | **4.6B** |
| **Yorumlar vitrinde hiç görünmüyor** — uçlar (2E) ve panel moderasyonu (4.5F) var, müşteri ulaşamıyor | **4.6C** |

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
| Silinen varyantın SKU'su tekrar kullanılamıyordu (ham veritabanı hatası) | Panel → ürün → varyant sil → **aynı SKU ile yeniden ekle** (artık kabul ediliyor) · canlı iki varyanta aynı SKU verirsen SKU kutusunun altında Türkçe uyarı | 4.6X |
| Ürün oluşturunca varyant sayfasına gitmiyor | `POST /yonetim/urunler` → `302 → /yonetim/urunler/{uuid}` — **ölçüldü, zaten doğruydu** | 4.5G |


yeni ürün ekledim sku numarası hata verdi sku numarası 'a' idi neyden kaynaklı verdi öyle bir sku numarası gerçekten hali hazırda bir üründe var mı bi kısaca baksana sadece merak ettim ondan mı öyle oldu,

Bu mimaride kaç tane db kullanıldı ve neden.