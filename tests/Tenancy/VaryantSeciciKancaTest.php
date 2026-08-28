<?php

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use Illuminate\Support\Facades\File;

/*
| VARYANT SEÇİCİSİNİN KANCALARI — İKİ DÜZENDE DE (4.6AL)
|
| ★ ÖLÇÜLEN KUSUR: `vitrinli` düzeninde varyant seçicisi VARDI ama betiğin
| aradığı iki işaret YOKTU (`data-fiyat`, `data-ekle-dugme`). Sonuçları,
| gerçek tarayıcıda ölçüldü:
|
|   seçim yok    → gizli variant_uuid BOŞ, "Sepete ekle" düğmesi AÇIK
|                  (müşteri boş gönderip doğrulama hatası alıyordu)
|   seçim yapıldı → Uncaught TypeError: Cannot set properties of null
|                   (setting 'disabled')
|                  → fiyat güncellenmiyor, uyarı mesajı HİÇ çıkmıyor
|
| ⚠️ Marka-a tam olarak `vitrinli` kullanıyor — yani kusur canlıydı.
|
| ⚠️ 4.6A'NIN AYNI DERSİ, ÜÇÜNCÜ KEZ. 4.6A'da seçici yalnızca `sade`'ye
| eklenmişti; 4.6A.1 onu `vitrinli`'ye taşıdı ama DESTEKLEYİCİ ALANLARI
| taşımadı. "Bitti kaydı bittiğinin kanıtı değildir."
|
| ★ BU TEST KANCA LİSTESİNİ BETİKTEN OKUYOR. Elle yazılsaydı betiğe yeni
| bir kanca eklenince liste bayat kalır ve test yine yalan söylerdi.
*/

/**
 * Varyant betiğinin BELGE düzeyinde aradığı işaretler.
 *
 * ⚠️ `kok.querySelector(...)` ile aranan işaretler HARİÇ: onlar
 * seçicinin kendi ortak parçasında ve düzenden bağımsız.
 *
 * @return list<string>
 */
function seciciKancalari(): array
{
    $betik = (string) File::get(
        base_path('resources/views/storefront/partials/varyant-betigi.blade.php')
    );

    // ⚠️ Yorumlar ayıklanıyor: kancaları ANLATAN yorum da aynı metni
    //    içeriyor ve iddia yönerge bozulsa bile yeşil kalırdı (4.6AE).
    $kod = (string) preg_replace('!/\*.*?\*/!s', '', $betik);
    $kod = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $kod);

    preg_match_all('/document\.querySelector\(\s*[\'"]\[([a-z-]+)\]/', $kod, $m);

    return array_values(array_unique($m[1]));
}

it('★★★ BETIGIN ARADIGI her kanca IKI DUZENDE de CIZILIYOR', function () {
    $kancalar = seciciKancalari();

    /*
    | ⚠️ Liste boş çıkarsa test hiçbir şey ölçmez — desen bozulduysa
    | sessizce yeşil kalmasın.
    */
    expect($kancalar)->not->toBeEmpty();

    /*
    | ⚠️ ÇOK EKSENLİ ürün şart: eksensiz iki varyantın `options` alanı
    | ikisinde de `[]` oluyor ve ikincisi `(product_id, options)`
    | benzersizliğine takılıyor (4.5L'de ölçülen tuzak).
    */
    $urun = seciciUrunu();

    /*
    | ★ İDDİA DOSYADA DEĞİL ÇİZİLEN SAYFADA kuruluyor — ve bu ayrım
    | testin ilk hâlinin bulduğu şeydi: `data-secici` ve
    | `data-varyant-uuid` ORTAK PARÇADAN geliyor, düzen dosyasında hiç
    | geçmiyor. Dosyaya bakan iddia onları "eksik" sanıyordu.
    |
    | Çizilen HTML'e bakmak hem doğru hem daha güçlü: parçanın nereden
    | geldiği önemli değil, müşterinin aldığı sayfada olması önemli.
    */
    $eksik = [];

    foreach (['sade', 'vitrinli'] as $duzen) {
        app(SettingsService::class)->yaz(SettingGroup::Theme, 'layout', $duzen);

        $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")
            ->assertOk()
            ->getContent();

        /*
        | ⚠️ `<script>` BLOKLARI AYIKLANIYOR — ve bu satır olmadan test
        | HİÇBİR ŞEY ÖLÇMÜYORDU.
        |
        | Varyant betiği sayfanın İÇİNDE ve kancaları adıyla arıyor
        | (`document.querySelector('[data-ekle-dugme]')`). Yani aranan
        | dizge, öznitelik silinse bile HTML'de duruyor. Kırma denemesi
        | `data-ekle-dugme`'yi düzenden kaldırdığında test yeşil kaldı.
        |
        | Aynı aile: 4.6AE'de iddia kuralı ANLATAN yorumu okuyordu,
        | burada kuralı ARAYAN betiği okuyor.
        */
        $kod = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $kod = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $kod);

        foreach ($kancalar as $kanca) {
            if (! str_contains($kod, $kanca)) {
                $eksik[] = "{$duzen} → {$kanca}";
            }
        }
    }

    expect($eksik)->toBe([]);
});

it('★★ BETIK eksik kancada COKMUYOR — geri kalani calisiyor', function () {
    $betik = (string) File::get(
        base_path('resources/views/storefront/partials/varyant-betigi.blade.php')
    );

    $kod = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $betik);
    $kod = (string) preg_replace('!/\*.*?\*/!s', '', $kod);

    /*
    | ⚠️ Koruma sorunu GİZLEMİYOR — yukarıdaki iki test kancaların
    | varlığını ölçüyor. Buradaki guard yalnızca "bir işaret eksikse
    | betiğin GERİ KALANI çalışmaya devam etsin" diyor: eskiden tek bir
    | eksik işaret, altındaki uyarı mantığını da öldürüyordu.
    */
    expect($kod)->toMatch('/if\s*\(\s*dugme\s*\)/')
        ->and($kod)->toMatch('/fiyatAlani\s*\)/');
});
