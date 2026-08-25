@extends('storefront.layout')

@section('baslik', $koleksiyon->title.' — '.$tema['ad'])

@section('aciklama')
    {{ \Illuminate\Support\Str::limit(strip_tags((string) $koleksiyon->description), 150) ?: $koleksiyon->title }}
@endsection

@section('icerik')

    <h1>{{ $koleksiyon->title }}</h1>

    @if ($koleksiyon->description)
        <p class="ipucu">{{ $koleksiyon->description }}</p>
    @endif

    @if ($urunler->isEmpty())
        {{--
            ⚠️ KURALLI koleksiyonda bu durum NORMAL olabilir: kurala uyan
            ürün kalmamıştır. Hata gibi göstermek marka ve müşteriyi
            yanıltırdı.
        --}}
        <p class="bos">Bu koleksiyonda şu anda ürün yok.</p>
    @else
        @include('storefront.partials.urun-izgarasi')
    @endif

@endsection
