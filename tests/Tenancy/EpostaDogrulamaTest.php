<?php

use App\Domain\Settings\StorePublication;
use App\Mail\EmailVerificationMail;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\User;
use App\Platform\Models\Tenant;
use Illuminate\Support\Facades\Mail;

/*
| E-POSTA DOĞRULAMA (4.6W) — güvenlik listesinin dördüncü ve son maddesi.
|
| ★ YUMUŞAK KAPI ve bu bir KARAR, eksiklik değil: `/odeme` kimlik
| istemiyor (misafir ödemesi açık, ölçülüyor). Doğrulanmamış müşterinin
| ödemesi engellenseydi hesap açmayan rahat alışveriş yapar, hesap açan
| yapamazdı — üstelik saldırgan çıkış yapıp misafir olarak alırdı. Yani
| sert kapı satışı kırar, kimseyi durdurmaz.
|
| ⚠️ EN KRİTİK İDDİA: imza MARKAYA bağlı. APP_KEY bütün markalarda AYNI;
| alan adı imzanın dışında kalsaydı A'da üretilen bağlantı B'de de
| geçerli olurdu.
*/

/** @return array{tenant: Tenant, sahip: User} */
function dogrulamaMagazasi(string $alanAdi = 'marka-a.test'): array
{
    $marka = markaKur($alanAdi);
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    return $marka;
}

function dogrulanmamisMusteri(string $eposta = 'ayse@ornek.test'): Customer
{
    return Customer::create([
        'name' => 'Ayşe', 'email' => $eposta, 'password' => bcrypt('sifre12345'),
    ]);
}

it('★★★ KAYIT doğrulama postasi TETIKLIYOR — kuyruktan ve MARKA adiyla', function () {
    dogrulamaMagazasi();
    Mail::fake();

    $this->post('http://marka-a.test/kayit', [
        'name' => 'Yeni Müşteri',
        'email' => 'yeni@ornek.test',
        'password' => 'sifre12345',
        'password_confirmation' => 'sifre12345',
    ])->assertRedirect();

    // ⚠️ `assertQueued` — `assertSent` DEĞİL. BrandMail ShouldQueue;
    // gönderim kuyruktan yapılıyor ve `assertSent` hiçbir şey görmez.
    Mail::assertQueued(EmailVerificationMail::class, function ($posta) {
        return $posta->hasTo('yeni@ornek.test');
    });

    $musteri = Customer::where('email', 'yeni@ornek.test')->firstOrFail();
    expect($musteri->hasVerifiedEmail())->toBeFalse();
});

