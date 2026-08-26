# Mimari — öğretici

> Bu belge **neden ve nasıl** anlatır, sıfırdan.
> Hızlı başvuru için `mimari.md` (ne var, nerede duruyor).
> Kararların gerekçesi için `pre-setup.md` (M-1…M-4).
>
> ⚠️ Her bölümün sonunda **"bu projede nasıl ısırdı"** var. Oralar
> teorik değil: hepsi yaşandı ve çoğu **hata vermeden** yanlış sonuç
> üretti.

---

## 0 · Önce çerçeve: "istek" ne demek

Her şeyin dayandığı tek gerçek: biri tarayıcıda `marka-a.localhost/urunler`
yazdığında ortaya çıkan şey bir **istek** (request) ve karşılığında bir
**cevap** (response) dönmek zorunda — hem de saniyenin altında.

Bu tek kısıt bütün kutuları doğuruyor:

| İhtiyaç | Kutu |
|---|---|
| Cevabı hazırlamak için birinin kod çalıştırması gerek | `app` |
| Kod veriyi bir yerden okumalı | PostgreSQL |
| Bazı işler saniyeler sürer, müşteri bekleyemez | `worker` |
| Bazı işler isteğe değil **saate** bağlı | `scheduler` |
| Her seferinde veritabanına gitmek pahalı | Redis |
| Bütün bunların önünde trafiği karşılayan biri | Caddy |

---

## 1 · CADDY — kapıdaki görevli

**Ne bu:** Bir web sunucusu ve **ters vekil** (reverse proxy). İnternetten
gelen hiçbir istek uygulamaya doğrudan çarpmıyor; hepsi önce Caddy'ye
geliyor, Caddy uygun olanı arkaya iletiyor.

**Neden ayrı bir kutu:** Yaptığı işlerin hiçbiri "e-ticaret" işi değil.

### a) TLS'i sonlandırıyor

`https://` demek trafiğin **şifreli** geldiği anlamına geliyor. Şifreyi
çözmek matematiksel olarak pahalı ve uygulama mantığıyla ilgisiz. Caddy
çözüyor, arkaya düz `http` veriyor.

Yerelde `tls internal` yazıyor — sertifikayı kendi üretiyor, o yüzden
tarayıcı uyarı veriyor ve `curl -k` kullanılıyor.

### b) Hangi markaya gideceğini ayırıyor

`*.localhost` joker kaydı sayesinde yeni marka açıldığında elle bir şey
eklemek gerekmiyor (4.6Z).

⚠️ Joker **tek seviye** eşleşiyor ve çıplak `localhost`'u kapsamıyor —
merkez panel orada.

⚠️ Alan adı Caddy tarafından tanınmıyorsa bağlantı **TLS el sıkışmasına
bile gelmiyor** (`curl` → 000) ve "sunucu kapalı" gibi görünüyor. Bunu
mağazanın kapalı olmasıyla (503) karıştırma.

### c) Statik dosyaları kendi veriyor

Ürün fotoğrafı için PHP'yi uyandırmanın anlamı yok.

### ⚠️ Bu projede nasıl ısırdı

Caddy şifreyi çözüp arkaya düz `http` verince uygulama "demek ki site
http" sandı ve ürettiği **her adres** `http://` çıktı. iyzico ise dönüş
adresinin SSL olmasını **zorunlu** tutuyor — yani ödeme başlatma isteği
reddedilecek, müşteri hiçbir yere yönlendirilemeyecek ve sebep "ödeme
çalışmıyor"dan ibaret kalacaktı.

Çözüm: Caddy `X-Forwarded-Proto: https` başlığını ekliyor, uygulama ona
güveniyor.

