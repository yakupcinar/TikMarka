{{--
    "vitrinli" düzeni — ürün sayfası. (4G)

    ⚠️ `sade`den farkı: görsel TAM GENİŞLİK ve üstte. Aynı veriler, farklı
    yerleşim.

    ⚠️ SEPET, ÖDEME ve DÖNÜŞ sayfalarının düzen kopyası YOK ve bu bilinçli:
    düzen bir GÖRÜNÜM tercihi, işlevsel akış değil. Kopyalansalardı iki
    dosya arasında bir gün fark oluşur ve müşterinin hangi düzeni seçtiğine
    göre farklı bir ödeme akışı yaşaması mümkün olurdu.
--}}
@extends('storefront.layout')

@section('baslik', $urun->title.' — '.$tema['ad'])
{{--
    ⚠️ `@section('ad', ifade)` KISA BİÇİMİ KULLANILMIYOR.
    Blade argümanları virgülden bölüyor; içinde virgül olan bir fonksiyon
    çağrısı (`Str::limit(..., 150)`) yanlış ayrışıyor ve görünüm derlenemez
    hâle geliyor. Belirtisi sinsi: sayfa çalışıyor gibi görünüyor ama
    Larastan görünümü bulamıyor. Blok biçimi bu sorunu yaşamıyor.
--}}
@section('aciklama')
    {{ \Illuminate\Support\Str::limit(strip_tags((string) $urun->description), 150) }}
@endsection

@section('icerik')

    @include('storefront.partials.kategori-kirintisi')

    <div class="urun urun-genis">
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
                <p class="fiyat-buyuk">
                    {{ number_format((float) $urun->variants->min('price'), 2, ',', '.') }} TL
                </p>

                <form method="post" action="{{ route('vitrin.sepet.ekle') }}" class="ekle-form">
                    {{--
                        ⚠️ CSRF alanı ZORUNLU: bu rota `web` grubunda ve koruma
                        İSTENİYOR (4-K4). 3C'de aynı koruma yanlış yerde olduğu
                        için sorun çıkarmıştı; burası doğru yeri.
                    --}}
                    @csrf

                    {{--
                        ⚠️ ÖNCE BURADA DÜZ AÇILIR LİSTE VARDI — 4.6A'nın
                        kaldırmayı amaçladığı şeyin ta kendisi:
                        "kirmizi · m — 249,90 TL". Seçici yalnızca `sade`
                        düzenine uygulanmıştı; `vitrinli` kullanan marka
                        (geliştirme markası dâhil) eski hâli görüyordu.
                    --}}
                    @include('storefront.partials.varyant-secici')

                    <label>
                        Adet
                        <input type="number" name="quantity" value="1" min="1"
                               max="{{ \App\Domain\Cart\CartService::MAKS_ADET }}">
                    </label>

                    <button class="dugme buyuk" type="submit">Sepete ekle</button>
                </form>
            @endif

            @if ($urun->description)
                <div class="aciklama">{!! nl2br(e($urun->description)) !!}</div>
            @endif
        </div>
    </div>

    @include('storefront.partials.favori-dugmesi')

    @include('storefront.partials.oneriler')

    @include('storefront.partials.yorumlar')

    @include('storefront.partials.varyant-betigi')

@endsection
