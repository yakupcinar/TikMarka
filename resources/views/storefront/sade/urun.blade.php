@extends('storefront.layout')

@section('baslik', $urun->title.' — '.$tema['ad'])
{{--
    ⚠️ `@section('ad', ifade)` KISA BİÇİMİ KULLANILMIYOR.
    Blade argümanları virgülden bölüyor; içinde virgül olan bir fonksiyon
    çağrısı (`Str::limit(..., 150)`) yanlış ayrışıyor ve görünüm derlenemez
    hâle geliyor. Belirtisi sinsi: sayfa çalışıyor gibi görünüyor ama
    Larastan görünümü bulamıyor. Blok biçimi bu sorunu yaşamıyor.
--}}
@section('og_tur', 'product')

@section('og_gorsel', $urun->images->first()?->url() ?? '')

@push('yapisal_veri')
    @include('storefront.partials.urun-yapisal-veri')
@endpush

@section('aciklama')
    {{ \Illuminate\Support\Str::limit(strip_tags((string) $urun->description), 150) }}
@endsection

@section('icerik')

    @include('storefront.partials.kategori-kirintisi')

    <div class="urun">
        <div>
            @if ($urun->images->first())
                <img class="urun-gorsel" src="{{ $urun->images->first()->url() }}" alt="{{ $urun->title }}">
            @else
                <div class="urun-gorsel bos-gorsel">Görsel yok</div>
            @endif
        </div>

        <div>
            <h1>{{ $urun->title }}</h1>

            @if ($urun->brand)
                <p class="marka-adi">{{ $urun->brand }}</p>
            @endif

            @if ($urun->variants->isEmpty())
                {{-- ⚠️ Varyantsız ürün satılamaz; "sepete ekle" gösterilmiyor. --}}
                <p class="bos">Bu ürün şu anda satışta değil.</p>
            @else
                {{--
                    ⚠️ Fiyat SEÇİME GÖRE değişiyor (4.6A). Sabit en düşük fiyat
                    yazılsaydı müşteri 100 TL görüp 120 TL'lik varyantı sepete
                    atardı — ve bunu ancak sepet sayfasında fark ederdi.
                --}}
                <p class="fiyat-buyuk" data-fiyat>
                    {{ number_format((float) $urun->variants->min('price'), 2, ',', '.') }} TL
                </p>

                <form method="post" action="{{ route('vitrin.sepet.ekle') }}" class="ekle-form">
                    {{--
                        ⚠️ CSRF alanı ZORUNLU: bu rota `web` grubunda ve koruma
                        İSTENİYOR (4-K4). 3C'de aynı koruma yanlış yerde olduğu
                        için sorun çıkarmıştı; burası doğru yeri.
                    --}}
                    @csrf

                    @include('storefront.partials.varyant-secici')

                    <label>
                        Adet
                        <input type="number" name="quantity" value="1" min="1"
                               max="{{ \App\Domain\Cart\CartService::MAKS_ADET }}">
                    </label>

                    {{-- ⚠️ Seçim tamamlanana kadar KAPALI: eksik seçimle
                         gönderilen form sunucudan "varyant zorunludur"
                         alırdı ve müşteri neyi eksik bıraktığını
                         göremezdi. --}}
                    <button class="dugme buyuk" type="submit" data-ekle-dugme>Sepete ekle</button>
                </form>
            @endif

            @if ($urun->description)
                <div class="aciklama">{!! nl2br(e($urun->description)) !!}</div>
            @endif
        </div>
    </div>


    @include('storefront.partials.varyant-betigi')

    @include('storefront.partials.favori-dugmesi')

    @include('storefront.partials.oneriler')

    @include('storefront.partials.yorumlar')

@endsection
