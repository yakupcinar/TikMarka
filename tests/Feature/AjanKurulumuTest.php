<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Skill ve alt-ajan kurulumu
|--------------------------------------------------------------------------
|
| ★ `/kontrol` skill'i ve `sinayici` ajanı, bu oturumda ELLE yapılan
| doğrulama ritüelini yazıya döküyor. `make kontrol` bu işi eksik yapıyor:
| `pint.json` onarımı yok, test veritabanı temizliği yok, CI eşitliği
| (public/build) yok ve `composer test` 300 sn'de zaman aşımına uğruyor
| (süit ~450 sn).
|
| ⚠️ Bu test YAPIYLA YETİNMİYOR. "Dosya var" demek, içindeki adımların
| doğru olduğunu göstermez — birisi skill'i sadeleştirip kritik bir adımı
| silerse dosya hâlâ "var" olur ve doğrulama SESSİZCE eksilir.
*/

/**
 * @return array{0: array<string, string>, 1: string} frontmatter, gövde
 */
function markdownAyristir(string $yol): array
{
    $metin = (string) file_get_contents($yol);

    preg_match('/^---\n(.*?)\n---\n(.*)$/s', $metin, $m);

    $alanlar = [];

    foreach (explode("\n", $m[1] ?? '') as $satir) {
        if (preg_match('/^([a-z-]+):\s*(.*)$/', $satir, $a)) {
            $alanlar[$a[1]] = trim($a[2]);
        }
    }

    return [$alanlar, $m[2] ?? ''];
}

it('★★★ KONTROL SKILLI VAR ve description tasiyor', function () {
    /*
    | ⚠️ `description` olmadan Claude skill'i ne zaman kullanacağını
    | bilemiyor — dosya durur, hiç çağrılmaz.
    */
    [$fm, $govde] = markdownAyristir(base_path('.claude/skills/kontrol/SKILL.md'));

    expect($fm)->toHaveKey('description');
    expect($fm['description'])->not->toBe('');
    expect($govde)->not->toBe('');
});

it('★★★ SKILL 1500 KELIMENIN ALTINDA — uzun skill baglam baskisinda KIRPILIYOR', function () {
    [, $govde] = markdownAyristir(base_path('.claude/skills/kontrol/SKILL.md'));

    $kelime = count(preg_split('/\s+/', trim($govde)) ?: []);

    expect($kelime)->toBeLessThan(1500);
});

it('★★★ SKILL KRITIK ADIMLARIN HEPSINI TASIYOR — sadelestirilirse kirmizi', function () {
    /*
    | ★ Her biri bu oturumda ölçülmüş bir sessiz hataya karşılık geliyor:
    |
    |   pint.json yeniden yazma → errno=35, boyut hakkında YALAN söylüyor
    |   test DB temizliği       → yarıda kesilen koşu, 142 kırmızı
    |   public/build taşıma     → CI'da derlenmiş varlık yok
    |   artisan test            → `composer test` 300 sn'de kesiliyor
    |   çıkış kodu / boş çıktı  → boş çıktı başarı DEĞİL
    |   koşarken dokunma        → yerel koşu eski sayıyı yeşil gördü
    */
    [, $govde] = markdownAyristir(base_path('.claude/skills/kontrol/SKILL.md'));

    $adimlar = [
        'pint.json yeniden yazılıyor' => 'rm -f pint.json',
        'test veritabanı temizleniyor' => 'DROP SCHEMA',
        'CI eşitliği için build taşınıyor' => 'mv public/build',
        'build geri konuyor' => 'mv /tmp/build-yedek public/build',
        'composer test yerine artisan test' => 'php artisan test',
        'boş çıktı uyarısı var' => 'Boş çıktı başarı değil',
        'koşarken dosyaya dokunma uyarısı var' => 'HİÇBİR DOSYAYA DOKUNMA',
    ];

    foreach ($adimlar as $ad => $aranan) {
        expect($ad.': '.(str_contains($govde, $aranan) ? 'var' : 'YOK'))
            ->toBe($ad.': var');
    }
});

it('★★★ SINAYICI AJANI VAR ve KONTROL SKILLINI ONYUKLUYOR', function () {
    /*
    | ⚠️ `skills:` alanı olmadan ajan skill'i GÖRMÜYOR: taze bağlamla
    | başlıyor ve ritüeli kendi bildiği gibi koşturuyor — yani adımları
    | atlıyor ve bunu kimse fark etmiyor.
    */
    $metin = (string) file_get_contents(base_path('.claude/agents/sinayici.md'));

    [$fm] = markdownAyristir(base_path('.claude/agents/sinayici.md'));

    expect($fm)->toHaveKey('name');
    expect($fm)->toHaveKey('description');
    expect($fm['name'])->toBe('sinayici');

    expect('skill önyükleniyor: '.(str_contains($metin, "skills:\n  - kontrol") ? 'evet' : 'HAYIR'))
        ->toBe('skill önyükleniyor: evet');
});

