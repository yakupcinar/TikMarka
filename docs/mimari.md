# Mimari — kuşbakışı

> Bu belge **ne olduğunu** anlatır, **neden** öyle olduğunu değil.
> Gerekçeler `pre-setup.md`'de (M-1…M-4), veri modeli `domain-model.md`'de.
>
> ⚠️ Konteynerlerin ne işe yaradığını bilmiyorsan önce
> **`mimari-ogretici.md`** — aynı mimariyi sıfırdan, her kutunun neden
> ayrı olduğunu ve nerede ısırdığını anlatıyor.

## 0. Bir cümlede

Tek Laravel uygulaması, tek PostgreSQL veritabanı; **her marka kendi
şemasında** (`tenant_<uuid>`), merkez veriler `public`'te. Kiracıyı **alan
adı** belirliyor. Dışarıya bakan tek dış servis **iyzico** (ödeme) ve
**SMTP** (posta).

---

## 1. Kapıdan içeri — konteynerler

```
                            İNTERNET
                               │
                    ┌──────────┴──────────┐
                    │        CADDY        │  :80 → :443
                    │  TLS sonlandırma    │  tls internal (yerel)
                    │  ters vekil         │  *.localhost joker (4.6Z)
                    └──────────┬──────────┘
                               │ FastCGI  (X-Forwarded-Proto)
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
┌───────┴───────┐    ┌─────────┴────────┐    ┌────────┴────────┐
│      app      │    │      worker      │    │    scheduler    │
│   PHP-FPM     │    │  queue:work      │    │  schedule:work  │
│               │    │                  │    │                 │
│ istek başına  │    │ KALICI SÜREÇ     │    │ dakikada bir    │
│ sıfırdan      │    │ ⚠ kod değişince  │    │ uyanır          │
│ başlar, ölür  │    │   restart ŞART   │    │                 │
└───────┬───────┘    └─────────┬────────┘    └────────┬────────┘
        │                      │                      │
        └──────────┬───────────┴──────────┬───────────┘
                   │                      │
          ┌────────┴────────┐    ┌────────┴────────┐
          │   PostgreSQL 17 │    │      Redis      │
          │                 │    │                 │
          │ public          │    │ cache  ┐        │
          │ tenant_<uuid> ×N│    │ queue  ├ hepsi  │
          │                 │    │ session┘ tek    │
          │ TimeZone = UTC  │    │        yerde    │
          └─────────────────┘    └─────────────────┘

  yalnızca geliştirme:   mailpit (posta yakalayıcı) · ngrok (tünel)
```

⚠️ **`app` istek başına ölür, `worker` ölmez.** Kodu değiştirdikten sonra
`docker compose restart worker scheduler` — yoksa işçi bayat kodla çalışır
ve bu **hata vermez**.

---

## 2. İstek nereye düşüyor — alan adı belirliyor

Aynı yol (`/yonetim`) **hangi alan adında** olduğuna göre iki farklı şeye
gidiyor. Kiracıyı seçen şey budur:

```
   HOST                             │  YOL          │  NE
───────────────────────────────────┼───────────────┼──────────────────
   marka-a.localhost                │  /            │  VİTRİN  (müşteri)
   marka-a.localhost                │  /yonetim     │  PANEL   (personel)
   marka-a.localhost                │  /api/...     │  marka API'si
   marka-a.localhost                │  /panel/...   │  panel API'si
───────────────────────────────────┼───────────────┼──────────────────
   localhost  (merkez)              │  /yonetim     │  KONTROL DÜZLEMİ (biz)
   localhost  (merkez)              │  /platform/.. │  merkez API'si
```

Kapı görevlisi `InitializeTenancyByDomain`:

```
  host  ──▶  public.domains  ──▶  tenant  ──▶  SET search_path = tenant_<uuid>
                    │
                    └── bulunamazsa → TenantCouldNotBeIdentified → 404
```

Bu andan sonra **her sorgu** o markanın şemasına gider. `app/Domain/`
içindeki hiçbir sınıf "hangi kiracıdayım" diye sormaz (M-2.7) — ölçülüyor.

---

## 3. Dört yüzey, dört farklı middleware zinciri

