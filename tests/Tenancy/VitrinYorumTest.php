<?php

use App\Domain\Review\ReviewService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\ReviewStatus;
use App\Enums\SettingGroup;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Review;

/*
| VİTRİNDE YORUMLAR (4.6C)
|
| ★ ÖLÇÜLEN BOŞLUK: uçlar 2E'de, panel moderasyonu 4.5F'de, doğrulama
| kapısı 4.6W'de yazılmıştı ama müşterinin yorumları GÖREBİLECEĞİ ya da
| YAZABİLECEĞİ bir ekran hiç yoktu. "Uç var ≠ kullanılabilir".
|
| ⚠️ Vitrin SUNUCUDA render ediliyor (4-K1), yani burada METİN aramak
| doğru yöntem. Panel bunun TERSİ (Inertia): orada cevap ekrandaki metni
| içermiyor, `component` ve `props` üzerinden iddia kurulur.
*/

/** @return array{musteri: Customer, urun: Product} */
function vitrinYorumHazir(): array
{
    $hazir = teslimAlmisMusteri('marka-a.test');
    app(StorePublication::class)->yayinla();

    $musteri = $hazir['musteri'];
    $musteri->password = 'sifre12345';
    $musteri->save();

    return ['musteri' => $musteri, 'urun' => $hazir['urun']];
}

it('★★★ ONAYLI yorum URUN SAYFASINDA gorunuyor — onaysiz GORUNMUYOR', function () {
    ['musteri' => $musteri, 'urun' => $urun] = vitrinYorumHazir();

    $yorum = app(ReviewService::class)->yaz($musteri, $urun, [
        'rating' => 5, 'title' => 'Harika ürün', 'body' => 'Beklediğimden hızlı geldi.',
    ]);

    $adres = "http://marka-a.test/urun/{$urun->slug}";

    /*
    | ⚠️ ÖNCE onaysız hâli ölçülüyor. Yalnızca "onaylı görünüyor" testi
    | yazılsaydı, moderasyonu hiç dinlemeyen bir ekran da geçerdi.
    */
    $html = (string) $this->get($adres)->assertOk()->getContent();

    expect($html)->not->toContain('Beklediğimden hızlı geldi.')
        ->and($html)->toContain('henüz yorum yapılmamış');

    $yorum->status = ReviewStatus::Approved;
    $yorum->moderated_at = now();
    $yorum->save();

    $html = (string) $this->get($adres)->assertOk()->getContent();

    expect($html)->toContain('Beklediğimden hızlı geldi.')
        ->and($html)->toContain('Harika ürün');
});

it('★★★ VITRINDE tam ad ve moderasyon notu YOK', function () {
    ['musteri' => $musteri, 'urun' => $urun] = vitrinYorumHazir();

    $musteri->name = 'Ahmet Yılmaz';
    $musteri->save();

    $yorum = app(ReviewService::class)->yaz($musteri, $urun, ['rating' => 4, 'body' => 'İyi ürün.']);
    $yorum->status = ReviewStatus::Approved;
    $yorum->moderated_at = now();
    $yorum->moderation_note = 'Personel notu — müşteri görmemeli';
    $yorum->save();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    /*
    | ⚠️ 2G'nin mantığı burada da geçerli: tam ad yazılsaydı müşterinin
    | kim olduğu vitrinde herkese açık olurdu.
    */
    expect($html)->toContain('Ahmet Y.')
        ->and($html)->not->toContain('Ahmet Yılmaz')
        ->and($html)->not->toContain('Personel notu')
        ->and($html)->not->toContain((string) $musteri->email);
});

it('★★★ MISAFIRE form YOK, GIRIS baglantisi VAR', function () {
    ['urun' => $urun] = vitrinYorumHazir();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('Yorumu gönder')
        ->and($html)->toContain('giriş yapın');
});

it('★★★ SATIN ALMAYAN musteri SEBEBINI goruyor — form YOK', function () {
    ['urun' => $urun] = vitrinYorumHazir();

    $baskasi = Customer::create([
        'name' => 'Zeynep', 'email' => 'zeynep@ornek.test', 'password' => bcrypt('sifre12345'),
    ]);
    $baskasi->forceFill(['email_verified_at' => now()])->save();

    $this->post('http://marka-a.test/giris', [
        'email' => 'zeynep@ornek.test', 'password' => 'sifre12345',
    ])->assertRedirect();

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    /*
    | ⚠️ Form gizlenip SUSULMUYOR: sebep yazılmasaydı müşteri "neden
    | yazamıyorum" sorusunu cevaplayamaz, destek yazardı.
    */
    expect($html)->not->toContain('Yorumu gönder')
        ->and($html)->toContain('teslim almış olmanız gerekiyor');
});

