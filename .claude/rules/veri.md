---
paths:
  - "database/**"
  - "app/Models/**"
---

# Veri modeli tuzakları

Buradaki maddelerin ortak yanı: **veritabanı ile Eloquent aynı kuralı
farklı anlayabiliyor**, ve fark hata vermeden yanlış sonuç üretiyor.

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **Migration klasörü.** `database/migrations/` kökü bilerek **boş**.
  Marka tablosu → `--path=database/migrations/tenant`,
  merkez tablosu → `--path=database/migrations/landlord`.
  Köke düşen dosya kazara merkez şemaya gider.
- **`timestampsTz()`** kullan, `timestamps()` değil. Laravel'in varsayılanı
  saat dilimi taşımayan damga üretiyor (`docs/domain-model.md` §0).
- **Kolon varsayılanı modele ULAŞMAZ.** `->default(true)` yalnızca diske
  yazarken uygulanır; `create()`'ten dönen nesnede alan hiç yoktur ve `null`
  okunur. Üç kez ısırdı: `accepts_marketing` (1A.2) · `is_system` (1A.6) ·
  `is_active` (1B.3). Çözüm modelde `protected $attributes = [...]`;
  `refresh()` de işe yarar ama ek sorgu ve her çağrı yerinde hatırlanmalı.
- **`SoftDeletes` + `firstOrFail()` = gecikmeli patlama.** Varsayılan sorgu
  silinmişleri görmüyor; kayıt "yok" sayılıp istisna fırlıyor. 1E.6'da
  ısırdı: marka, ödemesi yolda olan siparişin varyantını katalogdan
  kaldırınca `StockService::kilitle()` patladı — webhook 404 döndü,
  sağlayıcı üç kez denedi, üçü de düştü ve **tahsilat hiç kaydedilmedi.**
  Kural: bir kaydı **kapatan** yol (kesinleştirme, iptal, iade) silinmişi
  de görmeli (`withTrashed()`); **açan** yol görmemeli.
- **Kolon sonradan eklendiyse GERİYE DÖNÜK DOLDURMA gerekir.** Türetilmiş kolon
  yalnızca kayıt *değiştiğinde* yazılır; migration'dan önceki satırlar boş kalır
  ve bu **hata vermez**. 2C'de arama, mevcut hiçbir ürünü bulmuyordu — vitrin
  çalıştığı için fark edilmesi zordu. `php artisan tenants:run "search:reindex"`.
- **`<>` ile `IS DISTINCT FROM` aynı şey DEĞİL.** SQL'de `null <> null` sonucu
  `null`'dur — yani "farklı" sayılmaz ve satır `WHERE`/`HAVING`'den sessizce
  düşer. 2E'de denetim sorgusunda ısırdı: yorumu olmayan ürünlerdeki sayaç
  bozukluğu (`rating_avg` dolu ama olması gereken `null`) denetimden tamamen
  kaçıyordu. Karşılaştırılan iki taraftan biri `null` olabiliyorsa
  `IS DISTINCT FROM` kullan.
- **PostgreSQL'in jsonb `?` operatörü PDO'da YAZILAMAZ.** `data ? 'name'`
  sorgusu `syntax error at or near "$1"` veriyor: PDO `?` işaretini parametre
  yer tutucusu sanıyor. Fonksiyon biçimi kullan: `jsonb_exists(data, 'name')`.
- **VERİ DÖKÜMÜNDE TABLO LİSTESİNİ DARALTMAK YETMEZ — KOLON da temizlenir.**
  4F'de ölçüldü: marka dökümüne `customers.password` üzerinden **bcrypt
  hash'leri** girmişti. Sorun tablonun kendisi değil içindeki kolondu.
  Kimlik bilgisi iş verisi değildir — marka "kim müşterim"i alır,
  "müşterim hangi parolayı kullanıyor"u almaz.
  ⚠️ Şifreli ayar değerleri de çıkarılır: şifreli olması dosyaya
  konabileceği anlamına gelmiyor (dosya `APP_KEY` ile birlikte sızarsa
  çözülür). `TenantDataExport::HASSAS_KOLONLAR`.
