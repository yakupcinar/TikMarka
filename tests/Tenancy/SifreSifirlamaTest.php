<?php

use App\Domain\Settings\StorePublication;
use App\Mail\PasswordResetMail;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/*
| ŞİFRE SIFIRLAMA (4.6V) — güvenlik taramasında bulunan boşluk.
|
| ★ ÖNCESİNDE HİÇBİR YOL YOKTU: şifresini unutan müşteri ya da personel
| hesabına bir daha giremiyordu; tek çözüm geliştiricinin elle bcrypt
| hash yazmasıydı. Bu oturumda bunu birkaç kez elle yapmak zorunda
| kalmak, bloğun varlık sebebi oldu.
|
| ⚠️ EN KRİTİK İDDİA: müşteri ve personel AYRI tablo kullanıyor. Laravel
| jetonu yalnızca E-POSTAYA göre saklıyor; tek tablo paylaşılsaydı aynı
| e-postalı müşteri, personel parolasını ele geçirebilirdi.
*/

function sifreMagazasi(): void
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
}

it('★★★ MUSTERI sifresini sifirlayabiliyor — UCTAN UCA', function () {
    sifreMagazasi();

    $musteri = Customer::create([
        'name' => 'Ayşe', 'email' => 'ayse@ornek.test', 'password' => bcrypt('eskisifre'),
    ]);

    /*
    | ⚠️ Jeton MODELDEN OKUNMUYOR, gerçek akıştan alınıyor: istek → jeton
    | üretimi → sıfırlama. `Password::createToken()` çağırmak testi yeşil
    | tutar ama "müşteri bu jetonu nereden bulacak" sorusunu sormaz
    | (1D.6'nın dersi).
    */
    $this->post('http://marka-a.test/sifremi-unuttum', ['email' => 'ayse@ornek.test'])
        ->assertRedirect();

    $kayit = DB::table('password_reset_tokens')->where('email', 'ayse@ornek.test')->first();
    expect($kayit)->not->toBeNull();

    // ⚠️ Ham jeton veritabanında HASH'li duruyor; postadaki hâlini
    // yeniden üretemeyiz. Bu yüzden broker'ın kendi jetonu kullanılıyor.
    $jeton = Password::broker('customers')->createToken($musteri);

    $this->post('http://marka-a.test/sifre-sifirla', [
        'token' => $jeton,
        'email' => 'ayse@ornek.test',
        'password' => 'yenisifre123',
        'password_confirmation' => 'yenisifre123',
    ])->assertRedirect('http://marka-a.test/giris');

    // ★ ASIL ÖLÇÜM: GERÇEK giriş isteği. `Hash::check` yazmak yetmezdi —
    // çift hash'lenmiş bir parola orada da doğru görünebilirdi.
    $this->post('http://marka-a.test/giris', [
        'email' => 'ayse@ornek.test', 'password' => 'yenisifre123',
    ])->assertRedirect();

    expect(auth('customer-web')->check())->toBeTrue();
});

it('★★★ MUSTERI jetonu PERSONEL sifresini DEGISTIREMIYOR — ayri tablo', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    /*
    | ★ AYNI E-POSTA hem müşteri hem personel. Gerçek hayatta olağan:
    | marka çalışanı kendi mağazasından alışveriş yapıyor.
    */
    $ortakEposta = 'ortak@ornek.test';

    Customer::create(['name' => 'Ortak', 'email' => $ortakEposta, 'password' => bcrypt('musteri-sifre')]);

    $personel = User::factory()->create(['email' => $ortakEposta, 'password' => bcrypt('personel-sifre')]);

    // Müşteri broker'ından jeton al.
    $musteriJetonu = Password::broker('customers')->createToken(
        Customer::where('email', $ortakEposta)->firstOrFail()
    );

    /*
    | ⚠️ ASIL SALDIRI: müşteri jetonunu PANEL sıfırlama ucuna gönder.
    | Tek tablo paylaşılsaydı bu çalışır ve vitrinden herkesin açabildiği
    | bir hesap, panel personelinin parolasını ele geçirirdi.
    */
    $this->post('http://marka-a.test/yonetim/sifre-sifirla', [
        'token' => $musteriJetonu,
        'email' => $ortakEposta,
        'password' => 'saldirgan123',
        'password_confirmation' => 'saldirgan123',
    ])->assertSessionHas('hata');

    // Personel parolası DEĞİŞMEMELİ.
    expect(Hash::check('personel-sifre', (string) $personel->refresh()->password))->toBeTrue()
        ->and(Hash::check('saldirgan123', (string) $personel->password))->toBeFalse();
});

it('★★★ HESAP VARLIGI SIZDIRILMIYOR — olan ve olmayan AYNI cevabi aliyor', function () {
    sifreMagazasi();

    Customer::create(['name' => 'Var', 'email' => 'var@ornek.test', 'password' => bcrypt('x')]);

    $varCevap = $this->post('http://marka-a.test/sifremi-unuttum', ['email' => 'var@ornek.test']);
    $yokCevap = $this->post('http://marka-a.test/sifremi-unuttum', ['email' => 'yok@ornek.test']);

    /*
    | ⚠️ Laravel'in hazır davranışı "bu e-posta kayıtlı değil" diyor;
    | o cevap saldırgana ÜYE LİSTESİ çıkarma imkânı verirdi. Müşteri
    | listesi ticari bir varlık.
    */
    expect($varCevap->getStatusCode())->toBe($yokCevap->getStatusCode())
        ->and(session('mesaj'))->toContain('Eğer bu adrese kayıtlı');

    $yokCevap->assertSessionHasNoErrors();
});

