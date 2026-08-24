<?php

use App\Models\Customer;
use App\Models\User;
use App\Platform\Models\PlatformUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'customer'),
        /*
        | ⚠️ Varsayılan broker `customers`: vitrin tarafı. Panel kendi
        | broker'ını AÇIKÇA seçiyor (`Password::broker('staff')`) —
        | varsayılana güvenmek 4.5I'deki guard hatasının aynısı olurdu.
        */
        'passwords' => env('AUTH_PASSWORD_BROKER', 'customers'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        /*
        | İKİ AYRI KİMLİK ALANI (1A.0).
        |
        | Müşteri ve personel farklı tablolarda, farklı yüzeylerden giriyor.
        | Tek guard olsaydı "bu token hangi tabloya ait" sorusu her istekte
        | tekrar sorulur ve bir gün biri unuturdu. Ayrı guard bu soruyu
        | rotanın kendisinde cevaplıyor: `auth:staff` yazan bir uca müşteri
        | token'ı giremez.
        |
        | İkisi de `sanctum` sürücüsü: kimlik doğrulama token tabanlı (K-12).
        | Oturum çerezi kullanılmıyor — panel ileride ayrı alt alan adına
        | taşınırsa çerez kapsamı sorun çıkarırdı.
        */

        // Vitrin tarafı — markanın müşterisi
        'customer' => [
            'driver' => 'sanctum',
            'provider' => 'customers',
        ],

        // Panel tarafı — markanın personeli
        'staff' => [
            'driver' => 'sanctum',
            'provider' => 'staff',
        ],

        /*
        | ★ PANEL SAYFALARI — OTURUM tabanlı. (4C-K3 · 4-K4'ün uygulaması)
        |
        | ⚠️ `staff` ile AYNI kullanıcı tablosu, FARKLI kapı:
        |
        |   staff      token   → API istemcisi, mobil, entegrasyon
        |   staff-web  oturum  → tarayıcıdaki panel (Inertia)
        |
        | Neden ayrı guard: Inertia sayfa gezinmesi çerezle çalışıyor,
        | tarayıcı özel `Authorization` başlığı gönderemiyor. Token guard'ını
        | oturuma çevirmek API istemcilerini kırardı.
        |
        | ⚠️ Yukarıdaki yorum "oturum çerezi kullanılmıyor, panel ileride
        | ayrı alt alan adına taşınırsa çerez kapsamı sorun çıkarır" diyordu.
        | M-3 (4-K1) panelin MARKANIN kendi alan adında `/yonetim` yolunda
        | duracağına karar verdi — yani ayrı alt alan adı yok ve çerez
        | kapsamı sorunu doğmuyor. Karar değişti, gerekçesiyle yazıldı.
        */
        'staff-web' => [
            'driver' => 'session',
            'provider' => 'staff',
        ],

        /*
        | ★ MÜŞTERİNİN OTURUM KİMLİĞİ (4.5D).
        |
        | Vitrin sunucuda render edilen Blade (4-K1) ve formlar JavaScript'siz
        | çalışıyor (4B-K1) — yani müşteri kimliği de çerezle taşınmak
        | zorunda. `customer` guard'ı (sanctum) DURUYOR: mobil uygulama ve
        | marka entegrasyonları onu kullanacak.
        |
        | ⚠️ İki guard AYNI sağlayıcıya bakıyor: aynı müşteri hem token hem
        | oturumla girebiliyor. Ayrı sağlayıcı verilseydi "aynı e-posta iki
        | kimlik" karmaşası doğardı.
        |
        | ⚠️ Oturum-marka damgası bu guard için de zorunlu (4H): oturum
        | yalnızca kullanıcı `id`'sini tutuyor ve guard onu İSTEĞİN
        | kiracısının şemasından çözüyor. [EnsureSessionTenant] iki guard'ı
        | birden kontrol ediyor.
        */
        'customer-web' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],

        /*
        | ★ ÜÇÜNCÜ KİMLİK ALANI (3C) — kontrol düzlemi.
        |
        | ⚠️ TıkMarka'yı işleten kişi; yetkisi BÜTÜN MARKALARA uzanıyor,
        | sistemdeki en tehlikeli yetki. Marka personeliyle aynı guard'da
        | olsaydı bir markanın sahibi merkez uçlara girebilirdi.
        |
        | ⚠️ Kullanıcıları MERKEZ şemada (`platform_users`); marka
        | şemasındaki `users` tablosuyla hiçbir ilişkisi yok.
        */
        'platform' => [
            'driver' => 'sanctum',
            'provider' => 'platform_users',
        ],

        /*
        | ★ KONTROL DÜZLEMİ SAYFALARI — OTURUM tabanlı. (4F)
        |
        | `platform` ile AYNI kullanıcı tablosu, FARKLI kapı — panelde
        | yaptığımızın (4C-K3) aynısı:
        |
        |   platform      token   → merkez API
        |   platform-web  oturum  → tarayıcıdaki kontrol düzlemi
        |
        | ⚠️ MARKA PANELİNDEN AYRI GUARD: `staff-web` marka şemasındaki
        | `users` tablosuna bakıyor, bu merkez şemadaki `platform_users`'a.
        | Aynı guard'da olsalardı bir markanın sahibi kontrol düzlemine
        | girebilirdi — sistemdeki en tehlikeli yetki (3C).
        */
        'platform-web' => [
            'driver' => 'session',
            'provider' => 'platform_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        /*
        | Provider = "kullanıcıyı nereden bulacağız".
        | İkisi de marka şemasındaki tablolara bakıyor; hangi markanınki
        | olduğu sorusu burada sorulmuyor — `search_path` zaten belirlemiş
        | oluyor (M-2.1).
        */

        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],

        'platform_users' => [
            'driver' => 'eloquent',
            'model' => PlatformUser::class,
        ],

        'staff' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    /*
    | ŞİFRE SIFIRLAMA BROKER'LARI (4.6V).
    |
    | ⚠️ `users` BROKER'I BURADAN SİLİNDİ AMA YİNE DE VAR — ölçüldü.
    | Laravel 11+ ÇERÇEVE VARSAYILAN config'ini uygulamanınkiyle
    | BİRLEŞTİRİYOR (`vendor/laravel/framework/config/auth.php`), yani bu
    | dosyadan çıkarmak onu yok etmiyor:
    |
    |     dosyadan okunan → customers, staff
    |     çalışma anında  → users, customers, staff
    |
    | Kalan `users` broker'ı BOZUK: var olmayan bir `users` provider'ına
    | işaret ediyor. `Password::broker('users')` çağrılırsa çalışma anında
    | patlar. Korunma iki katmanlı: (1) varsayılan broker aşağıda
    | `customers` yapıldı, (2) panel kendi broker'ını AÇIKÇA seçiyor.
    | `SifreSifirlamaTest` bunu ölçüyor.
    |
    | ⚠️ İKİ AYRI TABLO ve bu bir GÜVENLİK kararı. Laravel jetonu yalnızca
    | E-POSTAYA göre saklıyor; müşteri ve personel aynı tabloyu
    | paylaşsaydı aynı e-postaya sahip iki kayıt birbirinin jetonunu
    | ezerdi ve müşteri jetonu personel parolasını değiştirebilirdi.
    | Gerekçenin tamamı migration dosyasında.
    |
    | ⚠️ `platform_users` BİLEREK YOK: onun komut satırı kurtarma yolu
    | zaten var (`CreatePlatformUser`). Müşteri ve personelin hiçbir yolu
    | yoktu — bu bloğun sebebi o.
    */
    'passwords' => [
        'customers' => [
            'provider' => 'customers',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'staff' => [
            'provider' => 'staff',
            'table' => 'staff_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        /*
        | ★ `users` — SİLİNEMEYEN ÇERÇEVE VARSAYILANI, ZARARSIZ HÂLE
        | GETİRİLDİ. Buradaki tanım bir kolaylık değil, KAPATILMIŞ BİR
        | AÇIK.
        |
        | ⚠️ ÖLÇÜLDÜ VE SÖMÜRÜLEBİLİRLİĞİ KANITLANDI. Laravel 11+ çerçeve
        | config'ini birleştirdiği için `users` broker'ı bu dosyadan
        | silinse bile çalışma anında var oluyordu ve ÇAPRAZ BAĞLIYDI:
        |
        |     users broker tablosu  → password_reset_tokens   (MÜŞTERİ)
        |     users provider modeli → App\Models\User         (PERSONEL)
        |
        | Yani vitrinden herkesin alabildiği bir MÜŞTERİ jetonu,
        | `Password::broker('users')` üzerinden PERSONEL parolasını
        | değiştiriyordu. Gerçek bir denemeyle doğrulandı: sonuç
        | `passwords.reset` döndü ve personel parolası ele geçirildi.
        |
        | ⚠️ Bugün hiçbir kod `broker('users')` çağırmıyor — yani açık
        | GİZLİ (latent). Ama silinemediği için tek savunma onu tutarlı
        | kılmak: artık `staff` ile aynı provider ve aynı tabloya
        | bakıyor. Çapraz bağ yok; en kötü ihtimalle personel jetonu
        | personel parolasını sıfırlar, ki doğru davranış budur.
        |
        | ⚠️ İki ayrı tablo kararının (migration'daki gerekçe) çerçeve
        | tarafından SESSİZCE delinebildiğinin örneği. Yeni bir broker
        | eklenirken bu dosyadaki her girdinin provider/table çiftinin
        | AYNI kullanıcı türüne baktığı doğrulanmalı.
        */
        'users' => [
            'provider' => 'staff',
            'table' => 'staff_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
