{{--
    "DAHA FAZLA" — kaydırmayla yükleme, JavaScript'siz de çalışan hâliyle. (B2)

    ★ SAF SONSUZ KAYDIRMA YAZILMADI ve bu bilinçli:

    ⚠️ SEO. Vitrin sunucuda render ediliyor (4-K1) ve bunun tek sebebi
    arama motorunun sayfayı görebilmesi. Ürünler yalnızca JavaScript
    çalışınca yüklenseydi, 25. üründen sonrası taranamaz olurdu — yani
    SSR'ı seçmenin gerekçesi çöpe giderdi.

    ⚠️ JAVASCRIPT KAPALIYSA. Bağlantı gerçek bir `<a href="?sayfa=2">`;
    betik yalnızca onu ÜSTLENİYOR. Betik hiç çalışmazsa müşteri
    tıklayarak devam edebiliyor.

    ⚠️ Bağlantı `withQueryString()` ile üretiliyor (controller): arama
    yapılmışken `?q=` korunuyor, yoksa müşteri sayfa 2'de aramasını
    kaybederdi.
--}}
@if ($urunler->hasMorePages())
    <div class="daha-fazla" data-daha-fazla>
        <a class="dugme" href="{{ $urunler->nextPageUrl() }}" data-sonraki>Daha fazla ürün</a>
    </div>

    <script>
        (function () {
            var kap = document.querySelector('[data-daha-fazla]')
            var izgara = document.querySelector('.izgara:last-of-type')

            if (!kap || !izgara || !('IntersectionObserver' in window)) return

            var yukleniyor = false

            function yukle() {
                var bag = kap.querySelector('[data-sonraki]')
                if (!bag || yukleniyor) return

                yukleniyor = true
                bag.textContent = 'Yükleniyor…'

                fetch(bag.href, { headers: { 'X-Requested-With': 'fetch' } })
                    .then(function (c) { return c.ok ? c.text() : Promise.reject(c.status) })
                    .then(function (html) {
                        var belge = new DOMParser().parseFromString(html, 'text/html')

                        /*
                        | ⚠️ SON ızgara alınıyor: sayfada bölüm ızgaraları da
                        | var ve ilkini almak "çok satanlar"ı tekrar tekrar
                        | eklemek olurdu.
                        */
                        var yeniIzgaralar = belge.querySelectorAll('.izgara')
                        var yeni = yeniIzgaralar[yeniIzgaralar.length - 1]

                        if (yeni) {
                            Array.prototype.forEach.call(yeni.children, function (kart) {
                                izgara.appendChild(document.importNode(kart, true))
                            })
                        }

                        /*
                        | ⚠️ Sonraki bağlantı YENİ sayfadan okunuyor; yoksa
                        | aynı sayfa sonsuza kadar tekrar yüklenirdi.
                        */
                        var sonrakiKap = belge.querySelector('[data-daha-fazla]')
                        var sonraki = sonrakiKap && sonrakiKap.querySelector('[data-sonraki]')

                        if (sonraki) {
                            bag.href = sonraki.href
                            bag.textContent = 'Daha fazla ürün'
                            yukleniyor = false
                        } else {
                            kap.remove()
                        }
                    })
                    .catch(function () {
                        /*
                        | ⚠️ Hata hâlinde bağlantı ESKİ hâline dönüyor:
                        | müşteri elle tıklayıp devam edebilsin. Sessizce
                        | "Yükleniyor…" bırakmak sayfayı ölü gösterirdi.
                        */
                        bag.textContent = 'Daha fazla ürün'
                        yukleniyor = false
                    })
            }

            /* ⚠️ Kaydırma dinlemek yerine gözlemci: her karede çalışmıyor. */
            new IntersectionObserver(function (girisler) {
                if (girisler[0].isIntersecting) yukle()
            }, { rootMargin: '400px' }).observe(kap)

            /* Tıklama da çalışsın — gözlemci tetiklenmeden basan olur. */
            kap.addEventListener('click', function (olay) {
                if (olay.target.closest('[data-sonraki]')) {
                    olay.preventDefault()
                    yukle()
                }
            })
        })()
    </script>
@endif
