@extends('storefront.layout')

@section('baslik', 'Yeni şifre — '.$tema['ad'])

@section('icerik')
    <div class="hesap-dar">
        <h1>Yeni şifre belirleyin</h1>

        {{--
            ⚠️ ADRES POST ROTASININ ADIYLA. Önce `vitrin.sifre.sifirla`
            yazılıydı ve o GET rotası (`/sifre-sifirla/{token}`); tarayıcı
            oraya POST edince 405 aldı. Gerçek kullanımda yakalandı —
            testler doğrudan doğru adrese POST ettiği için görmemişti.
        --}}
        <form method="post" action="{{ route('vitrin.sifre.guncelle') }}">
            @csrf

            {{--
                ⚠️ Jeton ve e-posta GİZLİ alanda: broker ikisini de POST
                gövdesinden okuyor. Yalnızca adreste kalsalardı sıfırlama
                her seferinde "bağlantı geçersiz" derdi.
            --}}
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            {{--
                ⚠️ E-posta FORM ALANI DEĞİL, düz metin. Önce `readonly`
                bir kutuydu ve doldurulamayan bir alan gibi görünüyordu.
                Burada tek işi "hangi hesabın şifresi değişiyor"u
                göstermek; jeton zaten BU adrese üretildi, değiştirilirse
                eşleşmez.
            --}}
            @if ($email !== '')
                <p class="ipucu">Hesap: <strong>{{ $email }}</strong></p>
            @endif

            <label>Yeni şifre <span class="ipucu">(en az 8 karakter)</span>
                <input type="password" name="password" required autofocus autocomplete="new-password">
            </label>

            <label>Yeni şifre (tekrar)
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <button class="dugme buyuk" type="submit">Şifreyi güncelle</button>
        </form>
    </div>
@endsection
