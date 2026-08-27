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


    {{-- ⚠️ TERMİNAL DURUMDA SAYAÇ TEMİZLENİYOR. Kalsaydı müşteri aynı
         tarayıcı oturumunda ikinci bir ödeme yaptığında sayaç dolu
         başlar ve o sipariş için otomatik yenileme HİÇ çalışmazdı. --}}
    @if ($durum !== 'processing')
        <script>
            try { window.sessionStorage.removeItem('tikmarka-odeme-bekleme-{{ $siparis->uuid }}') } catch (e) {}
        </script>
    @endif

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

            {{--
                ★ OTOMATİK YENİLEME (4.6AK) — bildirilen kusur buydu.

                Müşteri "ödemeniz işleniyor" ekranında bekliyor, hiçbir şey
                olmuyor ve ancak sayfayı ELLE yenileyince sonucu görüyordu.
                Sağlayıcı bildirimi 10-15 saniye sürüyor; o aralıkta ekran
                ölü kalıyordu ve müşteri ödemesinin akıbetini bilmiyordu.
            --}}
            <p id="bekliyor-notu">
                Bankanızdan onay bekliyoruz. Bu birkaç saniye sürebilir —
                sayfa kendini yenileyecek.
            </p>

            {{-- ⚠️ Süre dolunca gösterilecek: sipariş KAYBOLMADI, yalnızca
                 onay gecikti. Müşterinin elinde sipariş numarası var. --}}
            <p id="gecikti-notu" hidden>
                Onay hâlâ gelmedi. <strong>Siparişiniz kaydedildi</strong> ve
                numarası yukarıda; ödeme onaylandığında e-posta göndereceğiz.
                Sayfayı yenileyerek de bakabilirsiniz.
            </p>

            <script>
                (function () {
                    /*
                     | ⚠️ SAYAÇ ADRESE KONAMAZ. Bu sayfanın adresi İMZALI
                     | ve imza sorgu dizesini de kapsıyor: `?deneme=3`
                     | eklemek imzayı geçersiz kılar ve müşteri 403 görür.
                     | Bu yüzden sayaç `sessionStorage`'da.
                     */
                    var ANAHTAR = 'tikmarka-odeme-bekleme-{{ $siparis->uuid }}'
                    var EN_COK = 12        // 12 × 5 sn ≈ 1 dakika
                    var ARALIK = 5000

                    var depo = null
                    try { depo = window.sessionStorage } catch (e) { depo = null }

                    /*
                     | ⚠️ DEPO YOKSA HİÇ YENİLEME (gizli sekme, kapalı
                     | depolama). Sayaç tutulamayınca sayfa SONSUZA KADAR
                     | kendini yenilerdi — sunucuya da müşteriye de zarar.
                     | Bu durumda elle yenileme notu gösteriliyor.
                     */
                    if (depo === null) {
                        document.getElementById('bekliyor-notu').hidden = true
                        document.getElementById('gecikti-notu').hidden = false
                        return
                    }

                    var sayi = parseInt(depo.getItem(ANAHTAR) || '0', 10)
                    if (isNaN(sayi) || sayi < 0) sayi = 0

                    if (sayi >= EN_COK) {
                        depo.removeItem(ANAHTAR)
                        document.getElementById('bekliyor-notu').hidden = true
                        document.getElementById('gecikti-notu').hidden = false
                        return
                    }

                    setTimeout(function () {
                        depo.setItem(ANAHTAR, String(sayi + 1))
                        window.location.reload()
                    }, ARALIK)
                })()
            </script>
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
