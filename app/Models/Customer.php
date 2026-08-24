<?php

namespace App\Models;

use App\Domain\Identity\EmailNormalizer;
use App\Mail\EmailVerificationMail;
use App\Mail\PasswordResetMail;
use Database\Factories\CustomerFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

/**
 * Markanın MÜŞTERİSİ — vitrin tarafından giriş yapan kişi.
 *
 * Personel ayrı model (`User`), ayrı tablo, ayrı guard (1A.0).
 * Bu model marka şemasında yaşar; hangi markanınki olduğu sorusu burada
 * hiç sorulmaz — `search_path` zaten kapsamı belirlemiş oluyor (M-2.1).
 */
class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use HasUuids;

    /*
    | ⚠️ Trait'in `sendEmailVerificationNotification()`'ı AŞAĞIDA eziliyor;
    | geri kalanı (hasVerifiedEmail / markEmailAsVerified) olduğu gibi
    | kullanılıyor.
    */
    use MustVerifyEmailTrait;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'accepts_marketing',
    ];

    /**
     * API cevabında ve log'da görünmeyecek alanlar.
     * Bu liste olmasaydı parola hash'i her `/api/me` cevabında dışarı sızardı.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            // Yazarken otomatik hash'lenir. Böylece parolayı hash'lemeyi
            // unutmak imkânsız hale geliyor.
            'password' => 'hashed',

            // Metin yerine tarih nesnesi döner → ->addDays(7) yazılabilir.
            'email_verified_at' => 'datetime',

            'accepts_marketing' => 'boolean',
        ];
    }

    /**
     * HasUuids normalde birincil anahtarı uuid yapar. Biz `id`'yi otomatik
     * artan bırakıp UUID'yi AYRI bir kolonda tutuyoruz: id içeride hızlı ve
     * küçük, uuid dışarıya açılan tahmin edilemez kimlik (domain-model §0).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * E-postayı SINIRDA küçültür.
     *
     * Veritabanında `CHECK (email = lower(email))` var; bu mutator olmasaydı
     * kullanıcı "Ali@Site.com" yazdığında kayıt reddedilirdi. Mutator sessizce
     * düzeltiyor, CHECK ise modelden geçmeyen yolları (ham SQL, Query Builder)
     * kapatıyor. İkisi farklı kapıları tutuyor.
     *
     * ⚠️ citext kullanılamadığı için bu desen zorunlu — eklenti public
     * şemasında kalıyor ve marka bağlantısı onu göremiyor (1A.1 bulgusu).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => EmailNormalizer::normallestir($value),
        );
    }

    /**
     * Müşterinin adres DEFTERİ.
     *
     * ⚠️ Sipariş adresi bu ilişkiden okunmaz — siparişe kopyalanır.
     * Müşteri adresini değiştirirse geçmiş siparişler değişmemeli
     * (docs/domain-model.md §7).
     *
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Oturum çerezine dayalı "beni hatırla" kullanılmıyor — kimlik doğrulama
     * token tabanlı olacak (K-12). Tabloda `remember_token` kolonu da yok.
     */
    protected $rememberTokenName = null;

    /**
     * Şifre sıfırlama postası — MARKA ADIYLA. (4.6V)
     *
     * ⚠️ Laravel'in hazır `ResetPassword` bildirimi EZİLİYOR. Varsayılan
     * bildirim platform adıyla ve çerçevenin İngilizce iskeletiyle
     * gidiyor; müşteri onu tanımaz. Bu projede tüm postalar markanın
     * kimliğiyle çıkıyor (2H-K3) ve kuyruğa giriyor (2H-K1).
     *
     * ⚠️ Adres BURADA kuruluyor çünkü rota adı yüzeye göre değişiyor:
     * müşteri vitrindeki sayfaya, personel panele gitmeli. Tek adres
     * yazılsaydı personel müşteri ekranına düşerdi (4C'de aynı hata
     * `redirectGuestsTo` için yapılmıştı).
     */
    /**
     * Doğrulama bağlantısını MARKA kimliğiyle gönderir. (4.6W)
     *
     * Laravel'in hazır `VerifyEmail` bildirimi ezildi — 2H-K3'teki
     * gerekçenin aynısı: müşteri "TıkMarka"dan değil alışveriş yaptığı
     * markadan posta almalı.
     *
     * ⚠️ Adres İMZALI ve SÜRELİ. İmzasız olsaydı uuid'i bilen (ya da
     * deneyen) biri başkasının adresini "doğrulanmış" işaretleyebilirdi;
     * o zaman doğrulama hiçbir şey kanıtlamazdı.
     *
     * ⚠️ İmzaya e-postanın HASH'i giriyor: müşteri adresini değiştirirse
     * eski postadaki bağlantı ÖLÜR. Yoksa "adresi değiştir, eski
     * bağlantıya tıkla" ile doğrulanmamış bir adres doğrulanmış olurdu.
     */
    public function sendEmailVerificationNotification(): void
    {
        $dakika = (int) config('auth.verification.expire', 60);

        /*
        | ⚠️ İmza MUTLAK adres üzerinden kuruluyor (alan adı dâhil).
        | APP_KEY bütün markalarda AYNI olduğu için alan adı imzanın
        | dışında kalsaydı A markasında üretilen bağlantı B markasında da
        | geçerli olurdu. Ölçülüyor: `EpostaDogrulamaTest`.
        */
        $adres = URL::temporarySignedRoute(
            'vitrin.eposta.dogrula',
            now()->addMinutes($dakika),
            [
                'musteri' => $this->uuid,
                'hash' => sha1((string) $this->getEmailForVerification()),
            ],
        );

        Mail::to($this->getEmailForVerification())
            ->queue(new EmailVerificationMail($adres, $dakika));
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $adres = route('vitrin.sifre.sifirla', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);

        $dakika = (int) config('auth.passwords.customers.expire', 60);

        Mail::to($this->getEmailForPasswordReset())
            ->queue(new PasswordResetMail($adres, $dakika, panel: false));
    }
}