- **`firstOrFail()` OKUMA YOLUNDA veri sorununu 404'e ÇEVİRİR.** Laravel
  `ModelNotFoundException`'ı 404'e eşliyor; yani "kuralın gösterdiği kategori
  yok" gibi bir VERİ sorunu ekranda **"sayfa bulunamadı"** diye görünüyor ve
  gerçek sebep hiç anlaşılmıyor. 4.5H.1'de ısırdı: koleksiyon kuralı var
  olmayan kategori slug'ına bakıyordu — vitrinde koleksiyon 404 verdi, üstelik
  panelde üye sayısı aynı sorgudan geldiği için **tek bozuk kural koleksiyon
  listesinin tamamını** düşürdü. Kural: türetilmiş/başvurulan kayıt okuma
  yolunda bulunamıyorsa **istisna değil boş sonuç** üret — ama koşulu
  **sessizce atlama**, hiçbir şeyle eşleştir (atlanırsa `all` eşleşmesi gevşer
  ve fazla kayıt döner).
- **VERİTABANI KISITI TEK BAŞINA ARAYÜZ DEĞİLDİR.** `(product_id, options)`
  benzersizliği doğruydu ama yakalanmayınca panelde ham **500**
  (*"duplicate key value violates unique constraint"*) görünüyordu. 4.5L'de
  ısırdı ve en kötü yerinden: eksen tanımlama ekranı olmadığı için her
  varyantın `options` alanı `[]` oluyordu, yani **her ürünün ikinci
  varyantı** bu hataya düşüyordu. Kural: kısıtı kaldırma (yarış durumuna
  karşı son savunma), Domain'e **aynı adı taşıyan bir kontrol** koy ve
  panelde `CatalogRuleException`'ı **oturum hatasına** çevir — genel
  işleyici JSON döndürüyor ve o yalnızca `api/*` için doğru.
- **YUMUŞAK SİLME + `unique` = DOMAIN İLE VERİTABANI AYNI KURALI FARKLI
  ANLAYABİLİR.** `unique` kısıtı `deleted_at`'e bakmaz, Eloquent sorgusu
  bakar. İkisi hizalanmazsa hata Domain'i **atlayıp** veritabanından
  geliyor ve yakalanamıyor — panelde ham
  `UniqueConstraintViolationException`. 4.6X'te ısırdı.
  ⚠️ Doğru yön ALANIN NE OLDUĞUNA bağlı, tek bir cevabı YOK:
  · **Dış kimlik** (SKU, barkod, fatura no) → silinmişler DE sayılmalı;
    kısıt tam kalır, Domain `withTrashed()` ile arar. Kod dışarıda da
    kullanılıyor (depo, kargo, muhasebe); yeniden kullanılırsa aynı kod
    iki farklı fiziksel şeye işaret eder.
  · **İç ayrım** (`(product_id, options)` gibi "hangi birleşim") → kısmi
    indeks (`WHERE deleted_at IS NULL`), Domain silinmişi görmez. Rezerve
    edilseydi "Kırmızı / M" bir kez silinince bir daha ASLA açılamazdı.
  ⚠️ Silinmiş kayıtla çakışmanın MESAJI ayrı olmalı: kayıt katalogda
  görünmediği için marka o değeri ekranda **arayamaz** ve genel mesajı
  sistem arızası sanar.
- **MÜŞTERİ BAŞINA VERİ EKLERKEN KVKK YOLLARI DA GENİŞLETİLİR.** Yeni bir
  tablo müşteriye bağlanıyorsa `Anonymizer` ve `DataExporter` aynı blokta
  güncellenmeli; unutulursa müşteri başına veri tutan ama KVKK'ya girmeyen
  bir alan doğar ve bu **hata vermez**. ⚠️ İki yolun kararı FARKLI
  olabilir: 4.6D'de anonimleştirme favoriyi **siliyor** (maskelenecek
  alanı yok — kişisel veri bağın kendisi), veri dökümü ise silinmiş
  ürününkini bile **yazıyor** (orada soru "ne gösterelim" değil "elimizde
  ne var"). ⚠️ Yabancı anahtardaki `cascadeOnDelete` anonimleştirmede
  DEVREYE GİRMEZ: o yol müşteriyi silmiyor, maskeliyor.
- **BİR İLİŞKİYE `withTrashed()` EKLEMEK ÖRTÜLÜ BİR KORUMAYI KALDIRIR.**
  Silinmiş kayıt görünür olunca "satılabilir mi" sorusunu cevaplayan kod
  ona da `true` diyebilir. 4.6AJ'de `kullanilabilirMi()`'ye açık
  `trashed()` kontrolü eklendi — ⚠️ ve kırma denemesi **tutmadı**, çünkü
  ürün silindiğinde koruma başka yerden geliyordu
  (`product?->status === Active` zaten `false`). Gerçek senaryo **tek
  varyantın silinmesiydi**: orada ürün hayatta ve kontrol olmasaydı
  silinmiş varyant satılırdı. Ders: bir korumayı ölçen test, o korumanın
  **tek başına** geçerli olduğu senaryoyu kurmalı.
