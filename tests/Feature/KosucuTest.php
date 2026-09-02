<?php

/*
| GÖZETİMSİZ KOŞUCU (A6)
|
| `claude -p` her görevi alıp yapıp ÇIKIYOR — kimse bakmıyor. Gözetim
| altında bir insanın yakaladığı şeyleri (tünel açık kaldı, disk doldu,
| süit zaten koşuyor) burada betiğin kendisi yakalamak zorunda.
|
| ⚠️ ÖLÇÜLDÜ, VARSAYILMADI: hook'lar `-p` modunda YÜKLENİYOR. Headless
| oturuma `git checkout PLAN.md` denettirildi ve kilide takıldı ("requires
| approval"), oturum engeli aşmaya çalışmadı. Yardım metni "çalışma alanı
| güven diyaloğu atlanıyor" dediği için bu açık bir soruydu.
|
| ⚠️ İDDİALAR YORUMSUZ GÖVDEYE BAKIYOR: betiğin kendi yorumları kuralları
| ANLATIYOR (`--bare` HİÇBİR KOŞULDA kullanılmaz gibi), ham metinde arayan
| bir iddia bu yüzden yanlış sonuç verirdi (4.6AE).
*/

function kosucuKodu(): string
{
    return yorumsuz(base_path('.claude/kosucu.sh'));
}

it('★★★ KOSUCU VAR ve CALISTIRILABILIR', function () {
    $yol = base_path('.claude/kosucu.sh');

    expect(file_exists($yol))->toBeTrue('kosucu.sh yok');

    /*
    | ⚠️ `is_executable()` KULLANILMAZ: testler konteynerde root koşuyor ve
    | bit hiç yokken bile `true` dönüyor (A2). Bit doğrudan okunuyor.
    */
    expect(fileperms($yol) & 0111)->not->toBe(0, 'kosucu.sh calistirma biti yok');
});

it('★★★ DORT KOSU ONCESI DENETIM de yerinde', function () {
    /*
    | Dördü de bu projede ÖLÇÜLMÜŞ bir kayıptan geliyor:
    |   disk        A4 · ENOSPC sonrası hiçbir komut çalışmadı
    |   docker      disk dolunca daemon ölüyor
    |   süit        iki süit aynı test veritabanında çöküyor
    |   kirli ağaç  yarım kalan kırma denemesi göreve karışır (B5)
    */
    $kod = kosucuKodu();

    foreach (['df -g', 'docker version', 'artisan test', 'status --porcelain'] as $denetim) {
        expect($kod)->toContain($denetim);
    }
});

it('★★★ TEHLIKELI ARACLAR KAPALI ve --bare HIC KULLANILMIYOR', function () {
    /*
    | ⚠️ `--bare` hook'ları atlıyor. Üç kilidimiz (git checkout engeli, süit
    | kilidi, pint kapısı) tam da gözetimsiz koşu için var; onları kapatan
    | bayrak, koşucunun bütün güvenlik hikâyesini siler.
    */
    $kod = kosucuKodu();

    expect($kod)->not->toContain('--bare');
    expect($kod)->not->toContain('bypassPermissions');
    expect($kod)->not->toContain('--dangerously-skip-permissions');

    foreach (['git push', 'rm -rf', 'docker system prune'] as $yasak) {
        expect($kod)->toContain($yasak);
    }
});

it('★★★ TUNEL DENETIMI var — acik ngrok makineyi internete aciyor', function () {
    /*
    | Deneme koşusunda YANLIŞ ALARM DEĞİLDİ: tünel gerçekten açıktı (önceki
    | oturumdan kalmış) ve koşucu kapattı. Kapatmak yazılı kuraldı;
    | gözetimsiz koşuda kuralın tutmadığı varsayılıyor.
    */
    $kod = kosucuKodu();

    expect($kod)->toContain('ngrok');
    expect($kod)->toContain('--profile tunel stop');
});

it('★★ TASINABILIRLIK: mapfile ve timeout KULLANILMIYOR', function () {
    /*
    | ⚠️ İKİSİ DE ÖLÇÜLDÜ VE ISIRDI:
    |   `timeout`  macOS'ta YOK → docker denetimi daemon sağlamken HER
    |              koşuyu durduruyordu (yanlış alarm, en sinsisi)
    |   `mapfile`  bash 4+ → macOS'ta /bin/bash 3.2.57, dizi boş kalıyor
    */
    $kod = kosucuKodu();

    expect($kod)->not->toContain('mapfile');
    expect($kod)->not->toContain('timeout ');
});

it('★★ HEADLESS OTURUM STDIN BEKLEMIYOR', function () {
    /*
    | stdin bağlıyken oturum 3 saniye veri bekleyip günlüğe uyarı basıyor.
    | Tek görevde zararsız; kırk görevlik listede iki dakika.
    */
    expect(kosucuKodu())->toContain('< /dev/null');
});
