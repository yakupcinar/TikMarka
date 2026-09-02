---
name: kontrol
description: Commit öncesi tam doğrulama — biçim, statik analiz ve tüm test süiti. `make kontrol`'ün eksiklerini kapatır (pint.json onarımı, test veritabanı temizliği, CI eşitliği için public/build taşıma, çıkış kodu kontrolü). Bir blok bitmeden ve commit atmadan önce kullan.
allowed-tools: Bash, Read
---

# Tam doğrulama

`make kontrol` bu işi **eksik yapıyor**: `pint.json` bozulmasını onarmıyor,
test veritabanını temizlemiyor, CI eşitliğini kurmuyor ve `composer test`
300 saniyede zaman aşımına uğruyor. Aşağıdaki sıra bu oturumda ölçülerek
oluştu.

## 1 · pint.json'ı yeniden yaz

```bash
rm -f pint.json && printf '{\n    "preset": "laravel"\n}\n' > pint.json
```

⚠️ `pint.json` sık sık bozuluyor (`errno=35 Resource deadlock avoided`) ve
belirti **dosya boyutu hakkında yalan söylüyor**: `filesize()` 28 der,
`file_get_contents()` boş metin döndürür. Tek çözüm silip yeniden yazmak
(inode değişsin). `--config` ile mutlak yol vermek çözüm DEĞİL.

## 2 · Biçim — önce düzelt, sonra sına

```bash
docker compose exec -T app php vendor/laravel/pint/builds/pint --config /var/www/html/pint.json
docker compose exec -T app php vendor/laravel/pint/builds/pint --config /var/www/html/pint.json --test
```

⚠️ **Çıktıyı `| tail` ile boru hattına sokma.** `pint.json` bozukken boş
çıktı gelir ve "geçti" gibi görünür. **Boş çıktı başarı değil, hata
belirtisidir.** İkinci komutun `PASS` yazdığını gör.

⚠️ Sıra önemli: PHPStan düzeltmesi yaptıktan sonra pint'i **tekrar** koş.
CI bir kez tam bunun yüzünden kırmızı döndü.

## 3 · Statik analiz

```bash
docker compose exec -T app composer analyse
```

`[OK] No errors` görmeden devam etme.

## 4 · Test veritabanını temizle

```bash
docker compose exec -T postgres psql -U tikmarka -d tikmarka_test \
  -c "DO \$\$ DECLARE s text; BEGIN FOR s IN SELECT nspname FROM pg_namespace WHERE nspname LIKE 'tenant%' LOOP EXECUTE 'DROP SCHEMA '||quote_ident(s)||' CASCADE'; END LOOP; END \$\$;" \
  -c "TRUNCATE tenants, domains, plans, platform_users, personal_access_tokens RESTART IDENTITY CASCADE;"
```

⚠️ Yarıda kesilen bir koşu kiracı kayıtları bırakıyor ve sonraki süit
**142 kırmızı** verebiliyor. Belirti veri hatası gibi görünür
(`relation "orders" does not exist`), sebep temizlenmemiş durumdur.

## 5 · Süit — CI eşitliğiyle

```bash
mv public/build /tmp/build-yedek
docker compose exec -T app php artisan test 2>&1 | grep -E "^\s+⨯|Tests:|Duration:"
mv /tmp/build-yedek public/build
```

⚠️ `composer test` KULLANMA — 300 sn zaman aşımı var, süit ~450 sn
sürüyor. `php artisan test` doğrudan koşturulur.

⚠️ `public/build` neden taşınıyor: CI'da derlenmiş varlıklar yok. Manifest
yerindeyken geçen bir test CI'da düşebilir — bu bir kez yaşandı.

⚠️ **KOŞARKEN HİÇBİR DOSYAYA DOKUNMA** — belge dosyaları dâhil. Testler
dosyayı koştukları anda okuyor; bu projede `CLAUDE.md` ve `PLAN.md`
okuyan testler var. Süit koşarken `CLAUDE.md` düzenlendi ve yerel koşu
**eski sayıyı yeşil gördü**; gerçek durumu CI gösterdi.

⚠️ `build` geri taşımayı unutma. Unutulursa vitrin yerelde kırık kalır.

## 6 · Raporla

Şu üçünü **sayıyla** bildir:

```
biçim    PASS / kaç dosya
analiz   [OK] No errors  ya da hata sayısı
süit     N passed  ·  düşen varsa test adlarıyla
```

⚠️ "Hata yok" yeterli değil — **kaç test geçti** yaz. Bu projede
"hata yok" dolaylı sinyali iki kez yanlış güven verdi; ikisi de
gönderilecek/koşacak bir şey olmadığında da doğruydu.

## Düşen varsa

Test adını ve iddianın mesajını ver, **yığın izini değil**. Yığın izi 40
satırı tek başına doldurup asıl mesajı ekranın dışına itiyor.
