# CLAUDE.md

TıkMarka — çok kiracılı D2C e-ticaret. Laravel 12 / PHP 8.4 / PostgreSQL 17,
marka başına ayrı **şema** (`tenant_<uuid>`), merkez veriler `public` şemasında.

## Önce bunları oku

| Dosya | İçerik |
|---|---|
| `PLAN.md` | Yol haritası + **şu an neredeyiz**. Her madde gerekçesiyle yazılı. |
| `docs/summary.md` | Tek sayfalık özet — hızlı bağlam için buradan başla. |
| `docs/mimari.md` | **Kuşbakışı mimari** — konteynerler, yüzeyler, dış servisler, istek akışı. |
| `docs/mimari-ogretici.md` | Aynı mimari **sıfırdan**: her kutu ne, neden ayrı, nasıl ısırdı. |
| `docs/pre-setup.md` | Mimari kararlar (M-1…M-4) ve **neden** öyle olduğu. |
| `docs/domain-model.md` | Veri modeli, tablo tablo. |

Bir karara katılmıyorsan önce `pre-setup.md`'deki gerekçesini oku; çoğu tuzak
orada zaten yazılı.

## Komutlar — hepsi konteyner içinde

Yerel makinede PHP/Composer **yok**. Günlük işler `Makefile`'da toplu:

```bash
make              # ne var ne yok
make ayaga        # her şeyi başlat (tünel hariç)
make kaldir       # her şeyi başlat + ngrok tüneli
make indir        # her şeyi durdur (tünel dâhil)
make kontrol      # lint + analiz + test — commit öncesi ZORUNLU
make yeniden      # kod değişince: worker + scheduler + caddy
```

Altındaki uzun hâlleri:

```bash
docker compose exec -T app composer lint      # Pint (biçim)
docker compose exec -T app composer analyse   # Larastan seviye 8
docker compose exec -T app composer test      # Pest

docker compose exec -T app composer migrate:landlord   # merkez şema
docker compose exec -T app php artisan tenants:migrate # tüm markalar
docker compose exec -T app php artisan tenant:create "Ad" alan-adi.localhost
```

Adresler: `marka-a.localhost` · `marka-b.localhost` · `localhost` (merkez).
Sertifika uyarısı normal (`tls internal`), `curl -k` kullan.

## Sessiz hataya yol açan kurallar

Bunların hepsi **hata vermeden yanlış sonuç** üretir. Projede en az bir kez yaşandı.
- **Zaman karşılaştırması oturum saat dilimine bağlı.** Laravel `now()`'ı sorguya
  **ofissiz** metin bağlıyor (`'2026-08-11 14:01:38'`); PostgreSQL ofissiz metni
  oturumun `TimeZone`'una göre yorumluyor. Ölçüldü: 15 dk sonra dolacak bir
  rezervasyon, oturum `UTC` iken yaşıyor, `America/New_York` iken **ölmüş**
  sayılıyor — aynı satır, aynı an. WooCommerce'te aynısı yaşandı (#43593),
  Brisbane'de siparişler süre dolmadan iptal ediliyordu. Kapatıldı:
  `config/database.php`'de `'timezone' => 'UTC'` + `tests/Feature/ZamanDilimiTest`.
  Sunucu varsayılanı zaten UTC'ydi — yani **tesadüfen** doğruyduk, artık ayarla.
- **`$fillable`** = "neyi **asla** dışarıdan almam" listesi. Yetki/sahiplik
  alanları (`is_owner`, `is_system`, `customer_id`) buraya **girmez**.
- **Kod değiştikten sonra** `docker compose restart worker scheduler` —
  kuyruk işçisi kodu belleğe alıyor, bayat kodla çalışmaya devam eder.
- **Sürümlenmesi gereken şey `settings`'e konmaz.** Ayar "şu an geçerli
  değer"dir, geçmişi yoktur. Yasal metinler bu yüzden
  `legal_document_versions`'ta ve o tablo **salt-ekleme** — `UPDATE`/`DELETE`/
  `TRUNCATE` veritabanı tetiğiyle reddediliyor. Yayınlamak = yeni satır.
- **Yerel `lint` yeşil ≠ CI yeşil. Otorite CI.** Bir kez `lint:check`
  yerelde geçti, CI'da düştü (`class_attributes_separation`); fark 20 koşu
  boyunca fark edilmedi. Sebep kesinleşmedi — muhtemelen Pint'in geçici
  klasördeki önbelleğinde bayat kayıt. Gönderimden sonra durumu gör:
  ```
  curl -s "https://api.github.com/repos/yakupcinar/TikMarka/commits/main/check-runs" \
    | python3 -c "import sys,json;[print(c['name'],c['conclusion']) for c in json.load(sys.stdin)['check_runs']]"
  ```
  Hata ayrıntısı **anotasyonlarda** (günlükler yönetici yetkisi ister);
  `.github/ci-kontrol.sh` çıktıyı oraya basıyor.
- **Bağlı yapılandırma dosyası değişince `restart` gerekir; `up -d` YETMEZ.**
  Compose tanımı değişmediyse `up -d <servis>` konteyneri yeniden
  oluşturmuyor ve `:ro` bağlı dosya (Caddyfile, nginx.conf…) **bayat**
  kalıyor. 1E.7.3'te yarım saat kaybettirdi: Caddyfile'a arka arkaya üç
  düzeltme yazıldı, üçü de "işe yaramadı" sanıldı — hiçbiri
  **yüklenmemişti**. Doğrusu `docker compose restart caddy`.
  ⚠️ Ölçüm bayat yapılandırmaya karşı yapılırsa çıkan sonuç da bayattır;
  "denedim olmadı" demeden önce değişikliğin **yüklendiğini** doğrula.
- **Türetilmiş metne DEĞİŞKEN SAYIDA parça konmaz.** Benzerlik puanı metnin
  uzunluğuna duyarlı; parça sayısı veriye göre değişince eşik kayar ve kayıt
  **sessizce aranamaz** olur. 2C'de ısırdı: `search_text`'e varyant SKU'ları da
  yazılıyordu; testte 1, gerçek üründe **9** varyant vardı, skor 0,33'ten
  0,286'ya düştü ve ürün *varyant sayısı arttığı için* bulunamaz oldu. Test
  yeşildi, iki kiracıda gerçek HTTP koşusu yakaladı. SKU tam-token eşleşmesine
  (FTS vektörü) taşındı.
- **Her cevap JSON — `Accept` başlığı OLMAYAN istemci 500 alıyordu.** Laravel
  kimliksiz HTML isteğini `login` rotasına yönlendirmeye çalışıyor; arayüz
  olmadığı için (M-3) öyle bir rota yok. **425 testin hiçbiri yakalamadı**:
  `postJson`/`getJson` başlığı otomatik ekliyor, gerçek `curl` koşusu ortaya
  çıkardı. Çözüm `app/Http/Middleware/ForceJson.php` (istek düzeyinde başlık).
  ⚠️ `shouldRenderJsonWhen` ve `$exceptions->render(AuthenticationException)`
  ikisi de denendi, **ikisi de çözmedi** — Laravel bu istisnayı kullanıcı geri
  çağırmalarından önce eşliyor. Test: `tests/Tenancy/JsonCevapTest.php`, ve o
  dosyada `postJson` KULLANILMAZ (kullanılırsa hiçbir şey ölçmez).
