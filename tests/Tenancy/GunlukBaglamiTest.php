<?php

declare(strict_types=1);

use App\Domain\Settings\StorePublication;
use App\Logging\IstekBaglami;
use App\Models\Customer;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

/*
|--------------------------------------------------------------------------
| Günlük bağlamı — "bu satır hangi isteğe aitti"
|--------------------------------------------------------------------------
|
| ★ BLOK BİR ÖLÇÜMDEN DOĞDU. Günlükteki gerçek bir satır şuydu:
|
|   [2026-08-19 08:29:28] local.ERROR: [iyzico] email hatalı format ile
|   gönderilmiştir {"exception":"[object] (PaymentProviderException…
|
| Hangi marka, hangi müşteri, hangi istek — hiçbiri yok. E-ticarette
| asıl soru olan *"A markasının müşterisi 14:32'de neden ödeyemedi"*
| bu günlükle cevaplanamıyordu.
|
| ⚠️ Bu testler DAVRANIŞA bakıyor, ayara değil: `config('logging.…tap')`
| dolu olduğunu doğrulamak, işleyicinin gerçekten satıra bir şey
| yazdığını ölçmez.
*/

/**
 * Bağlamı gerçekten yazılan satırdan okur.
 *
 * ⚠️ `Log::spy()` KULLANILMIYOR: o, çağrıyı yakalar ama işleyiciyi hiç
 * çalıştırmaz — yani ölçmek istediğimiz şeyi tam olarak atlar.
 * (`postJson`'ın `Accept` eklemesiyle aynı aile.)
 */
/**
 * @return array<string, mixed>
 */
function yazilanKayit(callable $tetikle): array
{
    $tutucu = new TestHandler;
    $monolog = new MonologLogger('olcum', [$tutucu]);
    $logger = new Logger($monolog);

    (new IstekBaglami)($logger);

    Log::swap($logger);

    $tetikle();

    $kayitlar = $tutucu->getRecords();

    return $kayitlar === [] ? [] : (array) end($kayitlar)->extra;
}

/**
 * Giriş yapabilecek müşteri — mağaza AÇIK olmalı, yoksa 503 gelir.
 */
function gunlukMusterisi(string $eposta): Customer
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $musteri = Customer::create([
        'name' => 'Ayşe Yılmaz', 'email' => $eposta, 'password' => bcrypt('sifre12345'),
    ]);

    return $musteri;
}

it('★★★ GUNLUK SATIRI HANGI MARKA oldugunu soyluyor', function () {
    markaKur('marka-a.test');

    $extra = yazilanKayit(fn () => Log::error('ölçüm'));

    expect($extra['marka'] ?? null)->toBe(tenant()->getTenantKey());
});

it('★★★ GUNLUK SATIRI ISTEK KIMLIGI tasiyor — istekten kuyruga kadar', function () {
    markaKur('marka-a.test');
    magazayiHazirla();

    /*
    | ⚠️ Kimlik ELLE KONMUYOR: middleware'in gerçekten koştuğu ölçülüyor.
    | Elle `Context::add` yazılsaydı test kendi kurduğu değeri okurdu ve
    | middleware kaldırıldığında yeşil kalırdı (1D.6'nın dersi).
    */
    $this->get('http://marka-a.test/');

    $extra = yazilanKayit(fn () => Log::error('ölçüm'));

    expect($extra['istek_id'] ?? null)->toBeString();
    expect($extra['istek_id'] ?? null)->not->toBe('');
});

it('★★★ ISTEK KIMLIGI CEVABIN BASLIGINDA — destek musteriden isteyebilsin', function () {
    markaKur('marka-a.test');
    magazayiHazirla();

    $cevap = $this->get('http://marka-a.test/');

    expect($cevap->headers->get('X-Istek-Id'))->toBeString();
    expect($cevap->headers->get('X-Istek-Id'))->not->toBe('');
});

it('★★★ HER ISTEK AYRI KIMLIK aliyor — sabit deger ise hicbir sey ayirt edilemez', function () {
    markaKur('marka-a.test');
    magazayiHazirla();

    $bir = $this->get('http://marka-a.test/')->headers->get('X-Istek-Id');
    $iki = $this->get('http://marka-a.test/')->headers->get('X-Istek-Id');

    expect($bir)->not->toBe($iki);
});

