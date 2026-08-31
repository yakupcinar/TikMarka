<?php

declare(strict_types=1);

use App\Platform\ReservedSubdomains;

/*
|--------------------------------------------------------------------------
| Gözlem yığını — kararları ölçen sözleşme testleri (B6)
|--------------------------------------------------------------------------
|
| ⚠️ BURADA LOKI KOŞMUYOR ve koşmamalı. Testin ölçtüğü şey servisin
| çalışması değil, KARARLARIN yerinde durması: portlar kapalı mı, alt
| alan adı ayrılmış mı, kardinalite kuralı korunuyor mu. Loki CI'a
| eklenseydi her koşuda bir konteyner beklerdi ve bu üç sorunun hiçbirini
| cevaplamazdı.
*/

function composeMetni(): string
{
    return (string) file_get_contents(base_path('docker-compose.yml'));
}

/**
 * Bir compose servisinin gövdesi.
 *
 * ⚠️ Ham metinde aramak yetmiyor: `ports:` dosyada BAŞKA servisler için
 * zaten var (caddy 80/443, ngrok 4040). Hedef servise daraltılmazsa
 * iddia her zaman geçer ve hiçbir şey ölçmez.
 */
function composeServisi(string $ad): string
{
    $metin = composeMetni();

    /*
    | ⚠️ `mb_strpos`/`mb_substr` KULLANILMIYOR — ve bu bir hatadan geldi.
    | `mb_strpos` KARAKTER ofseti döndürüyor, `preg_match`'in
    | `PREG_OFFSET_CAPTURE` ile beklediği ise BAYT ofseti. Dosyada Türkçe
    | karakter ve emoji olduğu için ikisi kayıyor: arama yanlış yerden
    | başladı, `grafana` bloğu ararken `loki`de bitti ve test doğru
    | yapılandırmayı YANLIŞ sandı.
    */
    $baslangic = strpos($metin, "\n  {$ad}:");

    expect($baslangic)->toBeInt("compose'da '{$ad}' servisi yok");

    $sonraki = preg_match('/\n  [a-z][a-z0-9_-]*:/', $metin, $e, PREG_OFFSET_CAPTURE, (int) $baslangic + 5) === 1
        ? $e[0][1]
        : strlen($metin);

    return substr($metin, (int) $baslangic, $sonraki - (int) $baslangic);
}

it('★★★ TOPLAYICININ DISARI ACIK PORTU YOK', function () {
    /*
    | ★ Toplayıcının dışarıdan erişilmesi gereken hiçbir yüzeyi yok —
    | tek yönü giden trafik. Yönetim arayüzü (12345) yayınlansaydı
    | yapılandırma ve hedef adres dışarıdan okunabilirdi.
    */
    /*
    | ⚠️ MESAJ ARGÜMANI YOK — ve bu bir kırma denemesinin tutmamasından
    | öğrenildi. `toContain()` çok argümanlı: ikinci argüman mesaj değil
    | İKİNCİ ARANAN DEĞER. `->not->toContain('ports:', "servis: grafana")`
    | yazıldığında ikincisi zaten yok olduğu için iddia `ports:` VARKEN
    | BİLE geçiyordu — yani projenin en sert kararını ölçen test hiçbir
    | şey ölçmüyordu. (4.6AC'de kayıtlı tuzağın tekrarı.)
    |
    | Hangi servisin kırıldığı, ayrı `it` yerine döngüde tutuluyor ama
    | iddia TEK argümanlı; ölçüm kaybolmasın diye servis adı dizgeye
    | katılıyor.
    */
    foreach (['alloy'] as $servis) {
        $govde = composeServisi($servis);

        expect($servis.': '.(str_contains($govde, 'ports:') ? 'PORT ACIK' : 'kapali'))
            ->toBe($servis.': kapali');
    }
});

it('★★★ BULUT ERISIM BILGILERI ZORUNLU — sessiz varsayilan yok', function () {
    /*
    | ⚠️ `${LOKI_TOKEN:-...}` yazılsaydı değişken yokken varsayılana
    | düşerdi ve toplayıcı **hiçbir yere gönderemeyen** bir hâlde ayağa
    | kalkardı. Bu HATA VERMEZDİ: konteyner çalışır, Grafana boş görünür
    | ve sebebi anlaşılmaz. `:?` compose'u durduruyor — sessiz varsayılan
    | yerine gürültülü hata.
    */
    $alloy = composeServisi('alloy');

    foreach (['LOKI_URL:?', 'LOKI_KULLANICI:?', 'LOKI_TOKEN:?'] as $degisken) {
        expect($alloy)->toContain($degisken);
    }
});

