{{--
    FAVORİ DÜĞMESİ (4.6D)

    ⚠️ ORTAK PARÇA: ürün sayfası (iki düzen) ve favori listesi kullanıyor.
    Kopyalansaydı biri güncellenip öteki unutulurdu — 4.6C'deki yorum
    parçasıyla aynı gerekçe.

    ⚠️ Misafire düğme GÖSTERİLMİYOR, giriş bağlantısı gösteriliyor.
    Gösterilseydi basan misafir giriş ekranına savrulur ve ne yaptığını
    anlamazdı.
--}}
@if ($musteriGirisli)
    <form method="post" action="{{ route('vitrin.urun.favori', ['slug' => $urun->slug]) }}" class="favori-form">
        @csrf

        {{--
            ⚠️ `aria-pressed` ŞART: düğme iki DURUMLU ve durum yalnızca
            renkle anlatılsaydı ekran okuyucu kullanan müşteri ürünün
            favoride olup olmadığını hiç bilemezdi.
        --}}
        <button type="submit"
                class="favori {{ $favorideMi ? 'favori-dolu' : '' }}"
                aria-pressed="{{ $favorideMi ? 'true' : 'false' }}">
            {{ $favorideMi ? '♥ Favorilerimde' : '♡ Favorilere ekle' }}
        </button>
    </form>
@else
    <p class="ipucu">
        <a href="{{ route('vitrin.giris') }}">Giriş yapın</a> ve ürünü favorilerinize ekleyin.
    </p>
@endif
