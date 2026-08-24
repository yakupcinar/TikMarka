<?php

namespace App\Http\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as ParolaKurali;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personel şifre sıfırlama — marka paneli. (4.6V)
 *
 * ⚠️ VİTRİNDEKİNDEN AYRI BROKER (`staff`) ve AYRI TABLO
 * (`staff_password_reset_tokens`). Tek tablo paylaşılsaydı aynı
 * e-postaya sahip müşteri ve personel birbirinin jetonunu ezerdi ve
 * vitrinden açılan bir müşteri hesabı, panel personelinin parolasını
 * ele geçirmenin yolu olurdu. Gerekçenin tamamı migration dosyasında.
 *
 * ⚠️ Bu sayfalar Inertia — panelin geri kalanıyla aynı (4-K1). Vitrin
 * tarafı Blade; iki yüzey aynı işi yapıyor ama farklı teknolojiyle,
 * çünkü kullanıcıları farklı.
 */
class PanelPasswordResetController extends Controller
{
    public function istekFormu(): Response
    {
        return Inertia::render('SifreUnuttum');
    }

    /**
     * ⚠️ Cevap her zaman aynı — personel listesi de sızdırılmamalı.
     * Panel giriş ekranı herkese açık: bir saldırgan hangi e-postaların
     * o markada çalıştığını buradan öğrenebilirdi.
     */
    public function istekGonder(Request $istek): RedirectResponse
    {
        $veri = $istek->validate(['email' => ['required', 'email', 'max:190']]);

        Password::broker('staff')->sendResetLink(['email' => $veri['email']]);

        return back()->with('mesaj', 'Eğer bu adrese kayıtlı bir personel hesabı varsa şifre sıfırlama bağlantısı gönderildi.');
    }

    public function sifirlamaFormu(Request $istek, string $token): Response
    {
        return Inertia::render('SifreSifirla', [
            'token' => $token,
            'email' => (string) $istek->query('email', ''),
        ]);
    }

    public function sifirla(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', ParolaKurali::min(8)],
        ]);

        $sonuc = Password::broker('staff')->reset($veri, function ($personel, string $sifre): void {
            // ⚠️ `hashed` cast bcrypt'liyor; elle Hash::make ÇİFT hash olurdu.
            $personel->password = $sifre;
            $personel->save();
        });

        if ($sonuc !== Password::PASSWORD_RESET) {
            return back()->withInput($istek->only('email'))
                ->with('hata', 'Bağlantı geçersiz ya da süresi dolmuş. Lütfen yeni bir sıfırlama bağlantısı isteyin.');
        }

        return redirect()->route('panel.giris')
            ->with('mesaj', 'Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.');
    }
}