it('★★★ SIFIRLAMA POSTASI MARKA adiyla ve KUYRUKTAN gidiyor', function () {
    sifreMagazasi();
    Mail::fake();

    Customer::create(['name' => 'Ayşe', 'email' => 'ayse@ornek.test', 'password' => bcrypt('x')]);

    $this->post('http://marka-a.test/sifremi-unuttum', ['email' => 'ayse@ornek.test']);

    /*
    | ⚠️ `assertQueued` — `assertSent` DEĞİL. Tüm postalar kuyrukta
    | (2H-K1); `assertSent` yazsaydı test hiçbir şey görmezdi.
    |
    | ⚠️ Laravel'in hazır `ResetPassword` bildirimi platform adıyla
    | giderdi; müşteri onu tanımaz.
    */
    Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) {
        return str_contains($mail->adres, '/sifre-sifirla/') && $mail->panel === false;
    });
});

it('★★★ PERSONEL postasi PANEL adresine gidiyor — musteri ekranina DEGIL', function () {
    markaKur('marka-a.test');
    Mail::fake();

    User::factory()->create(['email' => 'personel@ornek.test']);

    $this->post('http://marka-a.test/yonetim/sifremi-unuttum', ['email' => 'personel@ornek.test']);

    /*
    | ⚠️ Tek adres yazılsaydı personel müşteri ekranına düşerdi ve orada
    | `staff` jetonu geçersiz olduğu için "bağlantı geçersiz" alırdı —
    | sebebi hiç anlaşılmazdı.
    */
    Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) {
        return str_contains($mail->adres, '/yonetim/sifre-sifirla/') && $mail->panel === true;
    });
});

it('★★★ JETON TEK KULLANIMLIK — ikinci deneme reddediliyor', function () {
    sifreMagazasi();

    $musteri = Customer::create([
        'name' => 'Ayşe', 'email' => 'ayse@ornek.test', 'password' => bcrypt('eski'),
    ]);

    $jeton = Password::broker('customers')->createToken($musteri);

    $veri = [
        'token' => $jeton,
        'email' => 'ayse@ornek.test',
        'password' => 'birinci123',
        'password_confirmation' => 'birinci123',
    ];

    $this->post('http://marka-a.test/sifre-sifirla', $veri)->assertRedirect('http://marka-a.test/giris');

    /*
    | ⚠️ Aynı jetonla ikinci sıfırlama ENGELLENMELİ. Engellenmeseydi
    | postası ele geçirilen bir kullanıcının hesabı, o bağlantı süresi
    | boyunca tekrar tekrar ele geçirilebilirdi.
    */
    $ikinci = $veri;
    $ikinci['password'] = 'ikinci123';
    $ikinci['password_confirmation'] = 'ikinci123';

    $this->post('http://marka-a.test/sifre-sifirla', $ikinci)->assertSessionHas('hata');

    expect(Hash::check('birinci123', (string) $musteri->refresh()->password))->toBeTrue();
});

it('★★★ SILINEMEYEN "users" BROKERI capraz bagli DEGIL — kanitlanmis acik', function () {
    /*
    | ★ BU TEST GERÇEK BİR AÇIĞI BEKLİYOR.
    |
    | ⚠️ Laravel 11+ çerçeve config'ini birleştiriyor: `users` broker'ı
    | `config/auth.php`'den silinse bile çalışma anında var oluyor. Ve
    | çerçevenin varsayılanı ÇAPRAZ BAĞLI:
    |
    |     users broker tablosu  → password_reset_tokens  (MÜŞTERİ)
    |     users provider modeli → App\Models\User        (PERSONEL)
    |
    | Sömürülebilirliği GERÇEK BİR DENEMEYLE kanıtlandı: vitrinden alınan
    | müşteri jetonu `Password::broker('users')` üzerinden PERSONEL
    | parolasını değiştirdi (`passwords.reset` döndü).
    |
    | Silinemediği için tutarlı kılındı: artık `staff` ile aynı provider
    | ve aynı tabloya bakıyor.
    */
    expect(config('auth.passwords.users.provider'))->toBe('staff')
        ->and(config('auth.passwords.users.table'))->toBe('staff_password_reset_tokens')
        ->and(config('auth.defaults.passwords'))->toBe('customers');

    /*
    | ⚠️ ASIL ÖLÇÜM AYARDA DEĞİL DAVRANIŞTA: müşteri jetonuyla personel
    | parolası DEĞİŞMEMELİ. Yalnızca config'e bakan bir test, ayar
    | doğruyken davranışın bozuk olduğu bir durumu göremezdi.
    */
    markaKur('marka-a.test');

    $eposta = 'capraz@ornek.test';

    $musteri = Customer::create(['name' => 'M', 'email' => $eposta, 'password' => bcrypt('musteri')]);
    $personel = User::factory()->create(['email' => $eposta, 'password' => bcrypt('personel-gizli')]);

    $musteriJetonu = Password::broker('customers')->createToken($musteri);

    $sonuc = Password::broker('users')->reset([
        'token' => $musteriJetonu,
        'email' => $eposta,
        'password' => 'ele-gecirildi',
        'password_confirmation' => 'ele-gecirildi',
    ], function ($kullanici, string $sifre): void {
        $kullanici->password = $sifre;
        $kullanici->save();
    });

    expect($sonuc)->not->toBe(Password::PASSWORD_RESET)
        ->and(Hash::check('personel-gizli', (string) $personel->refresh()->password))->toBeTrue();
});

