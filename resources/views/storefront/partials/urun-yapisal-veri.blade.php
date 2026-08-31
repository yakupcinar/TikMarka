{{--
    Ürün yapısal verisi — GÖVDE PHP'DE ÜRETİLİYOR.

    ⚠️ Blade `@context` ve `@type` anahtarlarını KENDİ YÖNERGESİ sanıyor;
    gerekçe [ProductStructuredData]'da yazılı. Buradaki tek iş basmak.

    ⚠️ İKİ DÜZEN DE bu parçayı kullanıyor (4.6A ve 4.6AL'de aynı hata iki
    kez yaşandı: özellik tek düzene eklendi, öteki markada hiç görünmedi).
--}}
<script type="application/ld+json">{!! json_encode($yapisalVeri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
