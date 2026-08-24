@extends('storefront.layout')

@section('baslik', 'Şifremi unuttum — '.$tema['ad'])

@section('icerik')
    <div class="hesap-dar">
        <h1>Şifremi unuttum</h1>

        <p class="ipucu">
            Hesabınızın e-posta adresini girin; şifre sıfırlama bağlantısını gönderelim.
        </p>

        {{-- ⚠️ POST rotasının ADIYLA — GET rotasıyla aynı adresi paylaşsa
             bile isim yazmak niyeti açık tutuyor (bkz. sifre-sifirla). --}}
        <form method="post" action="{{ route('vitrin.sifre.unuttum.gonder') }}">
            @csrf

            <label>E-posta
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>

            <button class="dugme buyuk" type="submit">Sıfırlama bağlantısı gönder</button>
        </form>

        <p class="ipucu">
            <a href="{{ route('vitrin.giris') }}">Giriş ekranına dön</a>
        </p>
    </div>
@endsection