- **Aynı kilit `.git` dosyalarında da oluyor — belirtisi FARKLI.**
  `fatal: unable to access '.git/config': Operation not permitted` ve
  `warning: unable to access '.git/info/exclude'`. Dosyanın izinleri normal,
  `head` ile okunuyor, ama git erişemiyor. Çözüm aynı: **sil ve yeniden yaz**.
  ⚠️ `.git/config` silinmeden önce içeriği okunmalı — remote adresi orada.
- **Docker Desktop bir dosyayı konteynerde OKUNAMAZ hâle getirebiliyor.**
  Belirti: `hash_file(): … errno=35 Resource deadlock avoided` — phpstan
  başlamadan düşüyor, hangi dosya olduğunu söylemiyor. Host'ta dosya
  sorunsuz okunuyor. Bulmak için konteyner içinden tara:
  ```
  docker compose exec -T app php -r '$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator("/var/www/html/app")); foreach($it as $f){ if($f->isFile() && @hash_file("sha256",$f->getPathname())===false) echo $f->getPathname(),PHP_EOL; }'
  ```
  Çözüm: dosyayı **sil ve yeniden yaz** (inode değişsin). `touch` ve konteyner
  yeniden başlatma yetmiyor — ikisi de denendi.
- **Merkez (`routes/web.php`) rotaları `web` grubunda — CSRF var.** Panel/vitrin
  `api` grubunda olduğu için bu tuzak Faz 1'de görünmedi; 3C'de kontrol düzlemi
  `web.php`'ye yazıldı, **bütün testler yeşildi** ama gerçek `curl` isteği
  `CSRF token mismatch` aldı. Sebep: testler `postJson` kullanıyor. Merkez API
  rotaları ayrı dosyada (`routes/platform.php`) ve `api` grubuyla yükleniyor.
  ⚠️ Karar 1A.2'de zaten verilmişti ve unutuldu — yorum yetmiyor, ölçen test
  gerekiyor (`KontrolDuzlemiTest`, rotanın middleware listesine bakıyor).
- **Test veritabanında ŞEMA silinip kiracı KAYDI kalırsa süit çöker.** Belirti:
  `relation "roles" does not exist` ya da `schema … does not exist`. Genelde
  yarıda kesilen bir koşudan kalıyor. Toparlamak için şemaları düşür **ve**
  merkez tabloları boşalt:
  ```
  TRUNCATE tenants, domains, plans, platform_users, personal_access_tokens RESTART IDENTITY CASCADE;
  ```
  ⚠️ İki test süreci aynı test veritabanında paralel koşarsa da aynı belirti
  çıkıyor — arka planda süit koşarken ikinci bir koşu başlatma.
- **Test GERÇEK dosya sistemini siler — `storage/` paylaşılıyor.** 3G'de
  yaşandı: öksüz klasör temizliği testi `--onayla` ile koştu ve geliştirme
  ortamındaki **gerçek marka klasörlerini** sildi (3 ürün görseli gitti, kayıt
  kaldı); `storage/framework` de silinip test süiti çöktü (`Please provide a
  valid cache path`). Veritabanı testte ayrı (`tikmarka_test`) ama **disk
  ayrı değil**. Kural: dosya silen her servis **kök parametresi** almalı ve
  test kendi geçici klasöründe çalışmalı.
- **Adlandırılmış birim, imajdaki karşılığından DOLUYOR — izinler dâhil.**
  Klasör imajda yoksa birim `root:root 755` doğuyor. 4.5D'de Blade
  derleyici geçici dosyasını yazamadı ve belirti yanıltıcıydı: hata "izin
  yok" değil `tempnam(): file created in the system's temporary directory`
  — PHP sessizce sistem geçici klasörüne düşüyor, sonra `rename()` dosya
  sistemleri arasında patlıyor. Düzeltme `Dockerfile`'a yazılır; elle
  `chmod` taze kurulumda kaybolur.
- **FORM ALANLARI DOĞRULAMAYLA HİZALI OLMALI — testler bunu GÖREMEZ.**
  4.5D'de adres formuna `title` alanı konmamıştı ama `AddressRequest` onu
  zorunlu tutuyordu: **adres defteri hiç kullanılamıyordu.** Müşteri
  "başlık alanı zorunludur" uyarısı alıyor ama neyi dolduracağını ekranda
  göremiyordu. ⚠️ Testlerin hepsi `ornekAdres()` ile **tam veri**
  gönderdiği için hiçbiri yakalamadı — eksik olan sunucu değil EKRANDI.
  Yeni bir form yazarken doğrulamanın `required` alanlarını tek tek
  ekranla karşılaştır; ölçen test formun HTML'ine bakmalı.
- **`Role::permissions` ÖZELLİK DEĞİL METOT.** `role_permissions` için ayrı
  Eloquent modeli yok (1A.6); `$rol->permissions` yazılırsa Laravel onu
  ilişki sanıyor ve *"must return a relationship instance"* ile **500**
  veriyor. `$rol->permissions()` çağır.
- **Modelin `getRouteKeyName()`'i `uuid` ise arayüz de `uuid` göndermeli.**
  4.5C'de `User` için `id` gönderiliyordu: rota eşleşmiyor ve **404**
  geliyordu — yani "korunuyor" sanılan şey kazaydı. ⚠️ Test de bunu
  ölçüyor sanıyordu; 404 ile 422 arasındaki fark yakalandı.
- **LARAVEL MIDDLEWARE'LERİ ÖNCELİK LİSTESİNE GÖRE YENİDEN SIRALIYOR —
  rota grubunda yazdığın sıra SESSİZCE geçersiz olabilir.** 4H'de ısırdı:
  kontrol middleware'i `auth:staff-web`'den önce yazılıydı ama sonra koştu
  (`Authenticate` öncelik listesinde, bizimki değildi). Belirti çok
  yanıltıcı — middleware çalışıyor, uyuşmazlığı doğru görüyor, `logout()`
  işini yapıyor, controller'a `check() === false` ile giriliyor, **ama
  sayfa yine 200 dönüyor**. ⚠️ `prependToPriorityList` denendi, tutmadı.
  Doğrusu: kontrol middleware'i uyuşmazlıkta `$next`'i **çağırmayıp kendi
  cevabını döndürsün** — o zaman zincirin neresinde olduğu fark etmez.
- **SVG LOGO/GÖRSEL KABUL EDİLMEZ.** XML belgesidir ve `<script>`
  taşıyabilir; tarayıcı `<img>` içinde çalıştırmasa da doğrudan açıldığında
  çalıştırır. Marka kendi vitrininde betik çalıştırabilseydi 4-K5'in
  kapattığı kapı yeniden açılırdı.
