<?php

declare(strict_types=1);

/*
| SAYFALAMA ÇEVİRİLERİ
|
| ⚠️ BU DOSYA HİÇ YOKTU ve eksikliği SESSİZDİ: Laravel çeviri bulamayınca
| anahtarın kendisini basıyor, yani panelde sayfalama düğmelerinde
| "pagination.previous" ve "pagination.next" YAZIYORDU. Dört sayfada
| birden (Siparişler · Ürünler · Müşteriler · Yorumlar).
|
| ⚠️ 4.6AA'daki `validation.uploaded` ile AYNI AİLE: çevirisi olmayan
| anahtar hata vermiyor, ekranda ham hâliyle duruyor ve ekranı okuyan
| bir test yoksa kimse görmüyor. Orada da "unutulursa hemen fark edilir"
| denmişti; fark edilmedi.
|
| ⚠️ Gerçek tarayıcı koşusu yakaladı (4.6AF), 963 testin hiçbiri değil.
*/

return [
    'previous' => '&laquo; Önceki',
    'next' => 'Sonraki &raquo;',
];
