{{--
    Panelin kök görünümü — Inertia buradan başlıyor. (4C)

    ⚠️ VİTRİNDEN AYRI DOSYA. Vitrin sunucuda render edilen Blade (4-K1),
    panel Inertia + Vue. Tek düzen paylaşsalardı biri diğerinin ihtiyacına
    göre bozulurdu: vitrin JS'siz çalışmak zorunda, panel değil.

    ⚠️ SSR YOK (4-K2). `@inertia` yalnızca boş kabı basıyor; sayfa
    tarayıcıda render ediliyor. Panelde SEO gerekmediği için kaybımız yok,
    kazancımız paylaşılan Node sürecinin hiç var olmaması.
--}}
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- ⚠️ Panel ARAMA MOTORUNA KAPALI: özel bir çalışma alanı. --}}
    <meta name="robots" content="noindex, nofollow">

    <title inertia>Panel</title>

    {{--
        ⚠️ TEMA BETİĞİ CSS'TEN ÖNCE VE SENKRON (4.6AE) — vitrindeki
        kararın aynısı (4.6AB). Sonra gelseydi panel önce açık temayla
        boyanır, sonra koyuya atlardı.

        ⚠️ ANAHTAR VİTRİNDEN AYRI (`tikmarka-panel-tema`): panel BİZİM
        aracımız, vitrin markanın sitesi (4C). Aynı anahtar paylaşılsaydı
        personelin vitrinde yaptığı seçim paneli de değiştirirdi — üstelik
        `localStorage` köken başına olduğu için aynı marka alan adında
        ikisi çakışırdı.

        ⚠️ `try/catch`: gizli sekmede `localStorage` istisna fırlatabiliyor
        ve korunmasaydı sayfadaki DİĞER betikler de çalışmazdı.
    --}}
    <script>
        (function () {
            try {
                var secim = localStorage.getItem('tikmarka-panel-tema')
                if (secim === 'koyu' || secim === 'acik') {
                    document.documentElement.setAttribute('data-tema', secim)
                }
            } catch (e) {}
        })()
    </script>

    @vite(['resources/js/panel.js'])
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