- **`tenant_asset()` `app/Domain/` İÇİNDEN ÇAĞRILAMAZ** (M-2.7): Domain
  doğrulanmış YOLU döndürür, adresi HTTP katmanı kurar. 4A'da logo yolu
  doğrudan `src`'ye basılıyordu; 4G'de yükleme gelince o hâliyle **kırık
  görsel** çıkardı.
- **`node_modules` BAĞLI KLASÖRDE DURMAZ — adlandırılmış birime konur.**
  macOS bind mount üzerinden binlerce küçük dosya okumak hem yavaş hem de
  kilitleniyor: Vite derlemesi `Unknown system error -35` ile düştü, üç
  denemede de aynı yerde. ⚠️ Tek dosyayı yeniden yazma çözümü BURADA
  YETMİYOR (kilitlenen dosya `node_modules` içinde ve binlerce tane var).
  `docker-compose.yml` → `- node_modules:/var/www/html/node_modules` (4E).
  Sonuç: `npm ci` konteyner içinde yaşıyor.
- **İç içe rota bağlamada Laravel çocuğu EBEVEYNİN İLİŞKİSİNDEN çözüyor.**
  `{urun:uuid}/{varyant:uuid}` için `Product::varyants()` arıyor; ilişkinin
  adı `variants` olduğu için **500** veriyor. Ya parametre adı ilişkiyle
  hizalanır ya da `withoutScopedBindings()` ile kapsama kapatılıp
  doğrulama açıkça yapılır (4D'de ikincisi seçildi: koruma görünür ve
  ölçülebilir olsun diye).
- **Kırma denemesinde DEĞİŞİKLİĞİN UYGULANDIĞINI doğrula.** 4D'de aynı
  kalıp (`izin:product.write`) iki yerde geçiyordu; `replace(..., 1)` ilk
  eşleşmeyi (panel API'sini) bozdu, sayfa izni sağlam kaldı ve test
  "geçti". Yani kırma denemesi **yanlış yeri kırmıştı** ve testin ölçtüğü
  şey hakkında yanlış güven verdi. Kalıp birden çok yerdeyse hedefi
  konumla daralt ve değişikliği `grep` ile gör.
- **Kimliksiz istek `login` ADLI rotaya yönlendiriliyor.** Bizde öyle bir
  rota yok ve `RouteNotFoundException` ile **500** dönüyor. 2E'de API
  tarafında çıkmıştı (`ForceJson` ile çözüldü), 4C'de panel tarafında
  yeniden çıktı — orada doğru cevap JSON değil **giriş sayfasına
  yönlendirme**. `bootstrap/app.php`'de `redirectGuestsTo` ile yola göre
  ayrılıyor.
- **`$errors` ve oturuma bağlı görünüm değişkenleri YALNIZCA `web` grubunda
  var.** `ShareErrorsFromSession` `api` grubunda çalışmıyor. Aynı düzeni
  (layout) iki gruptan render eden bir sayfa varsa `isset($errors)` ile
  korunmalı; korunmazsa `Undefined variable $errors` ile **500** döner.
  4B'de ölçüldü: ödeme dönüş ekranı `api` grubunda (sağlayıcı POST ediyor,
  CSRF üretemez) ve müşteri ödemesini bitirdikten sonra hata sayfası
  görüyordu.
- **`ForceJson` `api` grubunda İNSAN EKRANLARINI da eziyor.** Uç `api`
  grubundaysa `Accept` her istekte JSON'a çevriliyor ve `expectsJson()`
  **her zaman doğru** oluyor — HTML dalı yazsan bile hiç çalışmaz.
  İnsanın gördüğü uçlar `ForceJson::HTML_UCLARI` listesine girer.
  ⚠️ Liste **dar** tutulmalı: genişletilirse 2E'de ölçülen 500 geri gelir.
  ⚠️ Testler bunu görmedi, gerçek `curl` gösterdi (4B).
- **Git BOŞ KLASÖR TUTMAZ — takipteki son dosyayı silmek KLASÖRÜ siler.**
  4A'da CI'ı kırdı: derlenmiş Blade dosyaları yanlışlıkla depodaydı,
  takipten çıkarıldı ve `storage/framework/views`'i ayakta tutan tek şey
  gitti. Taze çıkışta klasör hiç oluşmuyor, Blade derleyemiyor, vitrinin
  **bütün sayfaları** düşüyor. ⚠️ Yerelde görünmez: klasör zaten diskte.
  Çalışma zamanı klasörleri Laravel'in yaptığı gibi `.gitignore` yer
  tutucusuyla tutulur (`*` + `!.gitignore`). Ölçmenin yolu:
  `git clone . /tmp/taze && ls /tmp/taze/storage/framework`.
- **CI hata anotasyonu KONUMA göre değil ÖNEME göre seçilmeli.**
  `.github/ci-kontrol.sh` önce `tail -40` yazıyordu; Pest'in yığın izi
  40 satırı tek başına dolduruyor ve asıl mesaj
  (`Failed asserting that 404 is identical to 200`) izin **üstünde**
  kaldığı için anotasyona hiç girmiyordu. Üstüne GitHub bir adımda ~10
  anotasyon gösteriyor — ekranda yalnızca yığın izinin ortası görünüyor
  ve hata **teşhis edilemiyordu**. Artık satırlar kalıba göre seçiliyor.
- **`EncryptCookies` YALNIZCA `web` grubunda çalışıyor.** `api` grubunda çerez
  middleware'i hiç yok. Bir çerez iki grupta da okunacaksa `encryptCookies`
  istisna listesine girmeli; girmezse aynı çerez iki grupta **iki farklı
  değer** olur ve bu hata vermez — sepet sayfada görünür, sepet ucunda
  görünmez (4A). ⚠️ Bunu ölçen test **iki gruba birden** vurmalı: yalnızca
  `api` tarafına vuran test istisna kaldırılınca **yeşil kalıyor** (ölçüldü).