it('★★★ IMZALI baglanti adresi DOGRULUYOR — giris GEREKMEDEN', function () {
    dogrulamaMagazasi();
    $musteri = dogrulanmamisMusteri();

    $adres = dogrulamaAdresi($musteri);

    /*
    | ⚠️ Oturum AÇILMADAN çağrılıyor. Müşteri bağlantıya çoğu zaman
    | telefonundan tıklıyor; `auth` konsaydı bağlantı orada çalışmaz ve
    | doğrulama pratikte ulaşılamaz olurdu.
    */
    $this->get($adres)->assertRedirect('http://marka-a.test/giris');

    expect($musteri->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('★★★ IMZASIZ adres REDDEDILIYOR — 403', function () {
    dogrulamaMagazasi();
    $musteri = dogrulanmamisMusteri();

    /*
    | ⚠️ Bu testin ölçtüğü şey doğrulamanın VARLIK SEBEBİ. İmza olmasaydı
    | uuid'i bilen (ya da vitrinden okuyan) biri başkasının adresini
    | "doğrulanmış" işaretlerdi — o zaman `email_verified_at` hiçbir şey
    | kanıtlamazdı.
    */
    $ham = 'http://marka-a.test/e-posta-dogrula/'.$musteri->uuid.'/'.sha1((string) $musteri->email);

    $this->get($ham)->assertForbidden();

    expect($musteri->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('★★★ A MARKASININ baglantisi B MARKASINDA calismiyor — APP_KEY ortak', function () {
    $markaA = dogrulamaMagazasi('marka-a.test');
    $musteri = dogrulanmamisMusteri();
    $adres = dogrulamaAdresi($musteri);

    // Aynı yolu B markasının alan adına taşı — imza alan adını kapsıyorsa düşmeli.
    $capraz = str_replace('marka-a.test', 'marka-b.test', $adres);

    dogrulamaMagazasi('marka-b.test');

    /*
    | ⚠️ OTURUM TEMİZLENİYOR — ve bu testin en öğretici parçası.
    |
    | İlk hâli temizlemiyordu ve 403 yerine 302 alıyordu: test istemcisi
    | çerez takip ediyor (CLAUDE.md), A'da giriş yapmış oturum B'ye
    | taşınıyor ve `EnsureSessionTenant` isteği İMZA KONTROLÜNDEN ÖNCE
    | kesiyordu. Yani test imzayı değil 4.5D'de zaten ölçülen başka bir
    | korumayı ölçüyormuş.
    |
    | Gerçek senaryo zaten çerezsiz: bağlantıya postadan tıklayan kişi
    | B markasında oturum açmış değil.
    */
    $this->flushSession();

    $this->get($capraz)->assertForbidden();

    /*
    | ⚠️ `markaKur()` ile A'ya DÖNÜLMEZ — o kiracıyı yeniden OLUŞTURUYOR
    | ve alan adı doluyken `DomainOccupiedByOtherTenantException` fırlıyor.
    | Var olan kiracı yeniden başlatılıyor.
    */
    tenancy()->end();
    tenancy()->initialize($markaA['tenant']);

    expect(Customer::where('email', 'ayse@ornek.test')->firstOrFail()->hasVerifiedEmail())->toBeFalse();
});

it('★★★ E-POSTA DEGISINCE eski baglanti OLUYOR', function () {
    dogrulamaMagazasi();
    $musteri = dogrulanmamisMusteri();
    $adres = dogrulamaAdresi($musteri);

    /*
    | ⚠️ Hash olmasaydı "adresi değiştir, eski postadaki bağlantıya tıkla"
    | ile DOĞRULANMAMIŞ bir adres doğrulanmış olurdu.
    */
    $musteri->email = 'baska@ornek.test';
    $musteri->save();

    $this->get($adres)->assertRedirect('http://marka-a.test/giris');

    expect($musteri->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('★★ IKINCI TIKLAMA hata DEGIL', function () {
    dogrulamaMagazasi();
    $musteri = dogrulanmamisMusteri();
    $adres = dogrulamaAdresi($musteri);

    $this->get($adres)->assertRedirect();
    $ilk = (string) $musteri->refresh()->email_verified_at;

    // ⚠️ Postadaki bağlantı birden çok kez açılabilir (ön-yükleme, geri
    // tuşu). Hata gösterilseydi müşteri bir sorun olduğunu sanırdı.
    $this->get($adres)->assertRedirect()->assertSessionHasNoErrors();

    // ⚠️ Damga DEĞİŞMEMELİ: ikinci tıklama yeniden işaretleseydi
    // doğrulama zamanı sürekli ileri kayar ve denetimde yanıltırdı.
    expect((string) $musteri->refresh()->email_verified_at)->toBe($ilk);
});

it('★★★ ODEME doğrulanmamis musteriye ACIK — misafir odemesi varken kapi ANLAMSIZ', function () {
    // ⚠️ `sayacMagazasi()` markayı kurup SATILABİLİR bir varyant bırakıyor;
    // `dogrulamaMagazasi()` ürün üretmiyor ve sepet doldurulamıyordu.
    sayacMagazasi();

    /*
    | ⚠️ Bu test bir ÖZELLİĞİ değil bir KARARI koruyor. Biri "güvenlik"
    | diye ödemeye doğrulama kapısı koyarsa burası düşer ve gerekçeyi
    | okur: misafir ödemesi açık olduğu için o kapı satışı kırar,
    | saldırganı durdurmaz (çıkış yapıp misafir olarak alır).
    */
    $musteri = dogrulanmamisMusteri();
    $this->post('http://marka-a.test/giris', [
        'email' => 'ayse@ornek.test', 'password' => 'sifre12345',
    ])->assertRedirect();

    expect($musteri->refresh()->hasVerifiedEmail())->toBeFalse();

    /*
    | ⚠️ Sepet GERÇEKTEN dolduruluyor. Boş sepetle `/odeme` zaten sepete
    | yönlendiriyor — o 302'yi "kapı yok" diye okumak testi yalancı
    | yapardı: doğrulama kapısı eklense bile aynı 302 gelirdi.
    */
    $varyant = ProductVariant::firstOrFail();

    $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid, 'quantity' => 1,
    ])->assertRedirect();

    $this->get('http://marka-a.test/odeme')->assertOk();
    $this->get('http://marka-a.test/hesabim')->assertOk();
});

it('★★★ DOGRULANMAMIS musteri YORUM YAZAMIYOR — 403 ve YAPILACAK SEYI soyluyor', function () {
    $hazir = teslimAlmisMusteri('marka-a.test');
    $musteri = $hazir['musteri'];

    /*
    | ⚠️ Fabrika artık DOĞRULANMIŞ üretiyor (4.6W); burada AÇIKÇA geri
    | alınıyor. Kapının ölçüldüğü tek yer burası olduğu için niyetin
    | görünür olması gerekiyor.
    */
    $musteri->forceFill(['email_verified_at' => null])->save();

    expect($musteri->refresh()->hasVerifiedEmail())->toBeFalse();

    $cevap = $this->withHeader('Authorization', 'Bearer '.$musteri->createToken('test')->plainTextToken)
        ->postJson('http://marka-a.test/api/products/'.$hazir['urun']->slug.'/reviews', [
            'rating' => 5, 'body' => 'Ürün gayet iyi, hızlı geldi.',
        ]);

    $cevap->assertForbidden();

    /*
    | ⚠️ `NotPurchasedException` ile AYNI kodu (403) döndürüyor. Mesaj
    | ayrımı olmasaydı ekran "satın almadınız" derdi ve müşteri
    | doğrulama adımını hiç göremezdi — oysa satın almış.
    */
    expect($cevap->json('message'))->toContain('doğrula')
        ->and($cevap->json('resolution'))->toContain('yeniden gönder');
});

it('★★★ DOGRULAYINCA yorum YAZILABILIYOR — kapinin tek sebebi bu', function () {
    $hazir = teslimAlmisMusteri('marka-a.test');
    $musteri = $hazir['musteri'];

    /*
    | ⚠️ `teslimAlmisMusteri()` mağazayı YAYINLAMIYOR — yayınlanmamış
    | mağazada vitrin uçları 503 döner ve giriş yapılamaz. Aynı tuzağa
    | 4.6T'de de düşülmüştü (`yorumaHazir`).
    |
    | ⚠️ Parola fabrikadan geliyor; yardımcı gerçek giriş yaptığı için
    | bildiğimiz bir değere çekiliyor.
    */
    app(StorePublication::class)->yayinla();
    $musteri->password = 'sifre12345';
    $musteri->forceFill(['email_verified_at' => null])->save();

    expect($musteri->refresh()->hasVerifiedEmail())->toBeFalse();

    $this->get(dogrulamaAdresi($musteri))->assertRedirect();
    expect($musteri->refresh()->hasVerifiedEmail())->toBeTrue();

    $this->withHeader('Authorization', 'Bearer '.$musteri->createToken('test')->plainTextToken)
        ->postJson('http://marka-a.test/api/products/'.$hazir['urun']->slug.'/reviews', [
            'rating' => 5, 'body' => 'Ürün gayet iyi, hızlı geldi.',
        ])->assertCreated();
});

it('★★★ HESAP SAYFASINDAKI formun ADRESI POST kabul ediyor — tarayici gibi', function () {
    dogrulamaMagazasi();
    dogrulanmamisMusteri();

    $this->post('http://marka-a.test/giris', [
        'email' => 'ayse@ornek.test', 'password' => 'sifre12345',
    ])->assertRedirect();

    $html = (string) $this->get('http://marka-a.test/hesabim')->assertOk()->getContent();

    expect($html)->toContain('doğrulanmadı');

    /*
    | ⚠️ 4.6V'DE TAM BURASI KIRILDI: form `route()` ile GET rotasını
    | üretiyordu ve müşteri 405 alıyordu; yedi test göremedi çünkü hepsi
    | DOĞRUDAN doğru adrese POST ediyordu.
    |
    | ⚠️ Regex `method="post"` ile daraltıldı — düzenin başlığındaki arama
    | formu (`method="get"`) sayfada ÖNCE geliyor ve ilk eşleşme odur.
    | Daraltılmazsa test düzeltilmiş kodda da düşer.
    */
    preg_match_all('/<form[^>]+method="post"[^>]+action="([^"]+)"/', $html, $eslesmeler);

    $dogrulama = array_values(array_filter(
        $eslesmeler[1],
        fn (string $adres): bool => str_contains($adres, 'e-posta-dogrula'),
    ));

    expect($dogrulama)->toHaveCount(1);

    $this->post($dogrulama[0])->assertRedirect()->assertSessionHasNoErrors();
});

it('★★★ YENIDEN GONDERME sinirli — 4. istek 429', function () {
    dogrulamaMagazasi();
    dogrulanmamisMusteri();

    $this->post('http://marka-a.test/giris', [
        'email' => 'ayse@ornek.test', 'password' => 'sifre12345',
    ])->assertRedirect();

    /*
    | ⚠️ Her istek BİR E-POSTA gönderiyor. Sınırsız bırakılsaydı iki
    | zarar: kurbanın gelen kutusu dolar ve SMTP kotası yanar — o kota
    | yandığında SİPARİŞ POSTALARI da gitmez.
    */
    for ($i = 0; $i < 3; $i++) {
        $this->post('http://marka-a.test/e-posta-dogrula/gonder')->assertRedirect();
    }

    $this->post('http://marka-a.test/e-posta-dogrula/gonder')->assertStatus(429);
});

it('★★ YENIDEN GONDERME ucu ADRESI ISTEKTEN ALMIYOR — oturumdan aliyor', function () {
    dogrulamaMagazasi();
    dogrulanmamisMusteri();
    dogrulanmamisMusteri('kurban@ornek.test');

    $this->post('http://marka-a.test/giris', [
        'email' => 'ayse@ornek.test', 'password' => 'sifre12345',
    ])->assertRedirect();

    Mail::fake();

    /*
    | ⚠️ Adres istekten alınsaydı bu uç HERKESE AÇIK bir posta gönderme
    | aracı olurdu: saldırgan istediği adrese, marka adıyla, sınırsız
    | posta tetiklerdi.
    */
    $this->post('http://marka-a.test/e-posta-dogrula/gonder', ['email' => 'kurban@ornek.test'])
        ->assertRedirect();

    Mail::assertQueued(EmailVerificationMail::class, fn ($posta) => $posta->hasTo('ayse@ornek.test'));
    Mail::assertNotQueued(EmailVerificationMail::class, fn ($posta) => $posta->hasTo('kurban@ornek.test'));
});
