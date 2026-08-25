{{--
    ÜRÜN SAYFASINDA KATEGORİ YOLU (4.6B)

    ⚠️ Ürünün kategorisi olmayabilir; o durumda hiçbir şey çizilmiyor.
    Koşulsuz çizilseydi kategorisiz üründe boş bir "Kategoriler /" satırı
    kalırdı.
--}}
@if ($kategoriZinciri->isNotEmpty())
    <nav class="kirinti" aria-label="Kategori yolu">
        <a href="{{ route('vitrin.kategoriler') }}">Kategoriler</a>

        @foreach ($kategoriZinciri as $halka)
            <span aria-hidden="true">/</span>
            <a href="{{ route('vitrin.kategori', ['slug' => $halka->slug]) }}">{{ $halka->name }}</a>
        @endforeach
    </nav>
@endif
