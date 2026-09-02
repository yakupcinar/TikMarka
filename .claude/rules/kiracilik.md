---
paths:
  - "app/Tenancy/**"
  - "app/Platform/**"
  - "config/tenancy.php"
  - "routes/tenant.php"
  - "docker/Caddyfile"
---

# Kiracılık tuzakları

Kiracılık **tek klasörde toplanmıyor**; bu yüzden yol listesi geniş.
Buradaki hataların hepsi *hata vermeden yanlış markaya* yazıyor ya da
yazmıyor — en pahalı sessiz hata sınıfı.

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **`citext` marka şemasında çalışmıyor** — eklenti `public`'te, marka
  `search_path`'i görmüyor, sessizce düz metin karşılaştırmasına düşüyor.
  E-posta için: modelde küçültme + `CHECK (email = lower(email))`.
- **Marka verisine dokunan zamanlanmış görev** `tenants:run <komut>` ile
  sarılır; doğrudan yazılan görev merkez bağlamda koşar ve hiçbir şey yapmaz.
  ⚠️ Seçenek geçirirken **tırnak içine alma** — `tenants:run "komut --bayrak"`
  "komut tanımlı değil" hatası verir. Doğrusu ayrı seçenek olarak:
  `tenants:run komut --option="bayrak=1"` (argümanlar `--argument=`).
- **Yeni PostgreSQL uzantısı İKİ yere yazılır.** `docker/postgres/init.sql`
  (yerel) **ve** `.github/workflows/ci.yml` (CI servis konteynerinde init.sql
  yok). 2C'de ikincisi unutuldu: yerelde 396 test yeşil, CI kırmızı — uzantı
  yerelde vardı. "Otorite CI" kuralının ikinci örneği.
- **Uzantılar `public`'te, marka `search_path`'i onları GÖRMEZ.** Üç kez ısırdı:
  `citext` (1A) · `ltree` (1B) · `pg_trgm` (2C). Hepsi nitelikli yazılmalı —
  `public.similarity`, `public.gin_trgm_ops`, `OPERATOR(public.<%)`. (Türkçe FTS
  sözlüğü `pg_catalog`'ta olduğu için görünüyor, o istisna.)
- **`tenants` tablosuna kolon eklemek YETMEZ — `getCustomColumns()`'a da yazılır.**
  Paketin varsayılanı `['id']`; geri kalan HER alan `data` json'ına gidiyor.
  3B'de ölçüldü: kolon `NULL`, veri json'da, ama `$tenant->name` **doğru**
  değeri veriyor — yani kod çalışıyor gibi görünüyor. Kırılan tek şey SORGU:
  `where('trial_ends_at', '<=', now())` hiçbir şey bulmaz, hata da vermez.
  ⚠️ Alan iki yerde birden durursa **`data` kazanıyor** (ölçüldü) — bu yüzden
  kolona taşırken `data`'dan `- 'anahtar'` ile SİLİNMELİ.
- **OTURUM TABANLI KİMLİK ÇOK KİRACILIKTA KENDİLİĞİNDEN GÜVENLİ DEĞİL.**
  Oturum yalnızca kullanıcı `id`'sini tutuyor; guard onu **isteğin
  kiracısının** şemasından çözüyor. İki markada da `id = 1` olan birer
  kullanıcı varsa **A'nın oturum çerezi B'nin panelini açar** — 4H'de
  ölçüldü, 200 dönüyordu. Bugün tarayıcı bunu yapmaz (`SESSION_DOMAIN=null`
  → çerez alan adına bağlı) ama koruma ona bırakılamaz: 3D'deki kayıt
  markalara **alt alan adı** veriyor ve biri `SESSION_DOMAIN`'i
  `.tikmarka.com` yaparsa her marka her paneli açar. Çözüm: girişte
  oturuma marka kimliği damgalanıyor, her istekte doğrulanıyor
  (`EnsureSessionTenant`).
- **Merkez rotalarda `route()` HER ZAMAN İLK alan adını üretir.**
  `central_domains` birden çok alan adı içeriyor (`localhost`,
  `127.0.0.1`, ileride gerçek alan adı). 4F'de ısırdı: `localhost`'tan
  giriş yapan yönetici `127.0.0.1`'e savruluyordu ve oturum çerezi orada
  geçerli olmadığı için giriş ekranına geri düşerdi. Merkez yönlendirmelerde
  **göreli yol** kullan (`redirect('/yonetim')`).
- **Yeni marka geliştirmede HTTPS'e çıkmaz — ARTIK ÇIKIYOR (4.6Z).**
  `docker/Caddyfile` joker kullanıyor (`*.localhost`), yani yeni marka için
  elle ekleme GEREKMİYOR. ⚠️ Joker **bare `localhost`'u kapsamaz** (merkez
  panel orada) ve tek seviye eşleşir. ⚠️ Belirti hâlâ bilinmeli: alan adı
  Caddy tarafından tanınmıyorsa bağlantı **TLS el sıkışmasına bile
  gelmiyor** (`curl` → 000) ve "sunucu kapalı" gibi görünüyor — mağazanın
  kapalı olmasıyla (503) karıştırma.
- **İMZALI ADRES ÜRETEN KOD İSTEK BAĞLAMINDA ÇALIŞMAK ZORUNDA.**
  `URL::temporarySignedRoute()` MUTLAK adres üretiyor ve kökünü o anki
  istekten alıyor; istek yokken `APP_URL`'e düşüyor — bu projede
  `http://localhost`, yani **merkez** alan adı. 4.6W'de ısırdı: doğrulama
  bağlantısı markanın vitrinine değil merkeze işaret ediyordu ve uç orada
  tanımlı olmadığı için **404** dönüyordu. ⚠️ Bedeli sessiz: posta gider,
  müşteri tıklar, sayfayı bulamaz. Bildirimi kuyruk işinden, artisan
  komutundan ya da `tenants:run` ile tetikleyen kod aynı tuzağa düşer.
  Ölçen test adresi **modelden değil gerçek HTTP akışından** almalı ve
  markanın alan adını taşıdığını ayrıca sınamalı.
  ⚠️ Aynı bağımlılık `sendPasswordResetNotification`'da da var (`route()`).
