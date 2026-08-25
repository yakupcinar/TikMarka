@extends('storefront.layout')

@section('baslik', 'Kategoriler — '.$tema['ad'])

@section('icerik')

    <h1>Kategoriler</h1>

    {{--
        ⚠️ Boş liste bir HATA DEĞİL: markanın henüz kategorisi olmayabilir
        ya da kategorilerinde ürün olmayabilir.
    --}}
    @if ($kategoriler->isEmpty())
        <p class="bos">Henüz kategori yok.</p>
    @else
        <ul class="kategori-agaci">
            @foreach ($kategoriler as $kategori)
                {{--
                    ⚠️ Girinti `level` ile veriliyor — ağaç yapısı `path`
                    sırasından geliyor (`orderBy('path')`), yani liste zaten
                    kökten yaprağa sıralı. Ağacı iç içe `<ul>`'lerle kurmak
                    özyineleme ve ek sorgu isterdi.
                --}}
                <li style="padding-right:0;margin-right:{{ min((int) $kategori->level, 5) * 18 }}px">
                    <a href="{{ route('vitrin.kategori', ['slug' => $kategori->slug]) }}">{{ $kategori->name }}</a>
                </li>
            @endforeach
        </ul>
    @endif

@endsection
