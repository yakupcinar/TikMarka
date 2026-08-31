@extends('storefront.layout')

@section('baslik', 'Ödeme — '.$tema['ad'])

@section('icerik')

    <div class="odeme-gomulu">
        <h1>Ödeme</h1>
        <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>

        <iframe
            src="{{ $gomuluAdres }}"
            title="Ödeme formu"
            class="odeme-cercevesi"
            allow="payment"
        ></iframe>

        <p class="ipucu">
            Ödeme formu bankanız ve ödeme kuruluşu tarafından sağlanır.
            Kart bilgileriniz {{ $tema['ad'] }} sunucularına gönderilmez.
        </p>

        {{--
            ÖDEMEDEN VAZGEÇME (4.6Z)

            ⚠️ Öncesinde bu ekrandan çıkmanın TEMİZ bir yolu yoktu: müşteri
            üst menüden başka sayfaya geçiyor, sipariş `pending` kalıyor ve
            stok 60 dakika kimseye satılamıyordu. "Hesabım"daki iptal
            düğmesi vardı (4.5J) ama MİSAFİRİN oraya erişimi yok.

            ⚠️ İframe'in ALTINDA ve sade: kart formunun yanında dikkat çeken
            bir düğme, ödemeye devam etmek isteyen müşteriyi tereddüde
            düşürürdü.

            ⚠️ `target` YOK — bu sayfa iframe'i İÇEREN sayfa, kendisi
            çerçeve içinde değil. Dönüş ekranı (4.6Y) bunun tersi.
        --}}
        <form method="post" action="{{ route('vitrin.ode.iptal', ['siparis' => $siparis->uuid]) }}" class="odeme-vazgec">
            @csrf
            <button type="submit" class="sil">Ödemeden vazgeç ve sepete dön</button>
        </form>
    </div>

@endsection