- **Kullanıcının yazdığı Blade RENDER EDİLMEZ — bu RCE'dir.** Blade PHP'dir ve
  kum havuzu yoktur (Twig'in aksine); `Blade::render()`'a dışarıdan gelen metin
  vermek doğrudan uzaktan kod çalıştırmadır. Cachet'te (#4621) tam bu yaşandı.
  ⚠️ **Bizde bedeli tek marka değil:** şema bazlı kiracılıkta sunucuda kod
  çalıştıran biri `search_path`'i değiştirip **bütün markaların** verisine
  ulaşır. Tema bu yüzden **ayar**, şablon değil (4-K5). Marka şablon yazacaksa
  yol Liquid benzeri **kum havuzlu** bir motordur.
- **Kullanıcının GÖRDÜĞÜ ad ile sistemin SAKLADIĞI değer aynı değilse, serbest
  metin kutusu koymak hatayı GARANTİ eder.** 4.5H.1: kural `slug` saklıyor,
  marka kategoriyi adıyla tanıyor; kutu boş bırakıldığı için "Giyim" yazdı ve
  kural **geçerli sayılıp kaydedildi**. Doğrusu listeden seçtirmek + yazma
  yolunda varlığı doğrulamak. ⚠️ Varlık kontrolü **biçim doğrulayan** sınıfa
  konmaz (o sınıf okuma yolunda da çalışıyor ve veritabanına bakmıyor);
  yazma yoluna ait.
- **`ConvertEmptyStringsToNull` BOŞ METNİ NULL YAPAR — `string` kuralı
  null'da DÜŞER.** 4.5I.1'de ısırdı: ödeme formunda gizli alanlar boş
  gönderiliyor (**gizlemek göndermemek değildir**), middleware onları
  null'a çeviriyor ve müşteri *"shipping.full_name metin olmalıdır"*
  uyarısıyla ödemeye hiç gidemiyordu. Koşullu zorunlulukta sıra:
  `['nullable', 'required_without:...', 'string']`. ⚠️ `nullable`
  zorunluluğu GEVŞETMEZ (`required_*` örtük kurallar, null'da da koşar)
  ama bu **ölçülmeli**. ⚠️ Anahtarı HİÇ göndermeyen test bunu göremez —
  middleware'in dönüştüreceği değer olmuyor; testin gövdesi **tarayıcının
  gönderdiğiyle birebir** olmalı.
- **İSTİSNA İŞLEYİCİSİ İÇİNDE `route()` ÇAĞIRMAK İŞLEYİCİYİ PATLATABİLİR.**
  Genel işleyiciler merkez bağlamında da koşuyor ve orada vitrin rotaları
  **tanımlı değil**; `route('vitrin.sepet')` doğrudan çağrılsaydı hatayı
  işlemeye çalışan kodun kendisi `RouteNotFoundException` fırlatırdı
  (4.5O). `Route::has()` ile koru, yoksa JSON dalına düş.
- **"TARAYICIYA HTML, API'YE JSON" AYRIMINI BİR UÇTA DÜZELTMEK YETMİYOR —
  AİLENİN TAMAMINI DÜZELT.** Aynı hata dört kez çıktı: 4A kapalı mağaza ·
  4B ödeme dönüşü · 4.5G ödeme başlatma · 4.5O sepet/stok istisnaları.
  Her seferinde "bu uç gözden kaçmıştı" denildi. Bir istisna için
  `expectsJson()` dalı yazarken **aynı ekrandan tetiklenebilecek diğer
  istisnaları da** aynı anda tara.
- **AYNI ADRESLİ İKİ ROTADA SON KAYIT KAZANIR — kırma denemesi bunu
  bilmezse yanlış yeri kırar.** 4.6S'de görüntüleme grubuna ikinci bir
  `/urunler/yeni` eklendi ve test **geçmeye devam etti**: yazma grubundaki
  aynı adresli rota onu eziyordu. Deneme, rotayı eski grubundan
  **silecek** biçimde kurulunca düştü. ⚠️ Ayrıca desen çakışması:
  `/urunler/yeni` ile `/urunler/{urun:uuid}` aynı gruptayken sıra sayesinde
  **tesadüfen** çalışıyordu; gruplar bölününce form 403 yerine **404**
  vermeye başladı. `whereUuid` ile sıraya bağımlılık kaldırıldı.
- **HIZ SINIRLAYICI İŞ MANTIĞINDAN ÖNCE ÇALIŞIR — sonuca değil isteğin
  VARLIĞINA bakar.** 4.6T'de ölçüldü: kupon ucuna sepeti olmayan bir
  istemciden 10 istek atıldığında hepsi 404 dönüyor (uygulanacak sepet
  yok) ama 11. istek yine 429. Saldırganın her denemede farklı/geçersiz
  bir hedef kullanması throttle'ı atlatmıyor — bu YAN etki değil, doğru
  davranış: sayaç isteğin başarılı olup olmamasına bakmıyor.
- **LARAVEL 11+ ÇERÇEVE CONFIG'İNİ BİRLEŞTİRİYOR — bir varsayılanı
  `config/`'ten SİLMEK onu YOK ETMİYOR.** 4.6V'de ölçüldü ve
  sömürülebilirliği kanıtlandı: `auth.passwords.users` broker'ı
  `config/auth.php`'den çıkarıldığı hâlde çalışma anında hâlâ vardı ve
  ÇAPRAZ BAĞLIYDI — tablosu `password_reset_tokens` (müşteri), provider
  modeli `App\Models\User` (personel). Vitrinden alınan bir müşteri
  jetonu `Password::broker('users')` ile **personel parolasını
  değiştirdi**. ⚠️ Silinemeyen varsayılan **tutarlı kılınmalı** (aynı
  provider + aynı tablo), yok sayılmamalı. ⚠️ Ayrıca: bir güvenlik
  kararını (burada "iki ayrı jeton tablosu") çerçevenin sessizce
  delebileceğini varsay — kararı ölçen test AYARA değil DAVRANIŞA
  bakmalı.
- **`pint.json` BOZULUNCA pint çöküyor — belirti dosya boyutu hakkında
  YALAN söylüyor.** `file_get_contents(): Read of 8220 bytes failed with
  errno=35 Resource deadlock avoided` diyor ama `pint.json` **28 bayt**.
  Ölçüldü: konteyner içinden `filesize()` 28 döndürüyor,
  `file_get_contents()` ise **0 baytlık** metin veriyor — hata da vermiyor.
  Yani dosya "var ve okunuyor" görünüyor, içeriği boş geliyor.
  Çözüm errno=35 ailesinin standardı: **sil ve yeniden yaz** (inode
  değişsin) — `cp pint.json /tmp/p && rm pint.json && cp /tmp/p pint.json`.
  ⚠️ `--config` ile mutlak yol vermek ÇÖZÜM DEĞİL: bir kez işe yaradı
  sanıldı, çünkü aynı komutta dosya zaten yeniden yazılmıştı. Üç biçim
  (config'li, config'siz, phar'ı /tmp'ye kopyalayarak) ayrı ayrı denendi,
  **üçü de** bozuk dosyayla düştü.
- **BİR KURALI TEK YOLA YAZMAK YETMEZ — AİLENİN TAMAMINA YAZ.** 4.5L'de
  `(product_id, options)` için Domain kontrolü yazıldı ama YALNIZCA
  `ekle()`'ye; `guncelle()` boş kaldı ve aynı ham hata oradan çıkmaya
  devam etti. 4.6X'te ikisi de kapatıldı. "Tarayıcıya HTML, API'ye JSON"
  tuzağıyla aynı aile: **bir uçta düzeltmek, ailenin düzeldiği anlamına
  gelmiyor.**
- **FLASH MESAJI `api` GRUBUNDA KAYBOLUR — ve TEST BUNU GÖREMEZ.**
  `api` grubunda `StartSession` yok; `->with('mesaj', …)` yazıldığı anda
  kayboluyor. 4.6Y'de ısırdı: ürün sepete geliyordu ama "şunlar
  eklenemedi" uyarısı müşteriye **hiç ulaşmıyordu**. ⚠️ Davranış testi
  yeşil kaldı çünkü test istemcisi oturumu ayakta tutuyor ve
  `session('mesaj')` doğru dönüyor — `getJson`'ın çerezi düşürmesiyle
  (4A) aynı aile. Ölçmek istiyorsan **rotanın middleware listesine** bak
  (`gatherMiddleware()`), davranışa değil.
  ⚠️ `gatherMiddleware()` grup adını GENİŞLETMİYOR: `web` döndürüyor,
  `StartSession` diye aramak boşa çıkar.
- **`git checkout` KIRMA DENEMESİNİ GERİ ALMAZ — DOSYAYI COMMIT'E
  DÖNDÜRÜR.** O oturumda yazılan, henüz commit edilmemiş kod sessizce
  gider. 4.6X.1 ve 4.6Y'de **iki kez** oldu; ikisinde de testler geri
  almadan sonra kırmızı kaldığı için fark edildi. Kırmadan önce
  `cp <dosya> /tmp/x.bak`, sonra `cp /tmp/x.bak <dosya>`. ⚠️ Geri almanın
  uygulandığını, kırmanın uygulandığı kadar dikkatle doğrula.
- **PHP'NİN YÜKLEME SINIRI DOĞRULAMA KURALINDAN KÜÇÜKSE KURAL HİÇ
  KONUŞMAZ.** `upload_max_filesize` varsayılanı **2M**; `max:5120` yazan
  bir kural 2–5 MB arası dosyayı hiç görmüyor, çünkü PHP isteği Laravel'e
  ulaşmadan kesiyor. 4.6AA'da ısırdı: marka 4,83 MB'lık ürün fotoğrafını
  yükleyemiyordu. ⚠️ Belirti ayrıca **çevrilmemiş** geliyor —
  `validation.uploaded` — çünkü o anahtar `lang/tr/validation.php`'de
  yoktu. Yeni bir doğrulama kuralı kullanıldığında çevirisini de ekle;
  dosyanın kendi yorumu "unutulursa hemen fark edilir" diyor ama **fark
  edilmiyor**: ekranı okuyan test yoksa kimse görmüyor.
  ⚠️ `post_max_size` yükleme sınırından büyük olmalı (gövdede form alanları
  da var), `memory_limit` ise GD için: 4000×3000 bir JPEG diskte 5 MB,
  bellekte ~48 MB.
- **GÖRSEL İŞLERKEN PİKSEL TAVANI ŞART — DOSYA BOYUTU SINIRI YETMEZ.**
  Birkaç yüz baytlık bir PNG başlığında 6000×5000 yazarak gigabaytlarca
  bellek isteyebilir (sıkıştırma bombası). Tavan `getimagesize()` ile
  **görsel açılmadan** uygulanmalı; sıra ters olursa bombayı önce belleğe
  açmış olursun ve koruma hiçbir işe yaramaz.
  ⚠️ `imagecreatetruecolor` OPAK SİYAH tuval üretiyor: `imagealphablending(false)`
  + `imagesavealpha(true)` konmazsa saydam PNG'ler siyah zeminle kaydedilir.
  ⚠️ AYAR HEM KAYNAKTA HEM HEDEFTE olmalı. Yalnızca hedefte olması CI'ı
  kırdı, yerel yeşildi: `imagecopyresampled` kaynağın alfasını
  HARMANLAYARAK kopyalıyor ve libgd sürümleri bu konuda farklı davranıyor.
  Ayrıca yeniden boyutlandırma YAPILMAYAN yolda hedef tuval hiç
  oluşmuyor — ayar açılışta (kaynakta) yapılmazsa o yolda saydamlık her
  sürümde kaybolur. "Yerel yeşil ≠ CI yeşil" kuralının GD biçimi.
- **"YAPABİLİR MİYİM" SORUSUNU EKRAN CEVAPLAMAZ, DOMAIN CEVAPLAR.** Vitrin
  bir işlemin mümkün olup olmadığını göstermek zorunda (form çıkacak mı,
  düğme açık mı) ve bunu kendi kontrolüyle hesaplarsa **iki formül** olur:
  biri ekranda, biri serviste. Zamanla ayrışırlar — 4.5J'de sepet rozeti
  ile sepetin kendisi farklı sonuç veriyordu. Doğrusu servise "engel var
  mı" diye soran bir metot (`yazmaEngeli`) ve o metodun **yazma yoluyla
  aynı** özel kontrolü çağırması. ⚠️ Engel varsa ekran SEBEBİ yazmalı:
  farklı engeller farklı çözümler gerektiriyor, tek bir "yapamazsınız"
  mesajı hepsini çıkmaza çevirir.
- **"BİTTİ" KAYDI, BİTTİĞİNİN KANITI DEĞİLDİR.** 4.6A'nın PLAN kaydı
  ayrıntılıydı, dört kırma denemesi yazılıydı ve altı testi yeşildi — blok
  yine de yarım uygulanmıştı. Bir bloğun kapsamını doğrularken kayda
  değil **ölçüme** bak: özelliğin geçtiği her yüzeyi tek tek aç.
- **`->not->toContain(a, b)` ÇOK ARGÜMANLI YAZILDIĞINDA YANILTIYOR.**
  Argümanlardan biri eksik olduğu anda iddia geçiyor; ötekinin varlığını
  hiç ölçmüyor. 4.6AC'de ısırdı: `->not->toContain('password',
  'remember_token')` yazılmıştı ve `remember_token` zaten hiç
  yüklenmediği için iddia **`password` varken bile yeşil kalıyordu**.
  Olumsuz iddiaları **tek tek** yaz.
  ⚠️ **KURAL YAZILI OLMASINA RAĞMEN B6'DA TEKRARLANDI** ve bu kez projenin
  en sert kararını ölçen test hiçbir şey ölçmüyordu:
  `->not->toContain('ports:', "servis: {$servis}")`. İkinci argüman
  **mesaj sanılmıştı** — `toContain()` mesaj almıyor, o ikinci ARANAN
  DEĞER. Yani "port dışarı açılmasın" iddiası, port açıkken de geçiyordu.
  Olumsuz iddiaya **mesaj argümanı geçirme**; mesaj gerekiyorsa değeri
  dizgeye katıp `toBe()` kullan.
- **KIRMA DENEMESİ TUTMUYORSA TESTİ SUÇLA, KODU DEĞİL.** Denemenin hiçbir
  testi düşürmemesi "kod fazladan korunuyor" demek değil; genellikle
  "iddia başka bir şeyi ölçüyor" demek. 4.6AC'de iki kez oldu: kolon
  daraltmasını kaldırmak ekranı bozmadı (koruyan şey `$hidden`'dı) ve
  `pending`'i satış saymak hiçbir şeyi bozmadı (test müşterisinin bekleyen
  siparişi yoktu). Her ikisinde de eksik ölçümü yazdıran şey denemenin
  kendisi oldu.
- **PINT ÇIKTISINI BORULARKEN HATAYI GİZLEME.** `pint | tail -2` yazmak
  `pint.json` bozulduğunda (bilinen errno=35) **boş çıktı** veriyor ve
  "geçti" gibi görünüyor. 4.6AC'de biçimlenmemiş dosyalar böyle commit
  edildi ve CI kırmızı döndü. Çıkışı da kontrol et ya da `--test` ile
  koş; boş çıktı **başarı değil, hata belirtisidir**.
- **TASARIMI GÖRMEK İÇİN NGROK TÜNELİ AÇ — tarayıcı aracı `.localhost`'a
  ULAŞAMIYOR.** Yerel sertifikalı adres reddediliyor; 4.6A ve 4.6AB'de
  "tarayıcıda doğrulanamadı" diye kaydedilen şey buydu. `make kaldir`
  (ya da `docker compose --profile tunel up -d ngrok`) sonrası tarayıcı
  sayfayı açıyor, ekran görüntüsü alınabiliyor ve **hesaplanmış stiller**
  okunabiliyor — yani kontrast ölçülebiliyor. ⚠️ İş bitince KAPAT:
  `docker compose --profile tunel stop ngrok`, yoksa makine internete
  açık kalır. ⚠️ ngrok ücretsiz planda ilk açılışta bir uyarı sayfası
  gösteriyor; `ngrok-skip-browser-warning` başlığı ya da bir tıklama
  gerekiyor.
- **KAYNAK DOSYASINI OKUYAN İDDİA, YORUMLARI AYIKLAMADAN ÖLÇMEZ.** Bir kuralı
  ANLATAN yorum, kuralın kendisiyle aynı metni içerir; ham metinde arayan test
  yönerge bozulsa bile **yeşil kalır**. 4.6AE'de iki kırma denemesi bu yüzden
  tutmadı: `@theme inline` dosyada iki kez geçiyordu (yönerge + onu anlatan
  yorum) ve `mb_strpos` ile kurulan SIRA iddiasında ilk eşleşme yine yorumdaydı
  — betik nereye taşınırsa taşınsın sıra değişmiyordu. Yorumlar ayıklanınca
  ikisi de düştü.
  ⚠️ Aynı tuzak 4.6AB'de bulunup düzeltilmişti (sabit renk taraması yorumları
  okuyordu) ve **iki blok sonra tekrarlandı**; ayıklama tek yerde, test
  yardımcısında olmalı.
  ⚠️ Ailenin üçüncü yüzü 4D: "kalıp birden çok yerdeyse hedefi konumla daralt".
  Orada KIRMA yanlış yeri kırmıştı, burada İDDİA yanlış yeri okuyordu.
- **OLAYIN YOKLUĞU, O ŞEYİN OLMADIĞI ANLAMINA GELMEZ — parayı olaydan
  sayma.** `EventRecorder` bilerek "işi bozmayan" bir yol: kuyruğa
  atamazsa istisnayı yutuyor (1F-K3). Yani olay kaydı **eksik olabilir**
  ve bu tasarım gereği. 4.6F'de rapor bu yüzden satışı `order_items`'tan
  sayıyor, olaylardan değil. ⚠️ Tersi de doğru: görüntülemeyi
  `order_items`'tan sayamazsın. **Her ölçüyü güvenilir olduğu yerden al**,
  tek kaynağa zorlama.
- **OKUMA YOLUNDA YAZAN ÖLÇÜMÜN BOT ELEMESİ OLMALI.** Ürün sayfası
  herkese açık; arama motorları, önizleme çekenler ve tarayıcılar aynı
  sayfayı defalarca çekiyor. Elenmezse marka "400 kez bakılmış" diye bir
  sayı görür ve ona göre stok planlar (4.6F). ⚠️ `curl` de elenir — bizim
  gerçek HTTP doğrulama koşularımız da olay üretmez; "curl ile denedim,
  sayaç artmadı" bir arıza değil.
- **HESABI DOĞRU AMA SONUCU SAÇMA OLAN SAYIYI GÖSTERME.** 4.6F'de rapor
  9 görüntülemeden 11 satış için **%122 dönüşüm** yazıyordu; matematik
  doğru, sebep ölçümün eksikliğiydi. Doğrusu sayıyı **düzeltmek değil**
  (tavan koymak gibi) hesaplamamak ve ekranda **sebebini yazmak** —
  bilinmeyeni bilinir göstermek daha kötü. ⚠️ Bunu ancak gerçek ekrana
  bakınca görürsün; prop'ları ölçen test "122" değerini doğru bulur.
- **KVKK GENİŞLETMESİ BLOĞUN SONUNA BIRAKILMAZ.** 4.6F'de ölçüldü: blok
  başlamadan **137 olay kayıtlıydı, 51'i müşteriye bağlı** ve iki KVKK
  yolu da onları görmüyordu — talep o an eksik cevaplanıyordu. Müşteriye
  bağlı veri **zaten toplanıyorsa** boşluk gelecekte değil **bugün** var.
- **`api` GRUBUNDA RENDER EDİLEN İNSAN SAYFASI OTURUMU GÖREMEZ — belirti
  masum, bedel ağır.** Ödeme sonuç sayfası `api`'deydi; görünen kusur "üst
  barda Hesabım yerine Giriş yazıyor"du, gerçek bedel ise
  `auth('customer-web')` her zaman `null` olduğu için **"Siparişimi
  görüntüle" düğmesinin hiç kimseye çıkmamasıydı** — ve bu hata vermiyordu
  (4.6AI). ⚠️ Bir sayfa sağlayıcıdan POST aldığı için `api`'ye konmuşsa,
  akış sonradan POST→303→GET'e çevrildiğinde (4.5R) o gerekçe DÜŞER;
  rotayı geri taşımayı hatırla.
  ⚠️ Taşırken `magaza-acik` gibi grup middleware'lerini gözden geçir:
  parasını ödemiş müşteri, marka mağazayı kapattıysa 503 görmemeli.
- **YAZILI KURAL ÜÇ KEZ TUTMADIYSA KURAL DEĞİL TEST YAZ.** "Test yardımcısı
  `tests/Pest.php`'ye taşınır" kuralı CLAUDE.md'de yazılıydı ve **tek
  oturumda üç kez** unutuldu (`panelSayfalari` · `vitrinliMarka` ·
  `sonucAdresi`). Artık `YardimciKonumuTest` ölçüyor — ve ilk koşusunda
  **zaten depoda duran** bir kusur buldu (`platformTokeni`,
  `AbonelikTest` tek başına koşunca düşüyordu). ⚠️ Belirti dosya yükleme
  sırasına bağlı: tam süitte GÖRÜNMÜYOR.
- **DAR İHTİYACA DAR ÇÖZÜM.** Sepette silinmiş ürünün ADI gerekiyordu;
  `ProductVariant::product()` ilişkisine toptan `withTrashed()` eklemek
  silinmiş ürünün vitrinde görünmesi gibi çok daha geniş bir kapıyı
  **sessizce** açardı. Model üzerinde dar bir erişimci yazıldı (4.6AJ).
- **İMZALI ADRESE SORGU PARAMETRESİ EKLENEMEZ.** İmza sorgu dizesini de
  kapsıyor; `?deneme=3` eklemek imzayı geçersiz kılar ve kullanıcı
  **403** görür. 4.6AK'de otomatik yenileme sayacı bu yüzden adrese değil
  `sessionStorage`'a kondu. ⚠️ Aynı sebeple imzalı bir adrese analitik
  parametresi, dil seçimi ya da UTM etiketi de eklenemez.
- **`queue:restart` BU KURULUMDA WORKER'I ÖLDÜRÜP ORTADA BIRAKIYOR.**
  Laravel'in "doğru" yolu o (nazikçe çık), ama hiçbir serviste `restart:`
  politikası yok — ölçüldü: `RestartPolicy → no`. Sinyal gidiyor, worker
  `Exited (0)` oluyor ve **geri gelmiyor**. Kod değişince kullanılacak yol
  `docker compose restart worker scheduler`.
  ⚠️ Aynı eksiğin daha ağır yüzü: **worker çökerse kimse fark etmiyor** ve
  işler Redis'te sessizce birikiyor.
- **`pcntl` YÜKLÜ DEĞİL — restart işi YARIDA KESEBİLİR.** Laravel SIGTERM'de
  "şu anki işi bitir, sonra çık" der; `pcntl` olmadan süreç anında ölüyor.
  Veri kaybı yok (`--tries=3` + `retry_after`) ama iş **baştan koşuyor** —
  e-posta gönderen bir iş yarıda kesilirse müşteri **iki kez** posta alır.
- **WORKER'IN BAYAT KOD TUTMASININ SEBEBİ OPCACHE DEĞİL.** Ölçüldü:
  `opcache.validate_timestamps=1`, yani opcache değişen dosyayı zaten fark
  ediyor. Sebep PHP'nin **sürecin belleğine bir kez yüklenen sınıfı bir
  daha okumaması**; `queue:work` tek bir uzun ömürlü süreç. ⚠️ Bu yüzden
  "worker'ı derlemeden sonra başlatalım" ÇÖZÜM DEĞİL: sorun ilk açılış
  değil, worker'ın saatlerce ayakta kalması.
- **DERLENMİŞ BLADE DOSYALARI GERİ SIZABİLİYOR.** 4A'da takipten
  çıkarılmıştı; 4.6AL'de **7 tanesi yeniden takipliydi** ve klasörü ayakta
  tutan `storage/framework/views/.gitignore` **diskte yoktu**. Kontrol:
  `git ls-files storage/framework/views/`.
- **`restart` BİRİM BAĞLAMASINI UYGULAMAZ — `up -d` GEREKİR.** B6'da ısırdı:
  Caddy'ye günlük birimi eklendi, `restart caddy` yapıldı, Caddy dosyayı
  yazdı ama **konteynerin kendi katmanına** — birime değil; toplayıcı boş
  klasör gördü. ⚠️ Kayıtlı tuzağın **TERS YÜZÜ**: bağlı yapılandırma
  DOSYASI değişince `restart` gerekir (`up -d` yetmez), compose TANIMI
  değişince `up -d` gerekir (`restart` yetmez). Ayırt etmenin yolu:
  değiştirdiğin şey dosyanın İÇERİĞİ mi, compose'daki TANIM mı?
- **`mb_strpos` KARAKTER, `preg_match` BAYT ofseti kullanır.** İkisi bir
  arada kullanılınca Türkçe karakter/emoji içeren dosyada arama yanlış
  yerden başlıyor. B6'da test yardımcısı `grafana` bloğunu keserken
  `loki`de bitirdi ve **doğru yapılandırmayı yanlış sandı**. Yapı ayrıştıran
  yerde `strpos`/`substr` kullan (ASCII iskelet), `mb_` ile karıştırma.
- **`git checkout` İZLENMEYEN DOSYAYI GERİ ALMAZ — SESSİZCE HİÇBİR ŞEY
  YAPMAZ.** B5'te ısırdı: o oturumda yeni yazılan (henüz commit'lenmemiş)
  bir middleware'e kırma denemesi uygulandı, `git checkout` ile geri
  alınmak istendi ve komut hiçbir şey yapmadı — **kırık kod beş deneme
  boyunca yerinde kaldı** ve sonraki her koşuda fazladan bir kırmızı
  üretti. ⚠️ 4.6X.1/4.6Y'deki tuzağın TERS YÜZÜ: orada `checkout`
  fazlasını geri almıştı, burada hiçbir şeyi. Her iki durumun da çözümü
  aynı: `cp <dosya> /tmp/x.bak` ile yedekle, `cp` ile geri al.
- **AYRILMIŞ ALAN ADINA POSTA GÖNDERMEK GERÇEK BİR BEDEL.** `.localhost`
  `.test` `.invalid` `.example` `.local` (RFC 6761) ve `example.com/.net/.org`
  (RFC 2606) tanımı gereği çözülmüyor; her deneme gönderen hesapta bir iade
  biriktirip itibarını düşürüyor — kullanıcı gerçek bir "Address not found"
  aldı (B4). ⚠️ **Eleme DOĞRULAMADA değil GÖNDERİMDE:** doğrulama
  sıkılaştırılırsa gerçek müşteri yazım hatasında sipariş **veremez**.
  ⚠️ **Uzantı taraması RFC 2606'yı GÖRMEZ** — `example.com` `.com`
  uzantısında ve elemeden geçiyordu; ikinci düzey adlar ayrıca yazılır.
  ⚠️ DNS sorgusu YOK: liste statik (4.5C'nin "ödeme akışında ağa çıkılmaz"
  kararı).
- **TOPLU E-POSTA DEĞİŞTİRMEDEN ÖNCE KİMİN OKUDUĞUNU ARA.** B4'te fixture
  adresleri toptan çevrildi ve **25 test kırıldı**: sahip adresi
  (`sahip@'.$alanAdi`) çağıranlar tarafından da türetiliyordu.
- **Her blok KIRMA DENEMESİNDEN geçer.** Testler yeşil olduğu için değil,
  **ölçtüklerini kanıtladıkları için** güvenilir. Yöntem: bloğun her
  kararını tek tek boz, testin düştüğünü gör, `cp` ile geri al.
  ⚠️ Deneme tutmuyorsa **testi suçla, kodu değil** — bu projede her
  seferinde iddia yanlış şeyi ölçüyordu (yorumu, betiği, kendi kurduğu
  değeri). Bu dosyadaki tuzakların çoğu böyle bulundu.
  ⚠️ `git checkout` ile geri alma: izlenmeyen dosyada **hiçbir şey
  yapmaz**, izlenen dosyada **fazlasını** geri alır. `cp <dosya> /tmp/x.bak`.
- **Bilgi sohbette değil DEPODA durur.** Bir blok bitmeden `PLAN.md`
  (karar + gerekçe + kırma denemeleri), `docs/summary.md` (özet) ve
  gerekiyorsa bu dosya (tuzak) güncellenir. Oturum kapandığında
  kaybolan hiçbir şey olmamalı — devralan kişi/ajan `PLAN.md` ve
  `CLAUDE.md` ile tam bağlamı kurabilmeli.
- **UZUN OTURUMDA DİSK DOLABİLİR — belirti "araç bozuldu" gibi görünür.**
  A4'te yaşandı: `ENOSPC` alındıktan sonra **hiçbir komut çalışmadı**,
  çünkü araç kendi çıktı dosyasını bile açamıyor. Sebep biriken süit
  çıktıları ve Docker imajları. Kayıp yok (dosyalar diskte), ama teşhis
  edilmezse "ortam bozuldu" sanılıyor. `df -h /` ile bak;
  `docker system prune -a --volumes` ve geçici klasör temizliği yer açar.
- **SÜİT KOŞARKEN KAYNAK DOSYA DÜZENLENMEZ — koşunun sonucu yalan olur.**
  Testler dosyayı **koştukları anda** okuyor. A2'de `CLAUDE.md` süit arka
  planda koşarken düzenlendi; yerel koşu **eski sayıyı** yeşil gördü,
  gerçek durumu CI gösterdi (171 beklenirken 173). ⚠️ Arka planda süit
  başlattıysan, o bitene kadar **testlerin okuduğu hiçbir dosyaya
  dokunma** — belge dosyaları dâhil, çünkü bu projede belgeleri okuyan
  testler var.
- **`is_executable()` KONTEYNERDE ROOT OLARAK YALAN SÖYLÜYOR.** Testler
  konteynerde root koşuyor; çalıştırma biti **hiç yokken** bile `true`
  dönüyor. Ölçüldü: dosya `-rw-r--r--` görünüyor, iddia yeşil kalıyor —
  yani izin kontrolü ölçtüğünü sandığı şeyi ölçmüyor (A2). Bit kontrolü
  `fileperms($yol) & 0111` ile yapılır.
- **PHP'DE BİRLEŞTİRME KARŞILAŞTIRMADAN ÖNCE BAĞLANIR.**
  `$ad.' sayı: '.count($x) > 0 ? 'var' : 'YOK'` ifadesi
  `("ad sayı: 3") > 0` oluyor ve sonuç sayı ne olursa olsun `'var'`.
  A2'de ısırdı: iddia, ölçtüğü şey HİÇ YOKKEN de geçiyordu. Ternary
  parantez içine alınır. ⚠️ Bu, "olumsuz iddiaya mesaj argümanı geçirme"
  tuzağının kardeşi: ikisi de **iddianın kendisini** sessizce boşa
  çıkarıyor.

## Kilitler — `.claude/hooks/`

Aşağıdaki üç şey **kural değil kilit**: üçü de bu dosyada yazılı olmasına
rağmen tekrarlandığı için deterministik olarak engelleniyor.

| Engellenen | Sebep |
|---|---|
| `git checkout <dosya>` · `git restore <dosya>` | İzlenmeyen dosyada **hiçbir şey yapmıyor**, izlenen dosyada o oturumun commit'lenmemiş kodunu da geri alıyor. İkisi de yaşandı. Doğrusu `cp <dosya> /tmp/x.bak` |
| İkinci eşzamanlı `artisan test` | Aynı test veritabanında iki süit çöküyor; belirti veri hatası gibi görünüyor (`relation … does not exist`). İki kez yaşandı, ikincisinde 142 test kırmızı |
| `git commit` (biçim düşükse) | CI bir kez pint yüzünden kırmızı döndü: pint koşuldu, sonra düzeltme yapıldı, tekrar koşulmadı |

⚠️ Dal değiştirme (`git checkout main`, `-b`) engellenmiyor — yalnızca
DOSYA geri alma.

⚠️ Hook'lar `.claude/settings.json`'da kayıtlı ve **çalışma alanı güveni**
ister; ilk oturumda onay istenir. Davranışları
`.claude/hooks/hook-testi.sh` ile ölçülüyor (host'ta, 13 vaka) —
konteynerde `jq`/`python3`/`pgrep` olmadığı için Pest onları koşturamıyor.
`tests/Feature/HookKurulumuTest.php` o betiğin eksiksiz kaldığını ölçüyor.

## Devralan ajan için — okuma sırası

Bu proje **tek bir sohbete bağlı değil**; bağlam depoda tutuluyor:

```
CLAUDE.md          bu dosya — her zaman geçerli tuzaklar
.claude/rules/     YOLA BAĞLI tuzaklar — yalnızca eşleşen dosyaya
                   dokunulduğunda yükleniyor:
                     test.md       tests/
                     vitrin.md     resources/views/storefront · app/Http/Storefront
                     veri.md       database/ · app/Models
                     kiracilik.md  app/Tenancy · app/Platform · routes/tenant.php
                     odeme.md      app/Domain/Payment · …/Payment*
                     tasarim.md    resources/css · *.blade.php · *.vue
                     panel.md      resources/js · app/Http/Panel
                     gozlem.md     app/Logging · config/logging.php
                   ⚠️ Toplam 174 tuzak, 72'si burada; sayımı ölçen test:
                      tests/Feature/TuzakSayimiTest.php
.claude/skills/    ritüeller:
                     /blok     bir bloğun dokuz adımı · durdurma koşulu
                               "testler yeşil" DEĞİL "kırma denemesi kırmızı"
                     /kirma    kırma denemesi + deneme tutmadığında
                               "testi suçla" kataloğu (8 vaka)
                     /kontrol  tam doğrulama — `make kontrol` BUNU EKSİK
                               YAPIYOR (pint.json onarımı, test DB
                               temizliği, CI eşitliği, zaman aşımı)
.claude/agents/    `sinayici` — doğrulamayı koşturur, YALNIZCA özet döner
                   (süit ~450 sn ve binlerce satır çıktı)
PLAN.md            36 bitmiş blok, her biri gerekçesi ve kırma
                   denemeleriyle · en üstte "şu an neredeyiz"
docs/summary.md    blok blok özet — hızlı bağlam
docs/mimari.md     kuşbakışı · docs/mimari-ogretici.md sıfırdan anlatım
docs/pre-setup.md  M-1…M-4 mimari kararları ve NEDEN'leri
tests/             1062 test — her biri bir kararı ölçüyor, yorumlar
                   kararın gerekçesini taşıyor
git log            210 commit, mesajlar kararı ve ölçümü anlatıyor
```

⚠️ Bir karara katılmıyorsan önce gerekçesini ara: büyük ihtimalle
`pre-setup.md` ya da `PLAN.md`'de yazılı ve **ölçülerek** verilmiş.