⚠️ Ama **kime güvendiği** kritik. `trustProxies` ayarı `*` DEĞİL, yalnızca
özel ağ aralıkları (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`).
`*` deseydik, uygulamaya doğrudan ulaşabilen herkes `X-Forwarded-Proto`
ve `X-Forwarded-For` başlıklarını **uydurabilirdi**.

⚠️ **Bağlı yapılandırma dosyası değişince `restart` gerekir; `up -d`
YETMEZ.** Compose tanımı değişmediyse konteyner yeniden oluşturulmuyor ve
`:ro` bağlı Caddyfile **bayat** kalıyor. 1E.7.3'te yarım saat kaybettirdi:
üç düzeltme arka arkaya yazıldı, üçü de "işe yaramadı" sanıldı — hiçbiri
**yüklenmemişti**.

---

## 2 · app — PHP-FPM, isteği cevaplayan

**Ne bu:** Asıl uygulama; Laravel kodu burada çalışıyor. FPM =
*FastCGI Process Manager*, Caddy ile PHP arasındaki konuşma protokolü.

### Kritik özelliği: "shared nothing"

Her isteğin hafızası, istek bitince **tamamen yıkılıyor**. Bir sonraki
istek sıfırdan başlıyor.

```
istek 1 →  [hafıza kurulur] → cevap → [hafıza YIKILIR]
istek 2 →  [hafıza kurulur] → cevap → [hafıza YIKILIR]
```

*(Teknik ayrıntı: süreç aslında yeniden kullanılıyor, ama PHP'nin bütün
kullanıcı durumu istek sonunda siliniyor. Pratikte aynı kapıya çıkıyor.)*

### Bu neden bir özellik, kusur değil

Çok kiracılı bir sistemde bu **doğrudan güvenlik** demek: A markasının
isteğinden kalan hiçbir şey B'ninkine sızamaz, çünkü arada hafızada
tutulan hiçbir şey yok. Sızıntı için önce hafızada bir şey kalması
gerekirdi.

### ⚠️ Bunun bir sonucu: Inertia SSR kapalı (4-K2)

Sunucuda render için ayrı bir Node süreci gerekiyor ve o süreç **uzun
ömürlü, tüm markalar için ortak** olurdu. Modül seviyesindeki durum
istekler arasında paylaşılır (*cross-request state pollution*) — yani tam
olarak PHP-FPM'in yapısı gereği engellediği şey.

⚠️ Yerelde görünmez: geliştirme sunucusu aynı anda tek istek işliyor.
Ayrıca SSR bozulunca **sessizce** istemci render'ına düşüyor — sayfa
çalışır, testler yeşil kalır, **SEO sessizce gider**.

---

## 3 · worker — arkada çalışan

**Ne bu:** `queue:work` çalıştıran ayrı bir konteyner. İşi: kuyruğa
bırakılmış işleri sırayla yapmak.

### Neden var

Bazı işler saniyeler sürüyor — e-posta göndermek, görsel işlemek, olay
kaydetmek. Müşteri o kadar bekleyemez:

```
istek:   "siparişi kaydet" → kaydetti → cevap DÖNDÜ (hızlı)
                              │
                              └→ kuyruğa not: "fatura e-postası yolla"
                                          │
worker:                                   └→ e-postayı yolluyor
                                             (yavaş, ama kimse beklemiyor)
```

### En önemli farkı

`app`'in **tersine**, hafızası açık kalıyor. Bir kez başlıyor ve vardiya
boyunca ayakta duruyor.

### ⚠️ Bu projede nasıl ısırdı — ve ısırmaya devam eder

`worker` kodu bir kez belleğe alıyor. Kod değiştiğinde `app` yeni kodu
görüyor (zaten her istekte sıfırdan okuyor) ama `worker` **eski kodla
çalışmaya devam ediyor** — ve **hata vermiyor**. Sessizce yanlış
davranıyor.

```bash
docker compose restart worker scheduler
```

Bu komutun tek varlık sebebi bu.

⚠️ Ayrıca: "uygulama bellekte kalınca RAM şişiyor" problemi bizde `app`
için **yok** (her istek sıfırlanıyor), ama `worker` kalıcı — orada
geçerli.

---

## 4 · scheduler — saatçi

**Ne bu:** `schedule:work` çalıştıran konteyner. Dakikada bir uyanıyor,
"şu an çalışması gereken görev var mı" diye bakıyor, varsa tetikliyor.

### Neden ayrı

Bu işler hiçbir isteğe bağlı değil — kimse siteye girmese de çalışmaları
gerek:

| Görev | Ne yapıyor |
|---|---|
| `stok:rezervasyon-temizle` | Süresi dolan stok kilitlerini açar |
| `siparis:terk-hatirlat` | Terk edilen sepete hatırlatma yollar |
| `stok:sayac-denetle` · `puan:sayac-denetle` | Türetilmiş sayaçları denetler |
| `abonelik:deneme-denetle` · `abonelik:nezaket-denetle` | Merkez bağlam |
| `marka:oksuz-dosyalari-temizle` | Sahipsiz klasörleri siler |

### ⚠️ Bu projede nasıl ısırdı

Marka verisine dokunan görev `tenants:run` ile **sarılmak zorunda**:

```
YANLIŞ   Schedule::command('stok:rezervasyon-temizle')
DOĞRU    Schedule::command('tenants:run stok:rezervasyon-temizle')
```

Sarılmazsa görev **merkez bağlamda** koşuyor — hiçbir markanın şemasına
bakmıyor, hiçbir şey yapmıyor ve **hata da vermiyor**.

⚠️ Seçenek geçirirken tırnak içine alma:
`tenants:run komut --option="bayrak=1"` (argümanlar `--argument=`).

⚠️ Ölçeklenince (birden çok `scheduler`) görev **iki kez** çalışabilir;
onun için kilit gerekiyor.

---

## 5 · PostgreSQL — kalıcı hafıza, ve projenin en özgün kararı

**Ne bu:** Veritabanı. Kalıcı olan her şey burada: ürünler, siparişler,
müşteriler, ayarlar.

### Asıl mesele: markalar birbirinden nasıl ayrılıyor?

| Yaklaşım | Nasıl | Risk |
|---|---|---|
| Ortak tablo + `brand_id` | Her satırda marka kimliği | **Tek unutulmuş `where` = veri sızıntısı** |
| Marka başına **veritabanı** | Tam ayrım | Yüzlerce bağlantı, ağır |
| Marka başına **şema** ← seçilen | Tek veritabanı, ayrı isim alanı | Ortası |

### Şema ne demek

Bir veritabanının içindeki klasör gibi. Aynı `products` tablosu, her
markanın şemasında **ayrı ayrı** var.

```
tikmarka (tek veritabanı)
│
├── public                        ← MERKEZ (bizim verimiz)
│   ├── tenants                     hangi markalar var
│   ├── domains                     hangi alan adı hangi markaya ait
│   ├── plans · platform_users
│
├── tenant_a27b7805-...           ← A Markası
│   ├── products · orders · customers · settings ...
│
└── tenant_01d31416-...           ← B Markası
    └── (aynı tablolar, bambaşka veri)
```

### Nasıl seçiliyor

```
host: marka-a.localhost
  → public.domains'te aranıyor
  → tenant kaydı bulunuyor
  → SET search_path = tenant_a27b7805-...
```

`search_path` bir yol tarifi: bundan sonra `products` yazan **her sorgu**
otomatik olarak o markanın şemasındaki tabloya gidiyor. Kod hiçbir yerde
"hangi markadayım" diye sormuyor — `app/Domain/` içinde bu **ölçülüyor**
(M-2.7).

### ⚠️ Bu projede nasıl ısırdı

**Eklentiler `public`'te ve marka şemasından GÖRÜNMÜYOR.** Üç kez oldu:
`citext` (1A) · `ltree` (1B) · `pg_trgm` (2C). `citext` sessizce düz metin
karşılaştırmasına düştü — büyük/küçük harf duyarsız olması gereken e-posta
karşılaştırması sessizce duyarlı hâle geldi. Çözüm: nitelikli yazmak
(`public.similarity`, `OPERATOR(public.<%)`).

**`TimeZone = UTC` tesadüf değil.** Laravel `now()`'ı sorguya **ofissiz
metin** bağlıyor; PostgreSQL onu oturumun saat dilimine göre yorumluyor.
Ölçüldü: 15 dakika sonra dolacak bir rezervasyon, oturum `UTC` iken
yaşıyor, `America/New_York` iken **ölmüş** sayılıyor — aynı satır, aynı
an. WooCommerce'te aynısı yaşandı (#43593), Brisbane'de siparişler süre
dolmadan iptal ediliyordu.

**pgBouncer (bağlantı havuzu) bu yüzden reddedildi (M-2.4).** Verimli
modunda fiziksel bağlantı başka isteğe veriliyor ve `search_path` bir
*oturum* ayarı — yani **A markasının şeması B'ye geçer**. Şema bazlı
kiracılıkta bu doğrudan veri sızıntısı.

**Migration klasörü ikiye ayrı:** marka tablosu
`database/migrations/tenant`, merkez tablosu `.../landlord`. Kök klasör
bilerek **boş** — köke düşen dosya kazara merkez şemaya gider.

---

## 6 · REDIS — hızlı ama unutkan hafıza

**Ne bu:** Verileri **diskte değil bellekte** tutan depo. Çok hızlı, ama
kalıcı değil.

Bu projede **üç işi birden** yapıyor:

| İş | Ne saklıyor | Kaybolursa |
|---|---|---|
| **Önbellek** | Sık sorulan, yavaş hesaplanan şeyler | Yeniden hesaplanır |
| **Kuyruk** | `worker`'a verilen notlar | İş kaybolur |
| **Oturum** | "Bu çerez şu kullanıcıya ait" | Herkes çıkış yapmış olur |

### Markalar Redis'te nasıl ayrılıyor

`config/tenancy.php`'de dört köprü (bootstrapper) etkin:

```
DatabaseTenancyBootstrapper     search_path
CacheTenancyBootstrapper        önbellek anahtarlarına marka öneki
FilesystemTenancyBootstrapper   marka klasörü
QueueTenancyBootstrapper        kuyruk işine marka kimliği
```

⚠️ **`RedisTenancyBootstrapper` KAPALI** (phpredis eklentisi gerektiriyor).
Yani Laravel'in `Cache`/`Queue` katmanından geçmeyen **doğrudan** bir
Redis kullanımı markalar arası **ayrılmaz**. Bugün öyle bir kullanım yok,
ama eklenirse bu bilinmeli.

---

## 7 · Yalnızca geliştirmede olanlar

**mailpit** — Sahte posta kutusu. Gelişirken gerçekten e-posta göndermek
istemezsin (yanlış adrese gider, sınırlara takılırsın). mailpit bütün
postaları yakalayıp web arayüzünde gösteriyor. Üretimde yerini gerçek
SMTP alıyor.

**ngrok** — Yerel makineyi geçici olarak internete açan tünel. İki işe
yarıyor: (1) iyzico webhook'u ulaşabilsin diye, (2) tasarımı gerçek
tarayıcıda görebilmek için — tarayıcı aracı yerel sertifikalı
`.localhost` adresine ulaşamıyor.

```bash
docker compose --profile tunel up -d ngrok     # aç
docker compose --profile tunel stop ngrok      # KAPAT
```

⚠️ İş bitince kapatılmalı, yoksa makine internete açık kalır.
⚠️ Ücretsiz planda ilk açılışta uyarı sayfası çıkıyor;
`ngrok-skip-browser-warning` başlığı gerekiyor.

---

## 8 · Birimler (volumes) — verinin kaybolmaması için

Konteynerler silinip yeniden yaratılabilir. Kalması gereken veri
**birim**lerde duruyor: `pg_data` · `redis_data` · `caddy_data`
(sertifikalar) · `blade_cache` · `node_modules`.

⚠️ **`node_modules` neden birim:** macOS'ta bağlı klasör üzerinden
binlerce küçük dosya okumak hem yavaş hem kilitleniyor — Vite derlemesi
`Unknown system error -35` ile düşüyordu, üç denemede de aynı yerde.
Adlandırılmış birime taşınınca çözüldü (4E).

⚠️ **Adlandırılmış birim, imajdaki karşılığından doluyor — izinler dâhil.**
Klasör imajda yoksa birim `root:root 755` doğuyor ve Blade derleyici
geçici dosya yazamıyor. Düzeltme `Dockerfile`'a yazılır; elle `chmod`
taze kurulumda kaybolur.

---

## 9 · Hepsi birlikte: bir isteğin tam yolculuğu

```
tarayıcı
   │  GET https://marka-a.localhost/urunler/tisort
   ▼
CADDY ── TLS çöz ── X-Forwarded-Proto: https
   │
   ▼
PHP-FPM  yeni istek, temiz hafıza
   │
   ▼
Güvenlik başlıkları        (dört yüzeyde de global)
   │
   ▼
Kiracı çözümleme           host → domains → SET search_path
   │
   ▼
Merkez adresten girilemez  (PreventAccessFromCentralDomains)
   │
   ▼
Oturum · çerez · CSRF      (yalnızca `web` grubunda)
   │
   ▼
Mağaza açık mı?            kapalıysa 503 + Retry-After
   │
   ▼
Controller → Domain → Model → PostgreSQL (tenant_<uuid>)
   │                            ↕
   │                          Redis (önbellek)
   ▼
HTML → Caddy → tarayıcı
   │
   └── SÜREÇ ÖLÜR. Hiçbir şey bellekte kalmaz.
```

Ve bu sırada, tamamen ayrı olarak:

```
worker      kuyruktaki işleri yapıyor      (kimse beklemiyor)
scheduler   dakikada bir uyanıyor          (kimse istemedi)
```

---

## Özetin özeti

| Kutu | Tek cümlede | Unutulmaması gereken |
|---|---|---|
| **Caddy** | Kapıcı: şifre çözer, yönlendirir | Başlığa güven **sınırlı** olmalı |
| **app** | İsteği cevaplar | Hafızası **her istekte sıfırlanır** — bu güvenlik |
| **worker** | Yavaş işleri arkada yapar | Kod değişince **restart şart** |
| **scheduler** | Saate bağlı işleri tetikler | Marka işi `tenants:run` ile **sarılmalı** |
| **PostgreSQL** | Kalıcı hafıza | Her marka **ayrı şemada**, `search_path` ile |
| **Redis** | Hızlı ama geçici hafıza | Önbellek + kuyruk + oturum, üçü bir arada |

---

## Buradan sonra nereye

| Soru | Belge |
|---|---|
| Ne var, nerede duruyor? | `mimari.md` |
| Bu kararlar neden böyle verildi? | `pre-setup.md` (M-1…M-4) |
| Hangi tablo neyi tutuyor? | `domain-model.md` |
| Şu an neredeyiz, sırada ne var? | `PLAN.md` |
| Neler sessizce kırılıyor? | `CLAUDE.md` → "Sessiz hataya yol açan kurallar" |