it('★★★ SINAYICI KOD DUZELTEMEZ — arac listesi Edit/Write TASIMIYOR', function () {
    /*
    | ★ Ajanın işi doğrulamak, düzeltmek DEĞİL. Düşen bir testte doğru
    | cevap çoğu zaman "testin kendisi yanlış şeyi ölçüyor" oluyor ve o
    | yargı tam bağlam istiyor — ajanda o bağlam yok.
    |
    | ⚠️ `tools` alanı yazılmazsa ajan BÜTÜN araçları miras alıyor;
    | yani Edit ve Write dâhil. Kısıt açıkça yazılmak zorunda.
    */
    [$fm, $govde] = markdownAyristir(base_path('.claude/agents/sinayici.md'));

    expect($fm)->toHaveKey('tools');

    $araclar = array_map(trim(...), explode(',', $fm['tools']));

    expect($araclar)->not->toContain('Edit');
    expect($araclar)->not->toContain('Write');

    expect('düzeltme yasağı yazılı: '.(str_contains($govde, 'Kod düzeltmezsin') ? 'evet' : 'HAYIR'))
        ->toBe('düzeltme yasağı yazılı: evet');
});

it('★★★ HER SKILL description ve KELIME SINIRI kurallarina uyuyor', function () {
    /*
    | ⚠️ `description` olmadan Claude skill'i ne zaman kullanacağını
    | bilemiyor — dosya durur, hiç çağrılmaz.
    | ⚠️ 1.500 kelime üstü skill bağlam baskısında KIRPILIYOR; kırpılan
    | kısım sessizce yok sayılıyor.
    */
    $skiller = glob(base_path('.claude/skills/*/SKILL.md')) ?: [];

    /*
    | ⚠️ Sayı BİLEREK sabit: yeni skill eklemek kararlı bir iş, silmek ise
    | kayıp. `blok` · `kirma` · `kontrol` · `belge`.
    */
    expect($skiller)->toHaveCount(4);

    foreach ($skiller as $yol) {
        $ad = basename(dirname($yol));

        [$fm, $govde] = markdownAyristir($yol);

        expect($ad.' description: '.(($fm['description'] ?? '') !== '' ? 'var' : 'YOK'))
            ->toBe($ad.' description: var');

        $kelime = count(preg_split('/\s+/', trim($govde)) ?: []);

        expect($ad.' kelime sınırı: '.($kelime < 1500 ? 'tamam' : "AŞILDI ({$kelime})"))
            ->toBe($ad.' kelime sınırı: tamam');
    }
});

it('★★★ KIRMA SKILLI "TESTI SUCLA" KATALOGUNU tasiyor', function () {
    /*
    | ★ Bu skill'in ASIL değeri sıra değil, deneme tutmadığında ne
    | yapılacağı. Bu oturumda 27 denemenin 6'sı tutmadı ve HER BİRİNDE
    | suçlu koddu değil iddiaydı. Katalog o altı vakadan doğdu.
    |
    | ⚠️ Katalog silinip skill "boz, koştur, geri al"a indirgenirse
    | ritüel kalır ama ÖĞRENİLEN ŞEY gider.
    */
    [, $govde] = markdownAyristir(base_path('.claude/skills/kirma/SKILL.md'));

    $vakalar = [
        'yorum okuma' => 'Yorumları ayıkla',
        'script okuma' => '`<script>` bloklarını ayıkla',
        'fixture ayırt edemiyor' => "Fixture'ı farklılaştır",
        'çok argümanlı olumsuz iddia' => 'çok argümanlı',
        'PHP öncelik hatası' => 'karşılaştırmadan **önce** bağlanıyor',
        'is_executable yalanı' => 'fileperms',
        'git checkout yasağı' => '`git checkout` KULLANMA',
        'değişikliğin uygulandığını doğrula' => 'UYGULANDIĞINI doğrula',
    ];

    foreach ($vakalar as $ad => $aranan) {
        expect($ad.': '.(str_contains($govde, $aranan) ? 'var' : 'YOK'))
            ->toBe($ad.': var');
    }
});

