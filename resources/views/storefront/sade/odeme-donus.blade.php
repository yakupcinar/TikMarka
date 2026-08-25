@extends('storefront.layout')

@section('baslik', 'Sipariş '.$siparis->order_number.' — '.$tema['ad'])

@section('icerik')

    {{--
        ★ IFRAME'DEN ÇIKIŞ (4.5-K1) — bu bloğun olmaması sessiz bir
        bozukluk üretirdi.

        Ödeme formu iframe içinde açılıyor ve sağlayıcı işlem bitince
        DÖNÜŞ ADRESİNİ O ÇERÇEVENİN İÇİNDE açıyor. Bu betik olmasaydı
        müşteri, "Siparişiniz alındı" ekranını ödeme formunun yerinde,
        küçük bir çerçevenin içinde görürdü — üst bar ve menü hâlâ ödeme
        sayfasına ait olurdu.

        ⚠️ `window.top` yerine `window.parent` yazılsaydı iç içe iki
        çerçevede yalnızca bir seviye çıkılırdı.

        ⚠️ Betik ÇALIŞMAZSA (JavaScript kapalı) sayfa yine de doğru ve
        okunabilir: çerçevenin içinde görünür ama içeriği tamdır. Aşağıdaki
        bağlantı da `target="_top"` ile çıkışı elle mümkün kılıyor.

        ★ BU BETİK 4.5R'DEN ÖNCE MÜŞTERİYİ 404'E GÖTÜRÜYORDU.
        Dönüş ucu POST alıyor ve referans GÖVDEDE geliyor; `window.top`
        aynı adrese **GET** ile gidince gövde kayboluyor ve sayfa "sipariş
        bulunamadı" diyordu. Artık bu sayfanın kendi adresi imzalı bir
        GET adresi — üst pencere oraya sorunsuz gidebiliyor.
    --}}
    <script>
        if (window.top !== window.self) {
            window.top.location.href = window.location.href
        }
    </script>


    <div class="sonuc">
        @if ($durum === 'success')
            <h1>Siparişiniz alındı</h1>
            <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>
            <p>Ödemeniz onaylandı. Kargoya verildiğinde e-posta ile haber vereceğiz.</p>

        @elseif ($durum === 'failed')
            <h1>Ödeme tamamlanamadı</h1>
            <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>

            {{--
                ⚠️ SEBEP YAZILMIYOR. Bankanın ret gerekçesi (limit, bakiye,
                fraud) müşterinin kartına dair bilgidir; onu bizim ekranımızda
                göstermek hem yanlış olabilir hem de gereksizce ifşa eder.
            --}}
            <p>Ödemeniz alınamadı. Bankanızla görüşüp yeniden deneyebilirsiniz.</p>

        @else
            {{--
                ★ EN ÖNEMLİ DAL: `processing` = "bildirim HENÜZ GELMEDİ",
                "başarısız" DEĞİL.

                ⚠️ Sağlayıcı ilk bildirimi 10-15 saniye sonra atıyor; müşteri
                bu ekrana 3 saniyede varabiliyor. Ara durum "başarısız"
                gösterilseydi müşteri paniğe kapılır, ikinci kez ödemeye
                çalışır ya da bankasını arardı — oysa ödemesi yolda.
            --}}
            <h1>Ödemeniz işleniyor</h1>
            <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>
            <p>
                Bankanızdan onay bekliyoruz. Bu birkaç saniye sürebilir —
                sayfayı yenileyerek durumu görebilirsiniz.
            </p>
        @endif

        {{--
          DURUMA GÖRE BAĞLANTILAR (4.6Y)

          ⚠️ Önce üç durumda da TEK bir "Alışverişe devam et" vardı: ödemesi
          başarılı müşteri siparişini göremiyor, başarısız olan ise elinde
          hiçbir şey kalmadan mağazaya atılıyordu.

          ⚠️ `target="_top"` HEPSİNDE: sayfa ödeme çerçevesinin içinde
          açılmış olabilir (4.5-K1). Olmasaydı müşteri sipariş detayını
          küçük bir çerçevede görürdü.
        --}}
        @if ($durum === 'success' && $siparis->customer_id !== null && auth('customer-web')->id() === $siparis->customer_id)
            {{--
              ⚠️ KOŞUL ŞART: sipariş detayı `auth:customer-web` arkasında ve
              `customer_id` eşleşmesi arıyor. MİSAFİR ÖDEMESİ AÇIK olduğu
              için koşulsuz bağlantı, misafiri önce giriş ekranına sonra
              404'e götürürdü. Misafirin elindeki referans sipariş numarası
              ve o zaten yukarıda yazıyor.
            --}}
            <p>
                <a class="dugme" target="_top"
                   href="{{ route('vitrin.hesap.siparis', ['siparis' => $siparis->uuid]) }}">Siparişimi görüntüle</a>
            </p>

        @elseif ($durum === 'failed')
            {{--
              ⚠️ "Sepete dön" YAZMIYOR ve yazamaz: ölçüldü, ödeme
              başarısız olunca sepet `converted` kalıyor ve vitrinde BOŞ
              görünüyor. Bağlantı müşteriyi boş bir sepete götürürdü.
              Bunun yerine ürünler geri KONULUYOR.

              ⚠️ Adres imzalı üretiliyor; gerekçesi rota dosyasında.
            --}}
            <form method="post" target="_top"
                  action="{{ URL::temporarySignedRoute('vitrin.odeme.sepeteGeri', now()->addHours(24), ['siparis' => $siparis->uuid]) }}">
                {{--
                  ⚠️ `@csrf` YOK ve olamaz: bu sayfa `api` grubunda render
                  ediliyor (sağlayıcı POST ettiği için oturumsuz), yani
                  jeton üretilemiyor. Koruma imzada — bkz. `bootstrap/app.php`.
                --}}
                <button type="submit" class="dugme">Ürünleri sepete geri koy</button>
            </form>
        @endif

        {{-- ⚠️ `target="_top"`: betik çalışmazsa müşteri çerçeveden elle çıkabilsin. --}}
        <p><a class="dugme" target="_top" href="{{ route('vitrin.anasayfa') }}">Alışverişe devam et</a></p>
    </div>

@endsection