it('★★★ GIRIS YAPMIS MUSTERININ KIMLIGI satira geciyor', function () {
    $musteri = gunlukMusterisi('gunluk@ornek.com');

    /*
    | ⚠️ `actingAs` KULLANILMIYOR — o, VARSAYILAN guard'ı da değiştiriyor
    | ve hangi guard'dan çözüldüğünü ölçen testi yalancı yapıyor (4.5I'de
    | iki kez ısırdı). Gerçek giriş isteği atılıyor.
    */
    $this->post('http://marka-a.test/giris', [
        'email' => $musteri->email, 'password' => 'sifre12345',
    ])->assertRedirect();

    $extra = yazilanKayit(fn () => Log::error('ölçüm'));

    expect($extra['musteri'] ?? null)->toBe($musteri->id);
});

it('★★★ SATIR HANGI SURECTEN geldigini soyluyor', function () {
    /*
    | ★ ÖLÇÜLDÜ: app · worker · scheduler ÜÇÜ DE aynı dosyaya yazıyor
    | (inode 4803) ve satırda süreci ayırt eden hiçbir alan yoktu.
    |
    | Bunun bedeli teşhis değil ALARM: *"kuyruk işçisi öldü"* kuralı
    | yazılamıyordu, çünkü worker'ın sustuğu Loki'den görülemiyordu.
    | Oysa worker'ın `restart` politikası yok — çökerse işler Redis'te
    | SESSİZCE birikiyor, sipariş e-postası gitmez ve bağlı stok
    | serbest kalmaz.
    */
    markaKur('marka-a.test');

    $extra = yazilanKayit(fn () => Log::error('ölçüm'));

    expect($extra['surec'] ?? null)->toBeString();
    expect($extra['surec'] ?? null)->not->toBe('');
});

it('★★★ SUREC DEGERI KAPALI LISTEDEN — serbest metin Lokide kardinalite patlatir', function () {
    /*
    | ⚠️ `surec` Loki'de ETİKET oluyor. Ortam değişkeninden gelen değer
    | doğrudan yazılsaydı, oraya ne konursa etiket olurdu — `istek_id`
    | için özenle kaçınılan sınırsız kardinalite tuzağının aynısı.
    |
    | ⚠️ Bu tuzak VARSAYIMSAL DEĞİL: `marka` "marka sayısı kadar" diye
    | güvenli sayılmıştı ama test süiti her koşuda yeni UUID'li kiracı
    | açıyordu ve bulutta 3 kiracıya karşı 71 etiket değeri ölçüldü.
    */
    markaKur('marka-a.test');

    config(['logging.surec' => 'uydurma-deger']);

    $extra = yazilanKayit(fn () => Log::error('ölçüm'));

    expect($extra['surec'] ?? null)->not->toBe('uydurma-deger');
});

it('★★★ E-POSTA GUNLUGE YAZILMIYOR — KVKK yollari dosyayi goremiyor', function () {
    $musteri = gunlukMusterisi('sizinti@ornek.com');

    $this->post('http://marka-a.test/giris', [
        'email' => $musteri->email, 'password' => 'sifre12345',
    ])->assertRedirect();

    $extra = yazilanKayit(fn () => Log::error('ölçüm'));

    /*
    | ⚠️ `Anonymizer` ve `DataExporter` VERİTABANINA bakıyor; günlük
    | dosyası ikisinin de göremediği bir yer. Müşteri "beni unut"
    | dediğinde maskelenmeyen tek kopya orada kalırdı.
    |
    | ⚠️ Olumsuz iddia TEK TEK yazılıyor: `->not->toContain(a, b)` çok
    | argümanlı yazıldığında biri eksikse iddia geçiyor ve ötekini hiç
    | ölçmüyor (4.6AC'de ısırdı).
    */
    $metin = json_encode($extra, JSON_THROW_ON_ERROR);

    expect($metin)->not->toContain('sizinti@ornek.com');
    expect($metin)->not->toContain('@');
});

it('★★★ BAGLAM TOPLARKEN ATILAN ISTISNA SATIRI OLDURMUYOR', function () {
    markaKur('marka-a.test');

    /*
    | ⚠️ Bu kod HATA YAZILIRKEN çalışıyor — yani sistemin zaten bozuk
    | olduğu anda. Bağlam toplarken bir istisna kaçarsa asıl hatanın
    | kaydı da yok olur ve geriye teşhis edilecek hiçbir şey kalmaz.
    |
    | Guard bilerek bozuluyor: olmayan bir guard sorulduğunda
    | `Auth::guard()` istisna atıyor.
    */
    config(['auth.guards.customer-web.provider' => 'olmayan-saglayici']);

    $tutucu = new TestHandler;
    $logger = new Logger(new MonologLogger('olcum', [$tutucu]));

    (new IstekBaglami)($logger);

    Log::swap($logger);

    Log::error('bozuk guard ile');

    expect($tutucu->getRecords())->toHaveCount(1);
});