```
┌─ VİTRİN ────────────── routes/tenant.php ──────────── `web` grubu ──┐
│  Blade, SUNUCUDA render                                            │
│  oturum + çerez + CSRF        guard: customer-web                  │
│  magaza-acik  (kapalıysa 503 + Retry-After, HTML)                  │
│  ⚠ ForceJson TAKILI DEĞİL — takılsaydı form hataları 422 JSON      │
│  ⚠ iki düzen var: sade · vitrinli   (tema bir AYAR, 4-K5)          │
└────────────────────────────────────────────────────────────────────┘

┌─ PANEL (sayfalar) ──── routes/tenant.php ── /yonetim ─ `web` grubu ─┐
│  Inertia + Vue, TARAYICIDA render                                  │
│  oturum + CSRF               guard: staff-web                      │
│  EnsureSessionTenant  (oturuma marka damgası — 4H)                 │
│  izin:<yetki> · sahip · marka-aktif                                │
│  ⚠ cevap EKRANDAKİ METNİ İÇERMEZ → component/props ile iddia kur   │
└────────────────────────────────────────────────────────────────────┘

┌─ API'ler ───────────── routes/tenant.php ── /api · /panel ─ `api` ──┐
│  JSON, OTURUMSUZ                                                    │
│  Sanctum token          guard: customer  /  staff                   │
│  ForceJson (prepend)  ·  throttle:...                               │
│  ⚠ çerez middleware'i YOK — EncryptCookies yalnızca `web`'de        │
└─────────────────────────────────────────────────────────────────────┘

┌─ KONTROL DÜZLEMİ ───── routes/web.php + platform.php ──────────────┐
│  merkez alan adı · Inertia            guard: platform-web / platform│
│  ⚠ API'si AYRI DOSYADA (platform.php) ve `api` grubunda —           │
│    `web`'de olsaydı CSRF her POST'u kırardı (3C)                    │
└─────────────────────────────────────────────────────────────────────┘
```

⚠️ **Guard'ı adıyla söylemek zorunlu.** Varsayılan guard `customer`
(= sanctum, token). Sayfa katmanında `$istek->user()` yazılırsa token
sorulur, `null` döner ve **giriş yapmış müşteri misafir sayılır** (4.5I).

---

## 4. Katmanlar — iş kuralı nerede durur

```
   HTTP           app/Http/Storefront   app/Http/Panel   app/Http/Platform
                          │                   │                │
                          └─────────┬─────────┴────────────────┘
                                    │   isteği al → servisi çağır → biçimle
                                    ▼
   İŞ MANTIĞI              app/Domain/*        ← KİRACIDAN HABERSİZ
                           Cart · Catalog · Order · Payment · Stock
                           Promotion · Returns · Review · Privacy
                           Identity · Legal · Search · Settings · Quota
                                    │
                          ┌─────────┴─────────┐
                          ▼                   ▼
   VERİ              app/Models/         app/Platform/
                     (marka şeması)      (public şeması: Tenant, Plan…)
```

**Kural:** bir kontrol HTTP dışından (artisan · kuyruk · tohumlayıcı)
atlanabiliyorsa `app/Domain/`'e girer. Controller yalnızca çevirir.

⚠️ Veritabanı kısıtı Domain'in **yedeği değil son savunması** — yarış
durumunda iki eşzamanlı istek de Domain kontrolünü geçebilir.

---

## 5. Dış servisler

```
┌──────────────┐        ┌───────────────────────────────────────────┐
│   TıkMarka   │        │  iyzico          ödeme + iade             │
│              │───────▶│                  IyzicoProvider           │
│              │        │  ⚠ arayüz: PaymentProvider                │
│              │◀───────│    testte FakePaymentProvider             │
└──────────────┘ webhook└───────────────────────────────────────────┘
        │
        │              ┌───────────────────────────────────────────┐
        └─────────────▶│  SMTP    yerelde mailpit · üretimde Gmail │
                       │          8 posta türü (sipariş, kargo,    │
                       │          doğrulama, şifre, terk…)         │
                       └───────────────────────────────────────────┘

  DNS sorgusu  →  yalnızca alan adı doğrulamada. Ödeme akışında AĞA
                  ÇIKILMIYOR (bir sorgu 24 sn sürmüştü — 4.5C).
```

### Ödeme akışı — en çok ısıran yer

```
  müşteri            biz                        iyzico
    │                 │                            │
    │ POST /odeme     │                            │
    │────────────────▶│  sipariş oluştur           │
    │                 │  STOK BAĞLA (60 dk)        │
    │                 │  ödeme başlat ────────────▶│
    │                 │◀──────── paymentPageUrl ───│
    │ /odeme/ode/{uuid}                            │
    │◀────────────────│  IFRAME içinde o ADRES     │
    │                 │  ⚠ sağlayıcının hazır      │
    │                 │    BETİĞİ kullanılmaz —    │
    │                 │    kart alanları bizim     │
    │                 │    DOM'umuza girerdi       │
    │                 │                            │
    │  ── kart bilgileri iyzico'nun sayfasında ───▶│
    │                 │                            │
    │                 │◀── POST /odeme/donus ──────│  (gövdede referans)
    │                 │    303 ──▶ imzalı GET      │
    │◀── /odeme/sonuc/{uuid}?imza                  │
    │                 │                            │
    │                 │◀── POST /webhooks/payment ─│  ASIL GERÇEK BU
```

⚠️ **Üç ayrı ders burada birikti:**
1. `status: success` demesi **istediğini yaptığı anlamına gelmez** —
   tutarı, adedi, kimliği karşılaştır.
