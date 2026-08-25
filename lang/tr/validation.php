<?php

/*
| Doğrulama mesajlarının Türkçesi.
|
| ⚠️ .env'de hem APP_LOCALE hem APP_FALLBACK_LOCALE 'tr'. Bu dosya olmasaydı
| kullanıcı ham anahtar görürdü: "validation.required".
|
| Laravel'in TÜM kurallarını çevirmiyoruz — yalnızca kullandıklarımızı.
| Yeni bir kural kullanıldığında buraya eklenir; unutulursa anahtar görünür
| ve hemen fark edilir.
*/

return [
    'required' => ':attribute alanı zorunludur.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'string' => ':attribute metin olmalıdır.',
    'boolean' => ':attribute doğru veya yanlış olmalıdır.',
    'confirmed' => ':attribute tekrarı eşleşmiyor.',

    'min' => [
        'string' => ':attribute en az :min karakter olmalıdır.',
        'numeric' => ':attribute en az :min olmalıdır.',
    ],
    'max' => [
        'string' => ':attribute en fazla :max karakter olabilir.',
        'numeric' => ':attribute en fazla :max olabilir.',
        'file' => ':attribute en fazla :max KB olabilir.',
    ],

    /*
    | DOSYA YÜKLEME (4.6AA)
    |
    | ⚠️ `uploaded` GERÇEK KULLANIMDA ISIRDI: marka 4,8 MB'lık bir ürün
    | fotoğrafı yükledi ve ekranda ham anahtar gördü — `validation.uploaded`.
    | Bu mesaj yalnızca kural ihlalinde değil, PHP'nin dosyayı hiç KABUL
    | ETMEDİĞİ durumda da çıkıyor; yani sebebini söylemesi en çok gereken
    | mesajdı ve hiç çevrilmemişti.
    |
    | ⚠️ Bu dosyanın kendi yorumu "unutulursa anahtar görünür ve hemen fark
    | edilir" diyor. Fark EDİLMEDİ — çünkü hiçbir test ekranı okumuyordu.
    */
    'uploaded' => ':attribute yüklenemedi. Dosya çok büyük olabilir ya da yükleme yarıda kesilmiş olabilir.',
    'file' => ':attribute bir dosya olmalıdır.',
    'image' => ':attribute bir görsel olmalıdır (JPEG, PNG veya WebP).',
    'mimes' => ':attribute şu türlerden biri olmalıdır: :values.',
    'dimensions' => ':attribute geçersiz boyutlarda.',

    /*
    | Alan adlarının kullanıcıya görünen karşılığı. Olmasaydı mesajda
    | "accepts_marketing alanı zorunludur" yazardı.
    */
    'attributes' => [
        'name' => 'ad',
        'email' => 'e-posta adresi',
        'password' => 'parola',
        'phone' => 'telefon',
        'accepts_marketing' => 'pazarlama izni',
        'title' => 'başlık',
        'full_name' => 'ad soyad',
        'city' => 'il',
        'district' => 'ilçe',
        'line1' => 'adres',
        'image' => 'görsel',
        'sku' => 'stok kodu',
    ],
];
