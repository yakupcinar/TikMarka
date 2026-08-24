<?php

namespace App\Http\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Müşteri e-posta doğrulama — vitrin. (4.6W)
 *
 * ★ YUMUŞAK KAPI. Doğrulanmamış müşterinin ödemesi ENGELLENMİYOR ve bu
 * bilinçli: `/odeme` kimlik istemiyor (misafir ödemesi açık). Sert kapı
 * konsaydı hesap açmayan rahat alışveriş yapar, hesap açan yapamazdı —
 * ve saldırgan zaten çıkış yapıp misafir olarak alırdı. Yani sert kapı
 * satışı kırar, kimseyi durdurmaz.
 *
 * ⚠️ DOĞRULAMA UCU GİRİŞ İSTEMİYOR. Müşteri bağlantıya çoğu zaman
 * telefonundan, oturum açmadığı bir tarayıcıdan tıklıyor. `auth`
 * konsaydı bağlantı o kişilerde çalışmaz, "doğrulayamıyorum" desteğine
 * dönerdi. Güvenliği oturum değil İMZA sağlıyor (`signed` middleware) —
 * imza müşteri uuid'sini ve e-postanın hash'ini kapsıyor.
 */
class EmailVerificationPageController extends Controller
{
    public function dogrula(Request $istek, string $musteri, string $hash): RedirectResponse
    {
        $kayit = Customer::where('uuid', $musteri)->first();

        /*
        | ⚠️ `firstOrFail()` DEĞİL: okuma yolunda veri sorununu 404'e
        | çevirmek gerçek sebebi gizler (CLAUDE.md). Burada müşteri
        | silinmişse ya da uuid uydurulmuşsa kullanıcıya anlamlı bir
        | cevap dönüyor.
        |
        | ⚠️ Hash karşılaştırması `hash_equals` ile — imza zaten geçmiş
        | olsa da alışkanlığı bozmuyoruz.
        */
        if ($kayit === null || ! hash_equals(sha1((string) $kayit->getEmailForVerification()), $hash)) {
            return redirect()->route('vitrin.giris')
                ->with('hata', 'Doğrulama bağlantısı geçersiz. Hesabınıza girip yeni bir bağlantı isteyebilirsiniz.');
        }

        /*
        | ⚠️ İKİNCİ TIKLAMA HATA DEĞİL. Postadaki bağlantı birden çok kez
        | açılabilir (istemci ön-yüklemesi, kullanıcının geri tuşu).
        | "Zaten doğrulanmış" bir hata gibi gösterilseydi müşteri bir
        | sorun olduğunu sanırdı.
        */
        if (! $kayit->hasVerifiedEmail()) {
            $kayit->markEmailAsVerified();
        }

        return redirect()->route('vitrin.giris')
            ->with('mesaj', 'E-posta adresiniz doğrulandı.');
    }

    /**
     * Doğrulama postasını yeniden gönderir.
     *
     * ⚠️ Adres İSTEKTEN DEĞİL OTURUMDAN geliyor. İstekten alınsaydı bu
     * uç herkese açık bir posta gönderme aracı olurdu.
     */
    public function yenidenGonder(Request $istek): RedirectResponse
    {
        $musteri = $istek->user('customer-web');

        /*
        | ⚠️ GUARD ADI AÇIKÇA YAZILIYOR. Varsayılan guard `customer`
        | (sanctum/token); sayfa katmanında kimlik OTURUMDA. Adı
        | yazmamak 4.5I'de ısırdı — giriş yapmış müşteri misafir
        | sayılıyor ve siparişler sahipsiz doğuyordu.
        */
        if (! $musteri instanceof Customer) {
            return back()->with('hata', 'Bu işlem için giriş yapmalısınız.');
        }

        if ($musteri->hasVerifiedEmail()) {
            return back()->with('mesaj', 'E-posta adresiniz zaten doğrulanmış.');
        }

        $musteri->sendEmailVerificationNotification();

        return back()->with('mesaj', 'Doğrulama bağlantısı gönderildi. Gelen kutunuzu kontrol edin.');
    }
}
