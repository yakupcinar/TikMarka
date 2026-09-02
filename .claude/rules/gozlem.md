---
paths:
  - "app/Logging/**"
  - "config/logging.php"
  - "docker/alloy/**"
---

# Gözlemlenebilirlik tuzakları — Loki, toplayıcı, etiket

Günlükler Grafana Cloud'a gidiyor (B6.1). Buradaki tuzaklar ya **veriyi
sessizce kaybettiriyor** ya da Loki indeksini şişiriyor; ikisi de hata vermiyor.

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **LOKI'NİN `auth_enabled: false` AYARI "GİRİŞ KAPALI" DEĞİL, "GİRİŞ DİYE
  BİR ŞEY YOK" DEMEK.** Ona ağdan ulaşabilen herkes **bütün markaların**
  günlüğünü okur. Bu yüzden `loki`/`grafana` servislerine `ports:`
  YAZILMAZ — erişim yalnızca Caddy üzerinden. ⚠️ Ayarın gerçek anlamı çok
  kiracılık: `true` olsaydı her istek `X-Scope-OrgID` isterdi; markaya
  kendi günlüğü gösterilmek istendiği gün açılacak kapı budur.
- **ETİKET KARDİNALİTESİNİ ÜRETİMDE DEĞİL TESTTE ÖLÇ.** `marka` "marka
  sayısı kadar" diye güvenli sayılmıştı; ama test süiti her koşuda yeni
  UUID'li kiracı açıyor ve o UUID etikete düşüyordu. Bulutta ölçüldü:
  3 kiracıya karşı **71 etiket değeri**, her koşuda artıyor. Testler
  toplayıcının okuduğu kanala yazmamalı (`phpunit.xml` → `LOG_CHANNEL`).
- **LOKI ETİKETİ = İNDEKS; SINIRSIZ DEĞER ALAN ALAN ETİKET OLMAZ.** Her
  benzersiz etiket birleşimi ayrı bir akış açıyor. `istek_id` her istekte
  farklı — etiket yapılırsa indeks şişer, sorgular yavaşlar. Satırın içinde
  durur ve LogQL ile aranır. Etiket olabilecekler: `marka`, `seviye`,
  `alanadi`, `durum` (hepsi sınırlı).
  ⚠️ `retention_period` TEK BAŞINA ETKİSİZ: silmeyi compactor yapıyor,
  `retention_enabled: true` olmadan süre dolsa da hiçbir şey silinmiyor.
- **`Log::build()` `tap`'İ UYGULAMAZ.** Yapılandırmadan gelmeyen bir kanal
  ürettiği için `tap` hiç devreye girmiyor. B6'da bir kırma denemesi bu
  yüzden tutmadı: test, işleyicinin biçimlendiriciyi ezip ezmediğini
  sınadığını sanıyordu ama işleyici **hiç yüklü değildi**. Bir tap'i ölçen
  test kanalı `Log::channel('<ad>')` ile, yani uygulamanın kendi çözdüğü
  yoldan almalı.
- **YAZMA SAYACI "GİTTİ" DER, "NE GİTTİ" DEMEZ.** B6.1'de iki kez ısırdı.
  Önce *"hata yok + okuma konumu ilerledi"* delil sanıldı ve kullanıcıya
  "çalışıyor" denildi — ikisi de **gönderilecek satır olmadığında da
  doğru**; gerçek durum `sent_entries_total = 0`'dı. Sonra sayaç 302
  gösterince "tamam" denildi, ama veriye bakınca iki kusur çıktı (test
  kirliliği · süreç ayrımı yok). Kural: **bir boru hattını, taşıdığı şeyi
  GÖRMEDEN doğrulama.** Salt-okunur bir jeton bunun için vardır.
- **`app` · `worker` · `scheduler` AYNI GÜNLÜK DOSYASINA YAZIYOR.** Satırda
  süreci ayırt eden alan yoksa *"kuyruk işçisi öldü"* alarmı YAZILAMAZ —
  ve worker'ın `restart` politikası olmadığı için çökerse işler Redis'te
  sessizce birikir. Ayrım `SUREC` ortam değişkeniyle compose'da yazılı;
  `runningInConsole()` yetmiyor (worker ile scheduler'ın ikisi de konsol).
  ⚠️ Değer `config()` üzerinden okunur, `env()` ile DEĞİL: `config:cache`
  sonrası `env()` null döner ve etiket sessizce kaybolur.
- **GÜNLÜK SATIRI BAĞLAMSIZSA TEŞHİS EDİLEMEZ — ve bu hata vermez.**
  B5'te ölçüldü: `[iyzico] email hatalı format` satırı hangi markaya,
  hangi müşteriye, hangi isteğe ait olduğunu **söylemiyordu**; hata
  günlükten teşhis edilemedi, 4.5C'de gerçek istek atılarak bulundu.
  ⚠️ Bağlam **Monolog işleyicisiyle** eklenir, middleware'le DEĞİL:
  middleware'in kiracı başlatılmadan önce mi sonra mı koştuğu sıraya
  bağlı (4H) — işleyici satır yazılırken çalıştığı için kiracı o an zaten
  çözülmüş. ⚠️ Kimlik `hasUser()` ile okunur, `user()` ile DEĞİL:
  ikincisi veritabanına gidiyor ve hatanın sebebi genelde veritabanının
  kendisi. ⚠️ **E-posta yazılmaz** — günlük dosyası `Anonymizer` ve
  `DataExporter`'ın göremediği bir yer.
  ⚠️ Bağlamı satırın SONUNA koymak işe yaramıyor: ölçüldü, tek hata
  girdisi **10.351 karakter** ve bağlam son 100 karakterindeydi.
