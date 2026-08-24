<?php

namespace App\Http\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as ParolaKurali;

/**
 * Müşteri şifre sıfırlama — vitrin. (4.6V)
 *
 * ★ ÖNCESİNDE HİÇBİR YOL YOKTU: şifresini unutan müşteri hesabına bir
 * daha giremiyordu; tek çözüm geliştiricinin elle bcrypt hash yazmasıydı.
 *
 * ⚠️ BROKER AÇIKÇA SEÇİLİYOR (`customers`). Varsayılana güvenmek 4.5I'de
 * ısırdı: orada `$istek->user()` varsayılan guard'ı (sanctum) soruyordu
 * ve giriş yapmış müşteri MİSAFİR sayılıyordu. Ayrıca Laravel çerçevenin
 * varsayılan config'ini birleştirdiği için BOZUK bir `users` broker'ı
 * çalışma anında hâlâ mevcut — adı yazmamak onu seçme riskidir.
 */
class PasswordResetPageController extends Controller
{
    public function istekFormu(): View
    {
        return view('storefront.sifre-unuttum');
    }

    /**
     * Sıfırlama bağlantısı gönderir.
     *
     * ⚠️ CEVAP HER ZAMAN AYNI — hesap olsa da olmasa da. Laravel'in
     * hazır davranışı "bu e-posta kayıtlı değil" diyor; o cevap
     * saldırgana ÜYE LİSTESİ çıkarma imkânı verirdi (enumeration).
     * Vitrinde bu özellikle önemli: müşteri listesi ticari bir varlık.
     */
    public function istekGonder(Request $istek): RedirectResponse
    {
        $veri = $istek->validate(['email' => ['required', 'email', 'max:190']]);

        Password::broker('customers')->sendResetLink(['email' => $veri['email']]);

        return back()->with('mesaj', 'Eğer bu adrese kayıtlı bir hesap varsa şifre sıfırlama bağlantısı gönderildi. Gelen kutunuzu kontrol edin.');
    }

    public function sifirlamaFormu(Request $istek, string $token): View
    {
        return view('storefront.sifre-sifirla', [
            'token' => $token,
            'email' => (string) $istek->query('email', ''),
        ]);
    }

    public function sifirla(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],

            /*
            | ⚠️ `confirmed` ŞART: kullanıcı yeni şifresini iki kez yazıyor.
            | Tek kutu olsaydı yazım hatası yapan kişi kendini hesabından
            | KİLİTLERDİ — ve sıfırlama akışının varlık sebebi tam olarak
            | o durumdan kurtarmak.
            |
            | ⚠️ `min(8)` kayıt formundakiyle AYNI (RegisterRequest).
            | Sıfırlama daha gevşek olsaydı zayıf şifreye giden bir arka
            | kapı açılırdı.
            */
            'password' => ['required', 'confirmed', ParolaKurali::min(8)],
        ]);

        $sonuc = Password::broker('customers')->reset($veri, function ($musteri, string $sifre): void {
            /*
            | ⚠️ Düz metin atanıyor; `password` alanı modelde `hashed`
            | cast'li, yani bcrypt otomatik uygulanıyor. Elle
            | `Hash::make()` yazılsaydı ÇİFT hash'lenir ve kullanıcı yeni
            | şifresiyle giriş yapamazdı.
            */
            $musteri->password = $sifre;
            $musteri->save();
        });

        if ($sonuc !== Password::PASSWORD_RESET) {
            /*
            | ⚠️ Süresi dolmuş/kullanılmış jeton ile yanlış e-posta AYNI
            | mesajı alıyor: hangisinin yanlış olduğunu söylemek yine
            | hesap varlığını sızdırırdı.
            */
            return back()->withInput($istek->only('email'))
                ->with('hata', 'Bağlantı geçersiz ya da süresi dolmuş. Lütfen yeni bir sıfırlama bağlantısı isteyin.');
        }

        return redirect()->route('vitrin.giris')
            ->with('mesaj', 'Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.');
    }
}