it('★★★ EKRANDAKI formun ADRESI POST kabul ediyor — tarayici gibi', function () {
    ['musteri' => $musteri, 'urun' => $urun] = vitrinYorumHazir();
    vitrinYorumGirisi($musteri);

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->toContain('Yorumu gönder');

    /*
    | ⚠️ Adres SAYFADAN okunuyor. 4.6V'de form `route()` ile GET rotasını
    | üretmiş ve müşteri 405 almıştı; testler görmemişti çünkü doğrudan
    | doğru adrese POST ediyorlardı. `method="post"` ile daraltıldı —
    | başlıktaki arama formu (`method="get"`) sayfada ÖNCE geliyor.
    */
    preg_match_all('/<form[^>]+method="post"[^>]+action="([^"]+)"/', $html, $eslesmeler);

    $yorumFormu = array_values(array_filter(
        $eslesmeler[1],
        fn (string $adres): bool => str_contains($adres, '/yorum'),
    ));

    expect($yorumFormu)->toHaveCount(1);

    $this->post(html_entity_decode($yorumFormu[0]), [
        'rating' => 5, 'title' => 'Süper', 'body' => 'Gerçekten memnun kaldım.',
    ])->assertRedirect("http://marka-a.test/urun/{$urun->slug}");

    // ★ Kayıt ONAY BEKLEYEREK açılıyor — doğrudan yayınlanmıyor.
    $yorum = Review::firstOrFail();

    expect($yorum->status)->toBe(ReviewStatus::Pending)
        ->and($yorum->body)->toBe('Gerçekten memnun kaldım.');
});

it('★★★ YAZDIKTAN SONRA "onay bekliyor" SOYLENIYOR ve yorum vitrinde YOK', function () {
    ['musteri' => $musteri, 'urun' => $urun] = vitrinYorumHazir();
    vitrinYorumGirisi($musteri);

    $this->post("http://marka-a.test/urun/{$urun->slug}/yorum", [
        'rating' => 5, 'body' => 'Gerçekten memnun kaldım.',
    ])->assertRedirect();

    /*
    | ⚠️ Söylenmeseydi müşteri yorumunu vitrinde göremeyip kaybolduğunu
    | sanır, ikinci kez yazmayı dener ve "zaten yorum yazdınız" uyarısı
    | alırdı — yani sessizlik, çıkmaza götürürdü.
    */
    expect((string) session('mesaj'))->toContain('onaylandıktan sonra');

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('Gerçekten memnun kaldım.');
});

it('★★★ IKINCI yorum HAM JSON degil, EKRANDA Turkce uyari', function () {
    ['musteri' => $musteri, 'urun' => $urun] = vitrinYorumHazir();
    vitrinYorumGirisi($musteri);

    $gonder = fn () => $this->post("http://marka-a.test/urun/{$urun->slug}/yorum", [
        'rating' => 5, 'body' => 'İlk yorumum burada.',
    ]);

    $gonder()->assertRedirect();

    /*
    | ★ TARAYICIYA HTML, API'YE JSON.
    |
    | ⚠️ Genel işleyici `DuplicateReviewException`'ı 409 JSON'a çeviriyor
    | ve o API için DOĞRU. Sayfa katmanında yakalanmasaydı müşteri ham
    | JSON görürdü — 4A · 4B · 4.5G · 4.5O'da dört kez yaşanan hata.
    */
    $cevap = $gonder();

    $cevap->assertRedirect();

    expect((string) session('hata'))->toContain('zaten yorum yazdınız');
});

it('★★★ DOGRULANMAMIS e-posta EKRANDA sebebini soyluyor', function () {
    ['musteri' => $musteri, 'urun' => $urun] = vitrinYorumHazir();

    // ⚠️ Fabrika doğrulanmış üretiyor (4.6W); burada AÇIKÇA geri alınıyor.
    $musteri->forceFill(['email_verified_at' => null])->save();

    vitrinYorumGirisi($musteri);

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('Yorumu gönder')
        ->and($html)->toContain('doğrulamanız gerekiyor');
});

it('★★ IKI DUZEN de yorumlari gosteriyor — tema bir AYAR', function () {
    ['musteri' => $musteri, 'urun' => $urun] = vitrinYorumHazir();

    $yorum = app(ReviewService::class)->yaz($musteri, $urun, ['rating' => 5, 'body' => 'Vitrinli düzende de görünmeli.']);
    $yorum->status = ReviewStatus::Approved;
    $yorum->moderated_at = now();
    $yorum->save();

    /*
    | ⚠️ Yorum bölümü ORTAK PARÇA. Kopyalansaydı biri güncellenip öteki
    | unutulur ve o düzeni seçmiş markanın müşterisi yorumları göremezdi.
    */
    app(SettingsService::class)
        ->yaz(SettingGroup::Theme, 'layout', 'vitrinli');

    $html = (string) $this->get("http://marka-a.test/urun/{$urun->slug}")->assertOk()->getContent();

    expect($html)->toContain('Vitrinli düzende de görünmeli.');
});
