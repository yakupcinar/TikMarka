---
paths:
  - "app/Logging/**"
  - "config/logging.php"
  - "docker/alloy/**"
---

# Gözlemlenebilirlik tuzakları — Loki, toplayıcı, etiket

Günlükler Grafana Cloud'a gidiyor (B6.1). Buradaki tuzaklar ya **veriyi
sessizce kaybettiriyor** ya da Loki indeksini şişiriyor; ikisi de hata vermiyor.

⚠️ Bu maddeler `CLAUDE.md`'den **taşındı, kopyalanmadı**. Toplam tuzak
sayısını ölçen test: `tests/Feature/TuzakSayimiTest.php`.

- **LOKI'NİN `auth_enabled: false` AYARI "GİRİŞ KAPALI" DEĞİL, "GİRİŞ DİYE
  BİR ŞEY YOK" DEMEK.** Ona ağdan ulaşabilen herkes **bütün markaların**
  günlüğünü okur. Bu yüzden `loki`/`grafana` servislerine `ports:`
  YAZILMAZ — erişim yalnızca Caddy üzerinden. ⚠️ Ayarın gerçek anlamı çok
  kiracılık: `true` olsaydı her istek `X-Scope-OrgID` isterdi; markaya
  kendi günlüğü gösterilmek istendiği gün açılacak kapı budur.
- **ETİKET KARDİNALİTESİNİ ÜRETİMDE DEĞİL TESTTE ÖLÇ.** `marka` "marka
  sayısı kadar" diye güvenli sayılmıştı; ama test süiti her koşuda yeni
  UUID'li kiracı açıyor ve o UUID etikete düşüyordu. Bulutta ölçüldü:
  3 kiracıya karşı **71 etiket değeri**, her koşuda artıyor. Testler
  toplayıcının okuduğu kanala yazmamalı (`phpunit.xml` → `LOG_CHANNEL`).
- **LOKI ETİKETİ = İNDEKS; SINIRSIZ DEĞER ALAN ALAN ETİKET OLMAZ.** Her
  benzersiz etiket birleşimi ayrı bir akış açıyor. `istek_id` her istekte
  farklı — etiket yapılırsa indeks şişer, sorgular yavaşlar. Satırın içinde
  durur ve LogQL ile aranır. Etiket olabilecekler: `marka`, `seviye`,
  `alanadi`, `durum` (hepsi sınırlı).
  ⚠️ `retention_period` TEK BAŞINA ETKİSİZ: silmeyi compactor yapıyor,
  `retention_enabled: true` olmadan süre dolsa da hiçbir şey silinmiyor.