it('★★★ BAGLAM SATIRIN BASINDA — yigin izinin ARKASINDA degil', function () {
    markaKur('marka-a.test');

    /*
    | ★ ÖLÇÜLDÜ: tek bir hata girdisi 10.351 karakterdi ve bağlam onun
    | SON 100 karakterindeydi. Teşhis için eklenen bilgi, teşhis edilecek
    | gürültünün arkasına düşüyordu. CI anotasyonu dersinin aynısı:
    | bilgi KONUMA göre değil ÖNEME göre yerleşir.
    */
    $tutucu = new TestHandler;
    $logger = new Logger(new MonologLogger('olcum', [$tutucu]));

    (new IstekBaglami)($logger);

    $bicimlenmis = (string) $tutucu->getFormatter()->format(
        new LogRecord(
            new DateTimeImmutable,
            'olcum',
            Level::Error,
            'MESAJ',
            [],
            ['marka' => 'abc'],
        )
    );

    $baglamYeri = mb_strpos($bicimlenmis, 'abc');
    $mesajYeri = mb_strpos($bicimlenmis, 'MESAJ');

    expect($baglamYeri)->toBeInt();
    expect($mesajYeri)->toBeInt();
    expect((int) $baglamYeri)->toBeLessThan((int) $mesajYeri);
});

it('★★★ GUNLUK DONDURULUYOR — tek dosya diski doldurup siteyi durdurur', function () {
    /*
    | ⚠️ Ölçüldü: `single` sürücüsüyle dosya 12 GÜNDE 72 MB'a ulaşmıştı
    | ve döndürme yoktu. Diski dolduran bir günlük siteyi KOMPLE durdurur.
    |
    | ⚠️ Ayara bakan bir test normalde zayıftır; burada ayarın KENDİSİ
    | karar (hangi sürücü kullanılıyor) ve davranışı başka türlü
    | ölçmenin yolu gerçekten gün değiştirmek.
    */
    expect(config('logging.channels.daily.days'))->toBeGreaterThan(0);
    expect(config('logging.channels.stack.channels'))->toContain('daily');
});

it('★★★ ISLEYICI GERCEKTEN KANALA BAGLI — sinif dogru olsa da baglanmamis olabilir', function () {
    /*
    | ★ BU TEST BİR KIRMA DENEMESİNİN TUTMAMASINDAN DOĞDU.
    |
    | `config/logging.php`'den `tap` satırları silindiğinde **hiçbir test
    | düşmedi**: buradaki testler işleyiciyi `new IstekBaglami` ile ELLE
    | kuruyor, yani sınıfın DAVRANIŞINI ölçüyor ama uygulamanın onu
    | gerçekten KULLANDIĞINI hiç ölçmüyordu.
    |
    | ⚠️ Fark sessiz: sınıf yerinde durur, testler yeşil kalır, gerçek
    | günlük dosyası bağlamsız yazılmaya devam eder.
    |
    | Burada uygulamanın KENDİ çözdüğü kanal alınıyor ve işleyicilerinin
    | gerçekten bağlam ürettiği ölçülüyor.
    */
    markaKur('marka-a.test');

    foreach (['single', 'daily'] as $kanal) {
        $kayit = new LogRecord(
            new DateTimeImmutable, $kanal, Level::Error, 'ölçüm', [], [],
        );

        $kanalLogger = Log::channel($kanal);

        assert($kanalLogger instanceof Logger);

        $monolog = $kanalLogger->getLogger();

        assert($monolog instanceof MonologLogger);

        foreach ($monolog->getProcessors() as $isleyici) {
            $kayit = $isleyici($kayit);
        }

        expect($kayit->extra['marka'] ?? null)
            ->toBe(tenant()->getTenantKey(), "kanal: {$kanal}");
    }
});

it('★★★ ROTA OLMAYAN ISTEKTE DE kimlik var — middleware GLOBAL', function () {
    markaKur('marka-a.test');

    Route::get('/olmayan-sey-yok', fn () => 'x');

    $cevap = $this->get('http://marka-a.test/kesinlikle-olmayan-adres');

    expect($cevap->status())->toBe(404);
    expect($cevap->headers->get('X-Istek-Id'))->toBeString();
    expect($cevap->headers->get('X-Istek-Id'))->not->toBe('');
});
