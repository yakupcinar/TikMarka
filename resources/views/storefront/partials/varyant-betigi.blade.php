{{--
    VARYANT SEÇİM BETİĞİ — ORTAK PARÇA (4.6A · 4.6A.1)

    ⚠️ Seçici parçasıyla BİRLİKTE kullanılır; ayrı düşerse kutucuklar
    çizilir ama hiçbir şey yapmaz.
--}}
    @if ($secici['eksenler'] !== [])
    {{--
        VARYANT SEÇİMİ (4.6A).

        ⚠️ ÇIKMAZ SOKAK OLUŞMAMALI. Bir değerin kapalı olup olmadığı,
        DİĞER eksenlerdeki seçime göre hesaplanıyor. Kendi ekseni de
        hesaba katılsaydı müşteri "Kırmızı" seçtikten sonra tüm bedenler
        kapanınca sıkışır, rengi değiştiremezdi.

        ⚠️ "Satılabilir" bilgisi SUNUCUDAN geliyor (`stock − committed` +
        aktiflik). Burada yeniden hesaplansaydı sepetle iki ayrı formül
        olurdu — 4.5J'deki tuzağın aynısı.
    --}}
    <script>
        (function () {
            var kok = document.querySelector('[data-secici]')
            if (!kok) return

            var varyantlar = JSON.parse(kok.dataset.varyantlar)
            var gizli = document.querySelector('[data-varyant-uuid]')
            var dugme = document.querySelector('[data-ekle-dugme]')
            var fiyatAlani = document.querySelector('[data-fiyat]')
            var uyari = kok.querySelector('[data-secici-uyari]')
            var eksenler = Array.prototype.slice.call(kok.querySelectorAll('[data-eksen]'))

            var secim = {}

            function paraFormat(deger) {
                return Number(deger).toLocaleString('tr-TR', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2,
                }) + ' TL'
            }

            /* Seçime uyan varyantlar — `haric` verilirse o eksen yok sayılır. */
            function uyanlar(aday, haric) {
                return varyantlar.filter(function (v) {
                    for (var eksen in aday) {
                        if (eksen === haric) continue
                        if (v.secenekler[eksen] !== aday[eksen]) return false
                    }
                    return true
                })
            }

            function uygula() {
                eksenler.forEach(function (grup) {
                    var eksenSlug = grup.dataset.eksen

                    /* ⚠️ KENDİ ekseni hariç tutuluyor — çıkmaz sokağın önlemi. */
                    var olasi = uyanlar(secim, eksenSlug)

                    grup.querySelectorAll('[data-deger]').forEach(function (kutu) {
                        var deger = kutu.dataset.deger
                        var varMi = olasi.some(function (v) {
                            return v.secenekler[eksenSlug] === deger && v.satilabilir
                        })

                        kutu.disabled = !varMi
                        kutu.classList.toggle('tukendi', !varMi)
                        kutu.classList.toggle('secili', secim[eksenSlug] === deger)
                    })

                    var liste = grup.querySelector('[data-deger-liste]')
                    if (liste) {
                        Array.prototype.slice.call(liste.options).forEach(function (secenek) {
                            if (!secenek.value) return
                            secenek.disabled = !olasi.some(function (v) {
                                return v.secenekler[eksenSlug] === secenek.value && v.satilabilir
                            })
                        })
                    }
                })

                var tam = eksenler.every(function (grup) { return secim[grup.dataset.eksen] })
                var secilen = tam ? uyanlar(secim)[0] : null

                gizli.value = secilen && secilen.satilabilir ? secilen.uuid : ''
                dugme.disabled = !gizli.value

                if (secilen) fiyatAlani.textContent = paraFormat(secilen.fiyat)

                /* ⚠️ Müşteri NEDEN ekleyemediğini görmeli. */
                if (!tam) {
                    uyari.textContent = 'Devam etmek için tüm seçenekleri belirleyin.'
                    uyari.hidden = false
                } else if (!gizli.value) {
                    uyari.textContent = 'Bu seçenek şu anda stokta yok.'
                    uyari.hidden = false
                } else {
                    uyari.hidden = true
                }
            }

            kok.addEventListener('click', function (olay) {
                var kutu = olay.target.closest('[data-deger]')
                if (!kutu || kutu.disabled) return

                var eksenSlug = kutu.closest('[data-eksen]').dataset.eksen
                secim[eksenSlug] = secim[eksenSlug] === kutu.dataset.deger ? undefined : kutu.dataset.deger
                uygula()
            })

            kok.addEventListener('change', function (olay) {
                var liste = olay.target.closest('[data-deger-liste]')
                if (!liste) return

                secim[liste.closest('[data-eksen]').dataset.eksen] = liste.value || undefined
                uygula()
            })

            /*
             ⚠️ Betik çalışmazsa düğme AÇIK kalıyor ve gizli girdi boş —
             sunucu doğrulaması devreye giriyor. Kapalı bırakılsaydı
             JavaScript'i kapalı müşteri hiçbir şey satın alamazdı.
            */
            uygula()
        })()
    </script>
    @endif
