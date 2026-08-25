{{--
    Vitrin düzeni — MARKANIN sitesi, bizim değil. (4A)

    ⚠️ Bu dosyayı marka DÜZENLEYEMEZ (4-K5). Blade PHP'dir ve kum havuzu
    yoktur; markanın yazdığı şablonu render etmek uzaktan kod çalıştırmadır
    ve şema bazlı kiracılıkta bedeli BÜTÜN markaların verisidir.
    Marka yalnızca `settings.theme` üzerinden AYAR seçiyor.

    ⚠️ Aşağıdaki her `$tema` değeri [ThemeSettings]'ten geçmiş, yani
    doğrulanmış. Ayarı doğrudan okuyup buraya basmak, kapattığımız kapının
    yanındaki pencereyi açmak olurdu.
--}}
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- ⚠️ Başlık MARKANIN adı. Vitrin markanın sitesi; bizim adımız geçmiyor. --}}
    <title>@yield('baslik', $tema['ad'])</title>

    <meta name="description" content="@yield('aciklama', $tema['ad'])">

    {{--
        ⚠️ TEMA BETİĞİ CSS'TEN ÖNCE VE SENKRON. (4.6AB)

        Vitrin sunucuda render ediliyor (4-K1); tema seçimi tarayıcıda
        saklanıyor. Betik `defer` olsaydı ya da gövdeye konsaydı sayfa
        önce AÇIK temayla boyanır, sonra koyuya atlardı — gece yarısı göz
        alan bir beyaz parlama (FOUC).

        ⚠️ Seçim `localStorage`'da, çerezde DEĞİL: sunucunun bu bilgiye
        ihtiyacı yok ve çerez olsaydı `EncryptCookies` istisna listesine
        girmesi gerekirdi (4A'daki `CartToken` tuzağı).

        ⚠️ `try/catch`: gizli sekmede bazı tarayıcılarda `localStorage`
        erişimi istisna fırlatıyor. Korunmasaydı betik patlar ve sayfadaki
        DİĞER betikler de çalışmazdı.
    --}}
    <script>
        (function () {
            try {
                var secim = localStorage.getItem('tikmarka-tema')
                if (secim === 'koyu' || secim === 'acik') {
                    document.documentElement.setAttribute('data-tema', secim)
                }
            } catch (e) {}
        })()
    </script>

    <style>
        /*
        | ⚠️ Renk ve yazı tipi CSS değişkeni olarak giriyor — kural
        | gövdesine değil. Değer yine de [ThemeSettings]'te kalıba
        | uydurulmuş durumda; burası ikinci kapı, tek kapı değil.
        */
        /*
        | ── RENK BELİRTEÇLERİ (4.6AB) ─────────────────────────────────
        |
        | ⚠️ Önce ~60 renk KURAL GÖVDESİNE sabit yazılıydı ve yalnızca iki
        | değişken vardı. Koyu tema eklemek her kuralı tek tek elden
        | geçirmek demekti; belirteçlere çevrilince koyu tema YALNIZCA bu
        | bloğun yeniden tanımlanması oldu.
        |
        | ⚠️ `--marka` ve `--yazi` MARKA AYARINDAN geliyor ve öyle
        | KALIYOR: marka rengini değiştirme özelliği (4A) bu blokla
        | kırılmamalı.
        */
        :root {
            --marka: {{ $tema['renk'] }};
            --yazi: {{ $tema['yazi_tipi'] }};

            --zemin: #ffffff;
            --yuzey: #f5f5f4;
            --yuzey-2: #fafaf9;
            --kenar: #e7e5e4;
            --kenar-koyu: #d6d3d1;
            --metin: #1c1917;
            --metin-2: #57534e;
            --soluk: #78716c;
            --soluk-2: #a8a29e;
            --tehlike: #be123c;
            --uyari-zemin: #fef3c7;
            --uyari-kenar: #fcd34d;
            --hata-zemin: #fee2e2;
            --hata-kenar: #fca5a5;
            --tehlike-metin: #b91c1c;
            --basari-zemin: #dcfce7;
            --basari-kenar: #86efac;
            --uyari-metin: #b45309;
            --yildiz: #ca8a04;
            --baglanti: #c2410c;
            --golge: rgba(0, 0, 0, .08);
        }

        /*
        | ── KOYU TEMA ────────────────────────────────────────────────
        |
        | İKİ YOL: işletim sisteminin tercihi (`prefers-color-scheme`) ve
        | müşterinin AÇIK seçimi (`data-tema`).
        |
        | ⚠️ Sistem kuralı `:not([data-tema])` ile korunuyor: müşteri
        | açıkça "açık tema" dediyse sistem tercihi onu EZMEMELİ. Koruma
        | olmasaydı gece modundaki telefonda "açık tema" seçimi hiç
        | çalışmazdı.
        |
        | ⚠️ `--marka` KOYU TEMADA DA MARKANIN RENGİ. Değiştirilseydi
        | marka kimliği kaybolurdu. Bunun bedeli bilinçli: çok koyu bir
        | marka rengi koyu zeminde sönük kalabilir — bu yüzden marka rengi
        | METİN rengi olarak değil VURGU olarak kullanılıyor ve okunması
        | gereken her metin `--metin` belirtecinden geliyor.
        */
        @media (prefers-color-scheme: dark) {
            :root:not([data-tema="acik"]) {
                --zemin: #1c1917;
                --yuzey: #292524;
                --yuzey-2: #232020;
                --kenar: #44403c;
                --kenar-koyu: #57534e;
                --metin: #f5f5f4;
                --metin-2: #d6d3d1;
                --soluk: #a8a29e;
                --soluk-2: #78716c;
                --tehlike: #fb7185;
                --uyari-zemin: #422006;
                --uyari-kenar: #a16207;
                --hata-zemin: #450a0a;
                --hata-kenar: #991b1b;
                --tehlike-metin: #fca5a5;
                --basari-zemin: #052e16;
                --basari-kenar: #166534;
                --uyari-metin: #fcd34d;
                --yildiz: #facc15;
                --baglanti: #fb923c;
                --golge: rgba(0, 0, 0, .4);
            }
        }

        :root[data-tema="koyu"] {
            --zemin: #1c1917;
            --yuzey: #292524;
            --yuzey-2: #232020;
            --kenar: #44403c;
            --kenar-koyu: #57534e;
            --metin: #f5f5f4;
            --metin-2: #d6d3d1;
            --soluk: #a8a29e;
            --soluk-2: #78716c;
            --tehlike: #fb7185;
            --uyari-zemin: #422006;
            --uyari-kenar: #a16207;
            --hata-zemin: #450a0a;
            --hata-kenar: #991b1b;
            --tehlike-metin: #fca5a5;
            --basari-zemin: #052e16;
            --basari-kenar: #166534;
            --uyari-metin: #fcd34d;
            --yildiz: #facc15;
            --baglanti: #fb923c;
            --golge: rgba(0, 0, 0, .4);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: var(--yazi);
            color: var(--metin);
            background: var(--yuzey-2);
            line-height: 1.6;
        }

        .kapsa { max-width: 1100px; margin: 0 auto; padding: 0 20px; }

        header {
            background: var(--zemin);
            border-bottom: 1px solid var(--kenar);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        header .kapsa {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-top: 16px;
            padding-bottom: 16px;
        }

        .logo {
            font-size: 20px;
            font-weight: 800;
            color: var(--marka);
            text-decoration: none;
        }

        .logo img { height: 34px; display: block; }

        .ara { margin-left: auto; display: flex; gap: 8px; }

        .ara input {
            padding: 8px 12px;
            border: 1px solid var(--kenar-koyu);
            border-radius: 8px;
            font: inherit;
            min-width: 200px;
        }

        .dugme {
            background: var(--marka);
            color: var(--zemin);
            border: 0;
            border-radius: 8px;
            padding: 8px 16px;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .sepet { text-decoration: none; color: var(--metin); font-weight: 600; white-space: nowrap; }

        .sepet span {
            background: var(--marka);
            color: var(--zemin);
            border-radius: 999px;
            padding: 1px 8px;
            font-size: 13px;
            margin-left: 4px;
        }

        .izgara {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            padding: 32px 0;
        }

        .kart {
            background: var(--zemin);
            border: 1px solid var(--kenar);
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }

        .kart img { width: 100%; aspect-ratio: 1; object-fit: cover; background: var(--yuzey); display: block; }

        /*
        | ⚠️ GÖRSELSİZ ÜRÜN için gerçek bir yer tutucu. Önce boş bir SVG
        | veri adresi basılıyordu ve tarayıcı KIRIK KARE çiziyordu —
        | müşteriye "bir şey yüklenemedi" izlenimi veriyordu.
        */
        .kart .yok, .bos-gorsel {
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--yuzey), var(--kenar));
            color: var(--soluk-2);
            font-size: 13px;
        }
        .kart .govde { padding: 12px; display: flex; flex-direction: column; gap: 4px; }
        .kart .ad { font-weight: 600; font-size: 15px; }
        .kart .fiyat { color: var(--marka); font-weight: 700; }

        .bos { padding: 64px 0; text-align: center; color: var(--soluk); }

        /* GÖRSEL İYİLEŞTİRME (4.5F) — yeni yapı değil, mevcut yapının cilası */
        .kart { transition: box-shadow .15s, transform .15s; }
                /* ⚠️ Gölge de BELİRTEÇTEN: koyu zeminde siyah gölge görünmüyor,
           daha koyu ve geniş olması gerekiyor. Sabit `rgb(0 0 0 / .08)`
           bırakılsaydı koyu temada kartların yükselme etkisi kaybolurdu
           ve bu SESSİZ bir kayıp olurdu — hata vermez, sadece düzleşir. */
        .kart:hover { box-shadow: 0 6px 20px var(--golge); transform: translateY(-2px); }
        .dugme { transition: filter .15s; }
        .dugme:hover { filter: brightness(.93); }
        .dugme:disabled { opacity: .6; cursor: not-allowed; }

        /*
        | ⚠️ ODAK HALKASI görünür bırakılıyor. `outline: none` yazmak
        | sayfayı "temiz" gösterir ama klavyeyle gezen kullanıcı nerede
        | olduğunu göremez — erişilebilirlik kaybı.
        */
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible {
            outline: 2px solid var(--marka);
            outline-offset: 2px;
        }

        h1, h2 { line-height: 1.25; }
        table { border-collapse: collapse; }

        /* ÜRÜN SAYFASI */
        .urun { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; padding: 32px 0; }
        .urun-gorsel { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 12px; background: var(--yuzey); }
        .bos-gorsel { display: grid; place-items: center; color: var(--soluk-2); }
        .marka-adi { color: var(--soluk); margin-top: -8px; }
        .fiyat-buyuk { font-size: 28px; font-weight: 800; color: var(--marka); }
        .ekle-form { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin: 20px 0; }
        .ekle-form label { display: flex; flex-direction: column; gap: 4px; font-size: 14px; }
        .ekle-form input, .ekle-form select { padding: 8px; border: 1px solid var(--kenar-koyu); border-radius: 8px; font: inherit; }
        .ekle-form input[type=number] { width: 80px; }
        .dugme.buyuk { padding: 12px 24px; font-size: 16px; font-weight: 600; }
        .aciklama { margin-top: 24px; color: var(--metin-2); }

        /* SEPET */
        /* ⚠️ Form içindeki eylem BAĞLANTI GİBİ görünüyor: "iptal et"
           yıkıcı bir düğme gibi durmasın ama tıklanabilir olduğu belli
           olsun. GET bağlantısı yapılamaz — iptal veri değiştiriyor. */
        .baglanti-dugme {
            background: none; border: 0; padding: 0; font: inherit;
            color: var(--baglanti); cursor: pointer; text-decoration: underline;
        }

        /* VARYANT SEÇİCİSİ (4.6A) */
        .varyant-secici { margin: 16px 0; }
        .eksen { margin-bottom: 12px; }
        .eksen-ad { display: block; font-size: 14px; color: var(--metin-2); margin-bottom: 6px; }
        .kutucuklar { display: flex; flex-wrap: wrap; gap: 8px; }
        .kutucuk {
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid var(--kenar-koyu); background: var(--zemin); color: var(--metin);
            border-radius: 8px; padding: 8px 14px; font: inherit; cursor: pointer;
        }
        .kutucuk.secili { border-color: var(--metin); box-shadow: inset 0 0 0 1px var(--metin); }

        /* ⚠️ Tükenen değer GİZLENMİYOR, kapatılıyor: müşteri o seçeneğin
           var olduğunu ama şu an alınamadığını görmeli. */
        .kutucuk:disabled, .kutucuk.tukendi {
            opacity: .45; cursor: not-allowed; text-decoration: line-through;
        }
        .kutucuk .renk {
            width: 14px; height: 14px; border-radius: 50%;
            /* ⚠️ Renk yuvarlağının kenarı: açık renkli swatch'ın açık
               zeminde kaybolmaması için. Koyu temada da aynı sorun ters
               yönde var, o yüzden belirteçten geliyor. */
            border: 1px solid var(--kenar-koyu); display: inline-block;
        }

        .sepet-tablo { width: 100%; border-collapse: collapse; margin: 24px 0; }
        .sepet-tablo td { border-bottom: 1px solid var(--kenar); padding: 14px 8px; vertical-align: top; }
        .sepet-tablo tr.olu { opacity: .55; }
        .satir-form { display: flex; gap: 6px; }
        .satir-form input { width: 64px; padding: 6px; border: 1px solid var(--kenar-koyu); border-radius: 6px; font: inherit; }
        .satir-form button, .sil { padding: 6px 10px; border: 1px solid var(--kenar-koyu); background: var(--zemin); border-radius: 6px; cursor: pointer; font: inherit; }
        .sil { color: var(--tehlike-metin); }
        .uyari { color: var(--uyari-metin); font-size: 13px; margin-top: 4px; }
        .kupon-form { display: flex; gap: 8px; align-items: center; margin: 20px 0; flex-wrap: wrap; }
        .kupon-form input { padding: 8px 12px; border: 1px solid var(--kenar-koyu); border-radius: 8px; font: inherit; }
        .ipucu { color: var(--soluk); font-size: 13px; }

        /* ── Tema düğmesi (4.6AB) ────────────────────────────────── */
        .tema-dugme {
            border: 1px solid var(--kenar-koyu); background: var(--zemin); color: var(--metin);
            border-radius: 999px; width: 36px; height: 36px; cursor: pointer;
            font: inherit; font-size: 15px; line-height: 1; flex: none;
        }

        /* ⚠️ İki simge de basılıyor, biri gizleniyor: JavaScript'in metni
           değiştirmesini beklemek, betik geç yüklenirse yanlış simge
           gösterirdi. Hangisinin görüneceğine CSS karar veriyor. */
        .tema-dugme .gunes { display: none; }
        :root[data-tema="koyu"] .tema-dugme .gunes { display: inline; }
        :root[data-tema="koyu"] .tema-dugme .ay { display: none; }

        @media (prefers-color-scheme: dark) {
            :root:not([data-tema="acik"]) .tema-dugme .gunes { display: inline; }
            :root:not([data-tema="acik"]) .tema-dugme .ay { display: none; }
        }

        /* ── Öneriler (4.6E) ─────────────────────────────────────── */
        .oneri { margin-top: 40px; border-top: 1px solid var(--kenar); padding-top: 12px; }
        .oneri h2 { margin-bottom: 0; }
        .oneri .izgara { padding-top: 16px; }

        /* ── Kategori gezinme (4.6B) ─────────────────────────────── */
        .kirinti { font-size: 13px; color: var(--soluk); margin: 0 0 12px; display: flex; gap: 6px; flex-wrap: wrap; }
        .kirinti a { color: var(--metin-2); }
        .kategori-agaci { list-style: none; padding: 0; margin: 16px 0 0; }
        .kategori-agaci li { padding: 7px 0; border-bottom: 1px solid var(--yuzey); }
        .alt-kategoriler { list-style: none; padding: 0; margin: 0 0 20px; display: flex; gap: 8px; flex-wrap: wrap; }
        .alt-kategoriler a {
            display: inline-block; padding: 6px 12px; border: 1px solid var(--kenar-koyu);
            border-radius: 999px; text-decoration: none; font-size: 14px;
        }

        /* ── Favoriler (4.6D) ────────────────────────────────────── */
        .favori-form { margin: 12px 0; }
        .favori { padding: 8px 14px; border: 1px solid var(--kenar-koyu); border-radius: 6px; background: var(--zemin); cursor: pointer; font: inherit; }
        .favori-dolu { border-color: var(--tehlike); color: var(--tehlike); }
        .favori-listesi { list-style: none; padding: 0; margin: 16px 0 0; }
        .favori-satir { display: flex; gap: 14px; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--yuzey); flex-wrap: wrap; }
        .favori-gorsel { width: 64px; height: 64px; object-fit: cover; border-radius: 6px; }
        .favori-bilgi { flex: 1; min-width: 160px; }

        /* ── Yorumlar (4.6C) ─────────────────────────────────────── */
        .yorumlar { margin-top: 40px; border-top: 1px solid var(--kenar); padding-top: 24px; }
        .yorum-ozet { font-size: 18px; margin: 0 0 16px; }
        .yorum-listesi { list-style: none; padding: 0; margin: 0 0 24px; }
        .yorum { border-bottom: 1px solid var(--yuzey); padding: 14px 0; }
        .yorum-bas { margin: 0 0 6px; display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
        .yorum-puan { color: var(--yildiz); letter-spacing: 1px; }
        .yorum-yazar { font-weight: 600; }
        .yorum-baslik, .yorum-metin { margin: 0 0 6px; }
        .yorum-metin { white-space: pre-line; }
        .yorum-form { display: grid; gap: 8px; max-width: 520px; }
        .yorum-form label { font-weight: 600; font-size: 14px; }
        .yorum-form input, .yorum-form select, .yorum-form textarea {
            padding: 8px 10px; border: 1px solid var(--kenar-koyu); border-radius: 6px; font: inherit; width: 100%;
        }
        .yorum-form button { justify-self: start; padding: 8px 16px; border: 0; border-radius: 6px; background: var(--metin); color: var(--zemin); cursor: pointer; font: inherit; }

        /* ⚠️ Ödemeden vazgeçme (4.6Z) — iframe'in ALTINDA ve sade tutuluyor:
           kart formunun yanında dikkat çeken bir düğme, ödemeye devam etmek
           isteyen müşteriyi tereddüde düşürürdü. */
        .odeme-vazgec { margin-top: 16px; }
        .engel-kutusu { background: var(--uyari-zemin); border: 1px solid var(--uyari-kenar); border-radius: 10px; padding: 14px 18px; margin: 20px 0; }

        /* BİLDİRİMLER */
        .bildirim { padding: 12px 18px; border-radius: 10px; margin: 16px 0; }
        .bildirim.iyi { background: var(--basari-zemin); border: 1px solid var(--basari-kenar); }
        .bildirim.kotu { background: var(--hata-zemin); border: 1px solid var(--hata-kenar); }

        /* ÖDEME */
        .odeme-form { display: grid; grid-template-columns: 1.4fr 1fr; gap: 32px; padding: 24px 0; align-items: start; }
        .odeme-form h2 { font-size: 17px; margin: 24px 0 8px; }
        .odeme-form label { display: flex; flex-direction: column; gap: 4px; font-size: 14px; margin-bottom: 12px; }
        .odeme-form input[type=text], .odeme-form input[type=email], .odeme-form input[type=tel] {
            padding: 9px 12px; border: 1px solid var(--kenar-koyu); border-radius: 8px; font: inherit;
        }
        .ikili { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .odeme-sag { background: var(--zemin); border: 1px solid var(--kenar); border-radius: 12px; padding: 20px; position: sticky; top: 90px; }
        .ozet { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .ozet td { padding: 6px 0; border-bottom: 1px solid var(--yuzey); font-size: 14px; }
        .ozet .sag { text-align: right; white-space: nowrap; }
        .onay { flex-direction: row !important; align-items: flex-start; gap: 8px; margin: 16px 0; }
        .onay input { margin-top: 4px; }

        /* "VİTRİNLİ" DÜZENİ (4G) — yalnızca yerleşim farkı, ayrı tema değil */
        .karsilama { padding: 48px 0 8px; text-align: center; }
        .karsilama h1 { font-size: 34px; margin: 0 0 8px; }
        .karsilama p { color: var(--soluk); margin: 0; }
        .urun-genis { grid-template-columns: 1fr; max-width: 720px; margin: 0 auto; }

        /* MÜŞTERİ HESABI (4.5D) */
        .hesap { max-width: 900px; margin: 0 auto; padding: 24px 0; }
        .hesap-dar { max-width: 460px; margin: 0 auto; padding: 24px 0; }
        .hesap-dar label, .hesap-adres label { display: flex; flex-direction: column; gap: 4px; font-size: 14px; margin-bottom: 12px; }
        .hesap-dar input { padding: 9px 12px; border: 1px solid var(--kenar-koyu); border-radius: 8px; font: inherit; }
        .hesap-bas { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .hesap-cikis { margin-left: auto; }
        .onay-satiri { flex-direction: row !important; align-items: flex-start; gap: 8px; }
        .adres-kart { border: 1px solid var(--kenar); border-radius: 10px; padding: 14px; margin-bottom: 12px; background: var(--zemin); }
        .adres-baslik { display: inline-block; background: var(--yuzey); border-radius: 999px; padding: 1px 10px; font-size: 12px; margin-bottom: 4px; }

        /* YASAL METİN (4.5A) */
        .yasal { max-width: 760px; margin: 0 auto; padding: 32px 0; }
        .yasal h1 { font-size: 26px; }
        .yasal-surum { color: var(--soluk); font-size: 14px; margin-top: -8px; }
        .yasal-metin { margin-top: 20px; color: var(--metin-2); }
        .yasal-liste { line-height: 2.2; }
        footer a { color: inherit; }

        /* GÖMÜLÜ ÖDEME (4.5-K1) */
        .odeme-gomulu { max-width: 760px; margin: 0 auto; padding: 24px 0; }
        .odeme-cercevesi { width: 100%; min-height: 640px; border: 1px solid var(--kenar); border-radius: 12px; background: var(--zemin); }

        /* SONUÇ */
        .sonuc { padding: 56px 0; text-align: center; }
        .siparis-no { font-size: 17px; }

        /*
        | ── MOBİL VE TABLET (4.6AB) ──────────────────────────────────
        |
        | ⚠️ Önce TEK medya sorgusu vardı ve yalnızca üç ızgarayı tek
        | sütuna indiriyordu. Ölçülen asıl kırılmalar başka yerdeydi:
        | başlık çubuğu (logo + iki menü + arama + sepet + hesap) dar
        | ekranda TAŞIYOR, tablolar yatay kaydırma zorluyor, ürün
        | ızgarasının 220px'lik en küçük sütunu 360px'lik telefonda
        | kenar boşluklarıyla birlikte sığmıyordu.
        |
        | ⚠️ Yaklaşım MOBİL ÖNCELİKLİ DEĞİL, düzeltici: mevcut CSS masaüstü
        | varsayımıyla yazılmış ve baştan yazmak 4.6AB'nin kapsamı değil.
        | Bu, tasarım yenilemesinin (listede ayrı madde) işi.
        */

        /* ── TABLET ──────────────────────────────────────────────── */
        @media (max-width: 900px) {
            .kapsa { padding: 0 16px; }

            /* ⚠️ Arama kutusu `margin-left: auto` ile sağa itiliyordu;
               dar ekranda o itme, öteki bağlantıları taşırıyor. */
            .ara { margin-left: 0; flex: 1 1 200px; }

            header .kapsa { flex-wrap: wrap; gap: 12px; }

            .urun { grid-template-columns: 1fr; }
            .odeme-form { grid-template-columns: 1fr; }
            .ikili { grid-template-columns: 1fr; }
        }

        /* ── TELEFON ─────────────────────────────────────────────── */
        @media (max-width: 620px) {
            .kapsa { padding: 0 12px; }

            /* ⚠️ 220px + boşluklar 360px'lik ekrana sığmıyordu: kartlar
               taşıyor ve sayfa YATAY kayıyordu. */
            .izgara { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }

            /* ⚠️ Yapışkan başlık telefonda ekranın önemli bir kısmını
               yiyor; kaydırırken içerik için yer kalmıyordu. */
            header { position: static; }

            /* ⚠️ Logo esnemezse uzun marka adı arama kutusunu taşırıyor. */
            .logo { flex: 1 1 100%; }
            .logo img { max-height: 34px; }

            /*
            | ⚠️ TABLOLAR KENDİ İÇİNDE KAYIYOR, sayfa değil. Sipariş
            | ayrıntısındaki tablo dar ekranda gövdeyi genişletiyor ve
            | TÜM sayfa yatay kayıyordu — başlık dâhil.
            */
            table { display: block; overflow-x: auto; white-space: nowrap; }

            .yorum-form { max-width: none; }
            .favori-gorsel { width: 48px; height: 48px; }
            .fiyat-buyuk { font-size: 24px; }

            /* ⚠️ Dokunma hedefi: 36px'lik düğme parmakla ıskalanıyor. */
            .tema-dugme { width: 40px; height: 40px; }
        }

        footer {
            border-top: 1px solid var(--kenar);
            margin-top: 48px;
            padding: 24px 0;
            color: var(--soluk);
            font-size: 14px;
        }
    </style>
</head>
<body>

<header>
    <div class="kapsa">
        <a class="logo" href="{{ route('vitrin.anasayfa') }}">
            @if ($tema['logo'])
                {{-- ⚠️ `alt` mağaza adı: logo yüklenmezse marka yine görünür. --}}
                <img src="{{ $tema['logo'] }}" alt="{{ $tema['ad'] }}">
            @else
                {{ $tema['ad'] }}
            @endif
        </a>

        {{-- ⚠️ Kategori YOKSA bağlantı da yok — koleksiyonlarla aynı
             gerekçe (4.6B). "Var" demek ÜRÜNÜ OLAN kategori demek: boş
             ağaca götüren menü maddesi müşteriyi yanıltırdı. --}}
        @if (($kategoriVar ?? false))
            <a class="sepet" href="{{ route('vitrin.kategoriler') }}">Kategoriler</a>
        @endif

        {{-- ⚠️ Koleksiyon YOKSA bağlantı da yok: boş sayfaya götüren bir
             menü maddesi müşteriyi yanıltırdı. --}}
        @if (($koleksiyonVar ?? false))
            <a class="sepet" href="{{ route('vitrin.koleksiyonlar') }}">Koleksiyonlar</a>
        @endif

        {{--
            TEMA DÜĞMESİ (4.6AB) — kullanıcının istediği yer: sağ üst.

            ⚠️ FORM DEĞİL, `<button type="button">`: tema seçimi sunucuyu
            ilgilendirmiyor. Form olsaydı her tıklamada sayfa yeniden
            yüklenir ve müşteri sepette ya da ödeme formunda yazdıklarını
            kaybederdi.

            ⚠️ `aria-pressed` ŞART: düğme iki durumlu ve durum yalnızca
            simgeyle anlatılsaydı ekran okuyucu kullanan müşteri hangi
            temada olduğunu bilemezdi. Değeri betik güncelliyor.

            ⚠️ Betik YOKSA düğme hiçbir şey yapmaz ama sayfa BOZULMAZ:
            tema yine sistem tercihinden geliyor.
        --}}
        <button type="button" class="tema-dugme" data-tema-dugme
                aria-label="Koyu temayı aç/kapat" title="Koyu tema" aria-pressed="false">
            <span class="ay" aria-hidden="true">☾</span><span class="gunes" aria-hidden="true">☀</span>
        </button>

        <form class="ara" method="get" action="{{ route('vitrin.anasayfa') }}">
            <input type="search" name="q" value="{{ $arama ?? '' }}" placeholder="Ürün ara" aria-label="Ürün ara">
            <button class="dugme" type="submit">Ara</button>
        </form>

        {{--
            ⚠️ Sepet sayısı SUNUCUDA yazılıyor — bu 4A'nın çerez kararının
            (CartToken) tek görünür sebebi. Başlık tek yol olarak kalsaydı
            burada her zaman 0 yazardı.
        --}}
        {{--
            ⚠️ Bağlantı GİRİŞ DURUMUNA göre değişiyor: giriş yapmış
            müşteriye "Giriş yap" göstermek, yapmamışa "Hesabım"
            göstermek kadar yanlış olurdu.
        --}}
        @auth('customer-web')
            <a class="sepet" href="{{ route('vitrin.hesap') }}">Hesabım</a>
        @else
            <a class="sepet" href="{{ route('vitrin.giris') }}">Giriş</a>
        @endauth

        <a class="sepet" href="{{ route('vitrin.sepet') }}">
            Sepet @if ($sepetAdedi > 0)<span>{{ $sepetAdedi }}</span>@endif
        </a>
    </div>
</header>

<main class="kapsa">

    {{--
        ⚠️ Bildirimler DÜZENDE, sayfalarda değil: PRG deseninde her işlem
        yönlendirmeyle bitiyor ve sonucu gösterecek tek ortak yer burası.
        Her sayfaya ayrı yazılsaydı biri unutulur ve o işlem sessizce
        "hiçbir şey olmamış" gibi görünürdü.
    --}}
    @if (session('mesaj'))
        <p class="bildirim iyi">{{ session('mesaj') }}</p>
    @endif

    @if (session('hata'))
        <p class="bildirim kotu">{{ session('hata') }}</p>
    @endif

    {{--
        ⚠️ `$errors` VARLIĞI KONTROL EDİLİYOR — ve bu gerçek bir 500'den
        sonra eklendi.

        `$errors` görünümlere `ShareErrorsFromSession` tarafından veriliyor
        ve o middleware yalnızca `web` grubunda çalışıyor. Ödeme dönüşü
        ekranı (4B) `api` grubunda — sağlayıcı POST ettiği için oraya
        taşınamıyor — ve aynı düzeni kullanıyor. Kontrolsüz hâlde
        "Undefined variable $errors" ile 500 veriyordu: müşteri ödemesini
        bitirmiş, dönüş ekranında hata sayfası görüyordu.
    --}}
    @if (isset($errors) && $errors->any())
        <div class="bildirim kotu">
            @foreach ($errors->all() as $hata)
                <div>{{ $hata }}</div>
            @endforeach
        </div>
    @endif

    @yield('icerik')
</main>

<footer>
    <div class="kapsa">
        {{-- ⚠️ Yıl sabit yazılmıyor: her 1 Ocak'ta eskimiş bir sayfa olurdu. --}}
        © {{ date('Y') }} {{ $tema['ad'] }}

        {{--
            ⚠️ Yasal metinlere HER SAYFADAN erişilebilmeli: müşteri
            sözleşmeyi yalnızca ödeme anında değil, öncesinde de
            okuyabilmeli.
        --}}
        · <a href="{{ route('vitrin.yasal.liste') }}">Yasal metinler</a>
    </div>
</footer>

{{--
    TEMA DEĞİŞTİRME BETİĞİ (4.6AB)

    ⚠️ Gövdenin SONUNDA (başlıktaki FOUC betiğinin tersine): burada acele
    yok, düğme tıklanana kadar çalışmasına gerek yok. Başa konsaydı DOM
    hazır olmadan düğmeyi arardı.
--}}
<script>
    (function () {
        var dugme = document.querySelector('[data-tema-dugme]')
        if (!dugme) return

        var kok = document.documentElement

        function koyuMu() {
            var secim = kok.getAttribute('data-tema')
            if (secim) return secim === 'koyu'

            /*
             ⚠️ Seçim yoksa SİSTEM tercihi soruluyor. `false` varsayılsaydı
             gece modundaki telefonda düğme "koyuya geç" der ama sayfa
             zaten koyu olurdu — ilk tıklama AÇIĞA geçirirdi.
            */
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        }

        function isaretle() {
            dugme.setAttribute('aria-pressed', koyuMu() ? 'true' : 'false')
        }

        isaretle()

        dugme.addEventListener('click', function () {
            var yeni = koyuMu() ? 'acik' : 'koyu'
            kok.setAttribute('data-tema', yeni)

            try { localStorage.setItem('tikmarka-tema', yeni) } catch (e) {}

            isaretle()
        })
    })()
</script>

</body>
</html>