it('★★★ JETON YAPILANDIRMA DOSYASINA YAZILMIYOR — docker/ depoda', function () {
    /*
    | ★ `docker/` klasörü depoda. Jeton dosyaya yazılsaydı doğrudan git
    | geçmişine girerdi ve geçmişten silmek force-push gerektirirdi.
    | Ortamdan okunuyor: `sys.env(...)`.
    |
    | ⚠️ Grafana Cloud jetonları `glc_` ile başlıyor; kalıp aranıyor.
    */
    $alloy = (string) file_get_contents(base_path('docker/alloy/config.alloy'));

    expect($alloy)->not->toContain('glc_');
    expect($alloy)->toContain('sys.env("LOKI_TOKEN")');
});

it('★★★ GOZLEM ALT ALAN ADLARI AYRILMIS — marka bunlari alamaz', function () {
    /*
    | ★ Bu satır olmadan bir marka `gozlem` alt alan adını kendi mağazası
    | olarak alabilirdi. O an Caddy'deki gözlem bloğu ile marka mağazası
    | aynı adrese bakar; `tenant:create` BAŞARILI görünür ve izleme
    | arayüzü SESSİZCE erişilemez olur.
    */
    foreach (['gozlem', 'grafana', 'loki', 'logs'] as $ad) {
        expect(ReservedSubdomains::ayrilmisMi($ad))->toBeTrue("ad: {$ad}");
    }
});

it('★★★ ISTEK KIMLIGI ETIKET DEGIL — sinirsiz kardinalite Lokiyi bogar', function () {
    /*
    | ★ Loki'de en pahalı hata bu. Etiketler indeksleniyor ve her
    | benzersiz birleşim ayrı bir akış açıyor. `istek_id` her istekte
    | farklı — etiket yapılsaydı her istek için bir akış doğar, indeks
    | şişer ve sorgular yavaşlar.
    |
    | ⚠️ İddia `stage.labels` BLOKLARINA bakıyor, dosyanın tamamına
    | değil: `istek_id` yapılandırmada `stage.json` içinde ve YORUMLARDA
    | zaten geçiyor. Ham metinde aransaydı test doğru kodda da kırılırdı.
    */
    $alloy = (string) file_get_contents(base_path('docker/alloy/config.alloy'));

    preg_match_all('/stage\.labels\s*\{(.*?)\}\s*\}/s', $alloy, $eslesmeler);

    expect($eslesmeler[1])->not->toBeEmpty('stage.labels bloğu bulunamadı');

    foreach ($eslesmeler[1] as $blok) {
        expect($blok)->not->toContain('istek_id');
    }
});

it('★★★ TOPLAYICI DOCKER SOKETI BAGLAMIYOR — host uzerinde root yetkisi', function () {
    /*
    | ★ Alloy'un standart yolu `/var/run/docker.sock` bağlamak. O soket
    | konteynere host üzerinde root eşdeğeri yetki verir: toplayıcıda bir
    | açık, doğrudan makinenin tamamı demek.
    |
    | Bu projede kullanıcının yazdığı Blade bile RCE riski yüzünden
    | reddedildi (4-K5); aynı ölçü burada da uygulanıyor.
    */
    expect(composeServisi('alloy'))->not->toContain('docker.sock');
});

it('★★★ TOPLAYICININ OKUDUGU BAGLAMALAR SALT-OKUNUR', function () {
    $alloy = composeServisi('alloy');

    expect($alloy)->toContain('./storage/logs:/logs:ro');
    expect($alloy)->toContain('caddy_logs:/caddy-logs:ro');
});