2. **"Çağrı başarısız" ≠ "işlem başarısız".** Cevapta işlemin kendi
   durumu varsa o bir *sonuçtur*, hata değil.
3. Dönüş **POST ve referans gövdede** — çerçeveden çıkan betik kendi
   adresine GET atarsa referans kaybolur, müşteri **404** görür (4.5R).

---

## 6. Eşzamansız taraf

```
  İSTEK ──▶ dispatch ──▶ ┌─ Redis kuyruğu ─┐ ──▶ worker ──▶ RecordEvent
                         └─────────────────┘

  scheduler (dakikada bir)
     │
     ├─ tenants:run stok:rezervasyon-temizle     süresi dolan kilitleri aç
     ├─ tenants:run stok:sayac-denetle           türetilmiş sayaç denetimi
     ├─ tenants:run puan:sayac-denetle           rating_avg denetimi
     ├─ tenants:run siparis:terk-hatirlat        terk edilmiş sepet postası
     ├─ abonelik:deneme-denetle                  ┐ MERKEZ bağlam —
     ├─ abonelik:nezaket-denetle                 ┘ tenants:run YOK
     ├─ marka:oksuz-dosyalari-temizle --onayla
     └─ queue:prune-failed --hours=168           (haftalık)
```

⚠️ **Marka verisine dokunan görev `tenants:run` ile sarılır.** Doğrudan
yazılan görev merkez bağlamda koşar ve **hiçbir şey yapmaz** — hata da
vermez.

⚠️ Seçenek geçirirken tırnak içine alma:
`tenants:run komut --option="bayrak=1"` (argümanlar `--argument=`).

---

## 7. İstemci tarafı

```
  VİTRİN                          PANEL / KONTROL DÜZLEMİ
  ──────                          ───────────────────────
  Blade (sunucuda render)         Inertia v2 + Vue 3
  ufak JS: tema, varyant seçici   Vite ile derlenir (make derle)
  SEO sunucudan gelir             ⚠ SSR KAPALI (4-K2) — kalıcı Node
                                     süreci markalar arası durum
  tema anahtarı:                     sızdırır
    tikmarka-tema                 tema anahtarı:
                                    tikmarka-panel-tema
```

⚠️ **İkisinin tema anahtarı bilerek ayrı**: panel bizim arayüzümüz,
vitrin markanın.

⚠️ Panel/Vue değişince `make derle` **şart** — derlenmemiş bileşen
tarayıcıya ulaşmaz ve belirti **boş sayfa**.

---

## 8. Bir isteğin tam yolculuğu

```
 tarayıcı
    │  GET https://marka-a.localhost/urunler/tisort
    ▼
 CADDY ── TLS sonlandır ── X-Forwarded-Proto: https ──┐
    │                                                 │
    ▼                                          (trustProxies:
 PHP-FPM  yeni süreç                            yalnızca özel ağ —
    │                                           `*` olsaydı başlık
    ▼                                            uydurulabilirdi)
 SecurityHeaders          global, DÖRT yüzeyde de
    │
    ▼
 InitializeTenancyByDomain   host → domains → search_path
    │
    ▼
 PreventAccessFromCentralDomains
    │
    ▼
 StartSession · EncryptCookies · CSRF        (`web` grubu)
    │
    ▼
 magaza-acik            kapalıysa 503 + Retry-After (HTML)
    │
    ▼
 Controller  ──▶  app/Domain/Catalog  ──▶  Model  ──▶  PostgreSQL
    │                                                  (tenant_<uuid>)
    ▼
 Blade render  ──▶  HTML  ──▶  Caddy  ──▶  tarayıcı
    │
    └── süreç ÖLÜR. Hiçbir şey bellekte kalmaz.
```

---

## 9. Nerede ne var

```
app/
├── Platform/      merkez şema — Tenant, Plan, abonelik
├── Tenancy/       kiracılık KOMUTLARI (yalnızca 142 satır)
├── Domain/        iş mantığı — kiracıdan habersiz
├── Models/        marka şeması modelleri
├── Http/
│   ├── Storefront/   vitrin (Blade)
│   ├── Panel/        marka paneli (Inertia)
│   ├── Platform/     kontrol düzlemi (Inertia)
│   └── Middleware/
├── Mail/          8 posta türü
└── Jobs/          RecordEvent

routes/
├── tenant.php     marka: vitrin + panel + API'ler
├── web.php        merkez sayfaları (`web` grubu)
├── platform.php   merkez API'si (`api` grubu — CSRF yok)
└── console.php    zamanlanmış görevler
```

⚠️ **Kiracılık tek klasörde toplanmıyor.** Kiracılığa dokunan yerlerin
tamamı: `app/Tenancy/` · `config/tenancy.php` · `routes/tenant.php` ·
`bootstrap/app.php` · `tests/Pest.php`. Bir kiracılık davranışı ararken
bu beşine bak.
