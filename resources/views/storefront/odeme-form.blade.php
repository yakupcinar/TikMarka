{{--
    Gömülü ödeme adımı — kart formu IFRAME içinde. (4.5-K1)

    ⚠️ Müşteri SİTEDEN AYRILMIYOR ama kart verisi bize HİÇ UĞRAMIYOR:
    iframe'in içeriği tamamen sağlayıcının kökeninde. Bizim sayfamız
    yalnızca çerçeveyi çiziyor.

    ⚠️ Sağlayıcının hazır BETİĞİ (`checkoutFormContent`) kullanılmıyor;
    o betik sağlayıcının JavaScript'ini BİZİM kökenimizde çalıştırırdı.
    Gerekçenin tamamı [PaymentInitiation::gomulebilirMi]'de.
--}}
@extends('storefront.layout')

@section('baslik', 'Ödeme — '.$tema['ad'])

@section('icerik')

    <div class="odeme-gomulu">
        <h1>Ödeme</h1>
        <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>

        {{--
            ⚠️ `sandbox` KOYULMUYOR. Ödeme formunun 3D Secure için banka
            sayfasına gitmesi, form göndermesi ve betik çalıştırması
            gerekiyor; kısıtlı bir sandbox bunları engelleyip ödemeyi
            sessizce bozardı.

            ⚠️ `title` erişilebilirlik için: ekran okuyucu çerçevenin ne
            olduğunu söyleyebilmeli.
        --}}
        <iframe
            src="{{ $gomuluAdres }}"
            title="Ödeme formu"
            class="odeme-cercevesi"
            allow="payment"
        ></iframe>

        {{--
            ⚠️ Müşteriye çerçevenin KİME ait olduğu yazılıyor. Yazılmasaydı
            kart bilgisini kime verdiğini bilemez — ve bilmediği bir forma
            kart girmemesi doğru davranış.
        --}}
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