it('★★★ MAKINE GUNLUGU AYRI KLASORDE — ayni satir iki kez toplanmasin', function () {
    /*
    | ⚠️ Toplayıcı `logs/*.log` desenini okusaydı insan günlüğünü de
    | çeker ve her satır İKİ KEZ toplanırdı (biri ayrıştırılamaz metin
    | olarak). Ayrım klasörle yapılıyor.
    */
    expect(config('logging.channels.json.handler_with.filename'))
        ->toContain('logs/json/');

    $alloy = (string) file_get_contents(base_path('docker/alloy/config.alloy'));

    expect($alloy)->toContain('/logs/json/app*.json');
});

it('★★★ JSON KANALI GERCEKTEN JSON URETIYOR — tap bicimlendiriciyi EZMIYOR', function () {
    /*
    | ★ `IstekBaglami` bütün işleyicilere `LineFormatter` takıyor; JSON
    | kanalında da taksaydı satır insan biçiminde yazılır ve toplayıcı
    | hiçbir alan çıkaramazdı — üstelik bu HATA VERMEZ: Loki'de her satır
    | tek bir metin olur ve `marka` diye bir alan hiç doğmaz.
    |
    | ⚠️ İLK HÂLİ `Log::build()` İLE KURUYORDU VE HİÇBİR ŞEY ÖLÇMÜYORDU.
    | Kırma denemesi (koruma kaldırıldı) düşmedi; sebebi ölçüldü:
    | `Log::build()` yapılandırmadan gelmeyen bir kanal ürettiği için
    | `tap` HİÇ uygulanmıyor. Yani test, ezilip ezilmediğini sınadığını
    | sanıyordu ama işleyici hiç devrede değildi.
    |
    | Artık uygulamanın KENDİ çözdüğü `json` kanalı alınıyor ve gerçekten
    | bir satır yazdırılıp dosyadan okunuyor.
    */
    $imza = 'B6-olcum-'.uniqid();

    Log::channel('json')->error($imza);

    $dosya = storage_path('logs/json/app-'.now()->format('Y-m-d').'.json');

    expect(file_exists($dosya))->toBeTrue('json kanalı dosya üretmedi');

    $satirlar = array_filter(
        explode("\n", (string) file_get_contents($dosya)),
        fn (string $satir): bool => str_contains($satir, $imza),
    );

    expect($satirlar)->not->toBeEmpty('yazılan satır dosyada yok');

    $cozulen = json_decode((string) array_pop($satirlar), true);

    expect($cozulen)->toBeArray('satır JSON değil — tap biçimlendiriciyi ezmiş');
    expect($cozulen['message'] ?? null)->toBe($imza);
    expect($cozulen)->toHaveKey('extra');
});

it('★★★ CADDY ERISIM GUNLUGU DOSYAYA yaziliyor — toplayici okuyabilsin', function () {
    /*
    | ⚠️ Varsayılan `log` stdout'a yazıyor; toplayıcı onu ancak Docker
    | soketiyle okuyabilirdi ve o soket bilerek bağlanmıyor.
    */
    $caddyfile = (string) file_get_contents(base_path('docker/Caddyfile'));

    expect(substr_count($caddyfile, 'output file /var/log/caddy/access.log'))
        ->toBeGreaterThanOrEqual(2);
});

it('★★★ DISARI ACIK GOZLEM YUZEYI YOK — arayuz bulutta', function () {
    /*
    | ★ Önce Grafana kendi makinemizde koşuyordu ve Caddy'de ters vekil
    | bir blok vardı. Karar değişti (B6.1): arayüz Grafana Cloud'da, yani
    | dışarıya açılacak bir yüzeyimiz kalmadı.
    |
    | ⚠️ Blok geri gelirse hedefi olmayan bir ters vekil doğar ve istek
    | 502'ye düşer — üstelik `*.localhost` jokerinden GEÇMEDİĞİ için
    | "marka yok" bile demez.
    */
    $caddyfile = (string) file_get_contents(base_path('docker/Caddyfile'));

    expect($caddyfile)->not->toContain('reverse_proxy grafana:');
});

it('★★★ GOZLEM ADLARI HALA AYRILMIS — servis gitse de ad korunuyor', function () {
    /*
    | ⚠️ Yerel Grafana kaldırıldı ama adlar listede KALIYOR: bir markanın
    | `grafana.tikmarka.com` alması bugün de istenmez (kimliğe bürünme),
    | ve karara geri dönülürse ad çakışırdı.
    */
    expect(ReservedSubdomains::ayrilmisMi('grafana'))->toBeTrue();
});