it('★★★ BLOK SKILLI DOKUZ ADIMI ve DURDURMA KOSULUNU tasiyor', function () {
    /*
    | ⚠️ Durdurma koşulu kritik: blok "testler yeşil" olunca DEĞİL,
    | "kırma denemeleri kırmızı" olunca bitiyor. Yeşile kadar koşan bir
    | döngü, bu projede ölçülmüş en sık hatayı üretir.
    */
    [, $govde] = markdownAyristir(base_path('.claude/skills/blok/SKILL.md'));

    $adimlar = ['ÖLÇ', 'KARAR VER', 'UYGULA', 'TEST YAZ', 'KIR',
        'GERÇEK İSTEKLE DOĞRULA', 'KONTROL', 'BELGELE', 'COMMIT'];

    foreach ($adimlar as $adim) {
        expect($adim.': '.(str_contains($govde, $adim) ? 'var' : 'YOK'))
            ->toBe($adim.': var');
    }

    expect('durdurma koşulu: '.(str_contains($govde, 'kırma denemeleri kırmızı olunca') ? 'var' : 'YOK'))
        ->toBe('durdurma koşulu: var');
});

it('★★★ OLCUMCU AJANI VAR ve SUITIN GOREMEDIKLERINI tasiyor', function () {
    /*
    | ⚠️ `sinayici` süiti koşturuyor; `olcumcu` süitin GÖREMEDİKLERİNİ
    | koşturuyor. İkisi ayrı ajan çünkü ikisi ayrı soruyu cevaplıyor:
    | "kod bozuldu mu" ile "kodun ölçülmeyen yüzü çalışıyor mu".
    |
    | Bu dört aile dört ayrı blokta ısırdı ve HER SEFERİNDE süit yeşildi.
    */
    $yol = base_path('.claude/agents/olcumcu.md');

    expect(file_exists($yol))->toBeTrue('olcumcu ajani yok');

    [$fm, $govde] = markdownAyristir($yol);

    expect($fm['name'] ?? null)->toBe('olcumcu')
        ->and(strlen((string) ($fm['description'] ?? '')))->toBeGreaterThan(80);

    /*
    | ⚠️ İDDİA TABLOYA BAKIYOR, DOSYAYA DEĞİL. İlk hâli `$govde` içinde
    | kelimeyi arıyordu ve KIRMA DENEMESİ TUTMADI: deneme satırı silindiği
    | hâlde "CSRF" rapor örneğinde ve gövdede geçmeye devam ediyordu.
    | Yani iddia "kelime dosyada var mı" diyordu, ölçmek istediği ise
    | "deneme listede var mı" idi. Katalogdaki *kalıp birden çok yerde*
    | vakası (4D) — hedefi konumla daralt.
    */
    $tabloSatirlari = implode("\n", array_filter(
        explode("\n", $govde),
        fn (string $satir): bool => str_starts_with(trim($satir), '|'),
    ));

    foreach (['Accept', 'CSRF', 'Kimliksiz', 'İki kiracıda'] as $deneme) {
        expect(kucuk($tabloSatirlari))->toContain(kucuk($deneme));
    }

    /*
    | ⚠️ Ajan KOD DÜZELTEMEZ. Bulguyu düzeltmeye kalkarsa "kodun ölçülmeyen
    | yüzü" hakkındaki bilgi kaybolur ve blok yanlış güvenle biter.
    */
    $araclar = array_map('trim', explode(',', (string) ($fm['tools'] ?? '')));

    expect($araclar)->not->toContain('Edit');
    expect($araclar)->not->toContain('Write');
});

it('★★★ BELGE SKILLI DORT ADIMI ve DURDURMA KOSULUNU tasiyor', function () {
    /*
    | "Bilgi sohbette değil DEPODA durur" kuralı CLAUDE.md'de yazılı ama
    | NASIL yazılacağı yazılı değildi. Skill dördünü de taşımalı; biri
    | düşerse devralan ajan eksik bağlam devralır.
    */
    [, $govde] = markdownAyristir(base_path('.claude/skills/belge/SKILL.md'));

    foreach ([
        'PLAN.md',
        'summary.md',
        'kırma denemeleri',
        'TuzakSayimiTest',
        'Durdurma koşulu',
        'süit koşarken',
    ] as $parca) {
        expect(kucuk($govde))->toContain(kucuk($parca));
    }

    /*
    | ⚠️ Tutmayan denemenin de yazılması ÖZELLİKLE ölçülüyor: bu projedeki
    | tuzakların çoğu tutmayan kırma denemesinden çıktı (4.6AC, 4.6AE).
    */
    expect(kucuk($govde))->toContain(kucuk('Tutmayan deneme'));
});