/*
| ⚠️ AŞAĞIDAKİ İKİ TEST GERÇEK KULLANIMDA BULUNAN BİR HATADAN DOĞDU.
|
| Blade formunun `action`'ı `route('vitrin.sifre.sifirla')` yazıyordu ve o
| GET rotasının adı (`/sifre-sifirla/{token}`). POST rotası İSİMSİZ ve
| farklı adresteydi (`/sifre-sifirla`), yani tarayıcı formu gönderince
| 405 Method Not Allowed alıyordu.
|
| ⚠️ MEVCUT TESTLERİN HİÇBİRİ GÖREMEZDİ: hepsi doğrudan doğru adrese
| POST ediyordu (`$this->post('/sifre-sifirla', ...)`), yani formun
| NEREYE gittiğini hiç sormuyorlardı. Bu, CLAUDE.md'deki "form alanları
| doğrulamayla hizalı olmalı — testler bunu göremez" tuzağının adres
| tarafı.
|
| Aşağıdaki testler TARAYICININ yaptığını yapıyor: sayfayı render et,
| formun `action`'ını OKU, tam oraya gönder.
*/

it('★★★ SIFIRLAMA FORMUNUN adresi POST KABUL EDIYOR — tarayici gibi', function () {
    sifreMagazasi();

    $musteri = Customer::create([
        'name' => 'Ayşe', 'email' => 'ayse@ornek.test', 'password' => bcrypt('eski'),
    ]);

    $jeton = Password::broker('customers')->createToken($musteri);

    $html = $this->get("http://marka-a.test/sifre-sifirla/{$jeton}?email=ayse@ornek.test")
        ->assertOk()
        ->getContent();

    // ⚠️ Adres SAYFADAN okunuyor, testin kendi bildiği adresten değil.
    // ⚠️ POST formu seçiliyor: düzenin başlığında bir de arama formu var
    // (`method="get"`) ve sayfada ÖNCE o geliyor.
    preg_match('/<form[^>]+method="post"[^>]+action="([^"]+)"/', (string) $html, $eslesme);
    $adres = $eslesme[1] ?? '';
    expect($adres)->not->toBe('');

    $this->post($adres, [
        'token' => $jeton,
        'email' => 'ayse@ornek.test',
        'password' => 'yenisifre123',
        'password_confirmation' => 'yenisifre123',
    ])->assertRedirect('http://marka-a.test/giris');

    // ★ Gerçek giriş: parola gerçekten değişmiş mi.
    $this->post('http://marka-a.test/giris', [
        'email' => 'ayse@ornek.test', 'password' => 'yenisifre123',
    ])->assertRedirect();

    expect(auth('customer-web')->check())->toBeTrue();
});

it('★★★ ISTEK FORMUNUN adresi de POST KABUL EDIYOR', function () {
    sifreMagazasi();

    $html = $this->get('http://marka-a.test/sifremi-unuttum')->assertOk()->getContent();

    // ⚠️ Aynı sebeple POST formu: ilk form başlıktaki aramadır.
    preg_match('/<form[^>]+method="post"[^>]+action="([^"]+)"/', (string) $html, $eslesme);
    $adres = $eslesme[1] ?? '';
    expect($adres)->not->toBe('');

    $this->post($adres, ['email' => 'kimse@ornek.test'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('★★ E-POSTA form ALANI DEGIL — degeri gizli alanda tasiniyor', function () {
    sifreMagazasi();

    $musteri = Customer::create([
        'name' => 'Ayşe', 'email' => 'ayse@ornek.test', 'password' => bcrypt('eski'),
    ]);

    $jeton = Password::broker('customers')->createToken($musteri);

    $html = (string) $this->get("http://marka-a.test/sifre-sifirla/{$jeton}?email=ayse@ornek.test")
        ->assertOk()->getContent();

    /*
    | ⚠️ Önce `readonly` bir kutuydu ve doldurulamayan bir form alanı gibi
    | görünüyordu. Artık düz metin: hangi hesabın şifresinin değiştiği
    | görünüyor ama kutu yok. Değer gizli alanda POST gövdesine giriyor.
    */
    expect($html)->toContain('name="email"')
        ->and($html)->toContain('type="hidden"')
        ->and($html)->not->toContain('readonly')
        ->and($html)->toContain('ayse@ornek.test');
});
