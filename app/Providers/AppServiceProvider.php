<?php

namespace App\Providers;

use App\Domain\Identity\EmailNormalizer;
use App\Domain\Quota\QuotaGuard;
use App\Http\Storefront\StorefrontViewData;
use App\Platform\Domains\DnsChecker;
use App\Platform\Domains\SystemDnsChecker;
use App\Platform\PlanQuotaGuard;
use App\Platform\Subscription\SubscriptionProvider;
use App\Platform\Subscription\SubscriptionProviderFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        /*
        | ★ DNS OKUYUCU (3H) — gerçek sorgu ağa çıkıyor ve testte
        | çalıştırılamaz, bu yüzden arayüz üzerinden.
        */
        $this->app->bind(
            DnsChecker::class,
            SystemDnsChecker::class,
        );

        /*
        | ★ KOTA KAPISI (3F) — arayüz Domain'de, uygulama Platform'da.
        |
        | ⚠️ Bağımlılık BİLEREK ters çevrildi: kota markanın planına bakıyor
        | ve plan MERKEZ kayıtta, ama `app/Domain/` kiracıdan habersiz olmak
        | zorunda (M-2.7, ölçülüyor). İş mantığı "kotam var mı" diye
        | soruyor, planın nereden geldiğini bilmiyor.
        */
        $this->app->bind(
            QuotaGuard::class,
            PlanQuotaGuard::class,
        );

        /*
        | ★ ABONELİK SAĞLAYICISI (3E) — MERKEZ yapılandırmadan.
        |
        | ⚠️ 1E'deki ödeme sağlayıcısı marka `settings`'inden geliyor ve her
        | markada AYRI. Bu ise TEK: TıkMarka'nın kendi tahsilat hesabı.
        | İkisi karışırsa markanın parası bize, bizim paramız markaya gider.
        */
        $this->app->bind(
            SubscriptionProvider::class,
            fn () => (new SubscriptionProviderFactory)->yap(),
        );

        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        | ★ VİTRİN ORTAK VERİSİ (4A) — tema ve sepet sayısı.
        |
        | ⚠️ Controller'da tekrarlanamaz: mağaza kapalı sayfasını MIDDLEWARE
        | döndürüyor ([RequirePublishedStore]) ve onun controller'ı yok.
        | Gerekçenin tamamı [StorefrontViewData]'da.
        */
        View::composer('storefront.*', StorefrontViewData::class);

        /*
        | MERKEZ migration klasörünü kaydediyoruz.
        |
        | database/migrations kökü bilerek boş (bkz. PLAN.md 0.5/2) — bu
        | yüzden Laravel varsayılan olarak hiçbir tarif bulamıyor. Burada
        | landlord/ klasörünü tanıtınca hem `php artisan migrate` hem de
        | testlerdeki `migrate:fresh` onu görüyor.
        |
        | Marka tarifleri (tenant/) BİLEREK kaydedilmiyor: onlar merkez
        | veritabanına değil, her markanın kendi şemasına uygulanacak
        | (`php artisan tenants:migrate`).
        */
        $this->loadMigrationsFrom(database_path('migrations/landlord'));

        /*
        | HIZ SINIRLAYICILAR — kaba kuvvet saldırısının en ucuz önlemi.
        |
        | M-4.1/3: Caddy'nin hız sınırlaması olgun değil, bu yüzden koruma
        | bilerek UYGULAMA katmanında. Yani bu satırlar "iyi olur" değil,
        | kapatılmış bir açığın tek sahibi.
        */

        // Giriş: e-posta + IP birlikte sayılıyor.
        // Sadece IP olsaydı ortak ağdaki (okul, ofis) kullanıcılar birbirini
        // kilitlerdi. Sadece e-posta olsaydı saldırgan farklı adreslerle
        // sınırsız deneme yapardı.
        RateLimiter::for('giris', fn (Request $istek) => Limit::perMinute(5)
            ->by(EmailNormalizer::normallestir((string) $istek->input('email')).'|'.$istek->ip()));

        // Kayıt: IP başına saatlik. Sahte hesap üretimini yavaşlatır.
        RateLimiter::for('kayit', fn (Request $istek) => Limit::perHour(10)->by($istek->ip()));

        /*
        | ★ 4.6T — daha önce hiç sınırlanmamış üç uç. Ölçüldü: kupon,
        | yorum ve iade uçlarında throttle YOKTU; giriş/kayıttan sonra
        | eklenen özellikler bu deseni miras almamıştı.
        */

        // Kupon: MİSAFİRE de açık uç, kimlik garantisi yok — IP tek
        // güvenilir anahtar. Kod tahmin etmeye çalışan bir betiği
        // pratikte kullanılamaz hâle getirmesi yeterli; gerçek müşterinin
        // birkaç kodu art arda denemesini engellemeyecek kadar geniş.
        RateLimiter::for('kupon', fn (Request $istek) => Limit::perMinute(10)->by($istek->ip()));

        // Yorum: yalnızca SATIN ALAN müşteri yazabiliyor (NotPurchasedException,
        // 2E) — yani kimlik zaten garanti. Müşteri anahtarı, misafir olamayan
        // bu uçta IP'den daha doğru: aynı NAT arkasındaki başka müşteriyi
        // etkilemiyor.
        RateLimiter::for('yorum', fn (Request $istek) => Limit::perHour(5)
            ->by($istek->user()?->getAuthIdentifier() ?? $istek->ip()));

        /*
        | Şifre sıfırlama (4.6V): her istek BİR E-POSTA gönderiyor.
        |
        | ⚠️ Sınırsız bırakılsaydı iki ayrı zarar: (1) saldırgan kurbanın
        | gelen kutusunu doldurur (mail bombing), (2) SMTP sağlayıcısının
        | günlük gönderim kotası yanar ve GERÇEK postalar da gitmez.
        |
        | ⚠️ `giris` gibi e-posta + IP birlikte: yalnız IP olsaydı ortak
        | ağdaki kullanıcılar birbirini kilitlerdi, yalnız e-posta olsaydı
        | saldırgan farklı adreslerle sınırsız posta tetiklerdi.
        |
        | ⚠️ Laravel'in broker'ında ayrıca `throttle => 60` var ama o
        | YALNIZCA aynı e-postaya art arda jeton üretmeyi engelliyor;
        | farklı e-postalarla yapılan toplu denemeyi görmüyor.
        */
        RateLimiter::for('sifre-sifirlama', fn (Request $istek) => Limit::perHour(5)
            ->by(EmailNormalizer::normallestir((string) $istek->input('email')).'|'.$istek->ip()));

        // İade: aynı gerekçe — müşteri kimliği zaten zorunlu (1A.5:
        // sipariş sahiplik üzerinden çözülüyor). Bir siparişte birden çok
        // satır için ayrı talep açılabildiğinden sınır kuponunkinden gevşek.
        RateLimiter::for('iade', fn (Request $istek) => Limit::perHour(10)
            ->by($istek->user()?->getAuthIdentifier() ?? $istek->ip()));
    }
}
