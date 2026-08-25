{{--
    ÜRÜN YORUMLARI (4.6C)

    ⚠️ ORTAK PARÇA: iki düzen de (`sade`, `vitrinli`) bunu kullanıyor.
    Kopyalansaydı biri güncellenip öteki unutulur ve o düzeni seçmiş
    markanın müşterisi yorumları göremezdi — tema bir AYAR (4-K5), yani
    hangi düzenin kullanıldığını marka belirliyor.

    ⚠️ Vitrin SUNUCUDA render ediliyor (4-K1), yani yorum metni HTML'in
    içinde. Bu bilinçli: arama motoru yorumları görüyor.
--}}
<section class="yorumlar" id="yorumlar">
    <h2>Yorumlar</h2>

    @if ($urun->rating_count > 0)
        <p class="yorum-ozet">
            <strong>{{ number_format((float) $urun->rating_avg, 1, ',', '.') }}</strong> / 5
            <span class="ipucu">({{ $urun->rating_count }} yorum)</span>
        </p>
    @endif

    {{--
        ⚠️ "Henüz yorum yok" bir HATA DEĞİL, yeni ürün için NORMAL.
    --}}
    @if ($yorumlar->isEmpty())
        <p class="ipucu">Bu ürüne henüz yorum yapılmamış.</p>
    @else
        <ul class="yorum-listesi">
            @foreach ($yorumlar as $yorum)
                <li class="yorum">
                    <p class="yorum-bas">
                        <span class="yorum-puan" aria-label="{{ $yorum->rating }} yıldız">{{ str_repeat('★', $yorum->rating) }}{{ str_repeat('☆', 5 - $yorum->rating) }}</span>

                        {{--
                            ⚠️ Ad KISALTILIYOR ("Ahmet Y."), e-posta hiç yok
                            ve kısaltma MODELDE — API cevabı da aynı adı
                            veriyor. `moderation_note` burada YOK, o
                            personel içindir.
                        --}}
                        <span class="yorum-yazar">{{ $yorum->vitrinAdi() ?? 'Müşteri' }}</span>

                        @if ($yorum->moderated_at)
                            {{-- ⚠️ Sunucuda render edilen yüzey saati KENDİ çevirir (4.5M). --}}
                            <span class="ipucu">{{ $yorum->moderated_at->setTimezone($saatDilimi)->format('d.m.Y') }}</span>
                        @endif
                    </p>

                    @if ($yorum->title)
                        <p class="yorum-baslik"><strong>{{ $yorum->title }}</strong></p>
                    @endif

                    <p class="yorum-metin">{{ $yorum->body }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- ── YORUM YAZMA ──────────────────────────────────────────────── --}}
    @if (! $musteriGirisli)
        <p class="ipucu">
            Yorum yazmak için <a href="{{ route('vitrin.giris') }}">giriş yapın</a>.
            Yalnızca ürünü satın alıp teslim almış müşteriler yorum yazabilir.
        </p>

    @elseif ($yorumEngeli !== null)
        {{--
            ⚠️ SEBEP GÖSTERİLİYOR, form gizlenip susulmuyor. "Satın
            almadınız", "zaten yazdınız" ve "e-postanızı doğrulayın"
            farklı durumlar ve müşterinin yapması gereken de farklı; tek
            bir "yazamazsınız" mesajı üçünü de çıkmaza çevirirdi.
        --}}
        <p class="ipucu">{{ $yorumEngeli }}</p>

    @else
        {{--
            ⚠️ Formun `action`'ı ADLI rotayı kullanıyor. 4.6V'de isimsiz
            POST rotası yüzünden müşteri 405 almıştı; testi bu sayfayı
            render edip `action`'ı OKUYARAK ölçüyor.
        --}}
        <form method="post" action="{{ route('vitrin.urun.yorum', ['slug' => $urun->slug]) }}" class="yorum-form">
            @csrf

            <label for="yorum-puan">Puanınız</label>
            <select id="yorum-puan" name="rating" required>
                @foreach ([5, 4, 3, 2, 1] as $puan)
                    <option value="{{ $puan }}" @selected(old('rating') == $puan)>{{ $puan }} — {{ str_repeat('★', $puan) }}</option>
                @endforeach
            </select>

            <label for="yorum-baslik">Başlık <span class="ipucu">(isteğe bağlı)</span></label>
            <input id="yorum-baslik" type="text" name="title" maxlength="120" value="{{ old('title') }}">

            <label for="yorum-metin">Yorumunuz</label>
            <textarea id="yorum-metin" name="body" rows="4" minlength="3" maxlength="2000" required>{{ old('body') }}</textarea>

            {{--
                ⚠️ "Onay bekleyecek" ÖNCEDEN söyleniyor. Sonradan
                söylenseydi müşteri yorumunu vitrinde göremeyip
                kaybolduğunu sanır, ikinci kez yazmayı dener ve "zaten
                yorum yazdınız" uyarısı alırdı.
            --}}
            <p class="ipucu">Yorumunuz marka tarafından onaylandıktan sonra yayınlanır.</p>

            <button type="submit">Yorumu gönder</button>
        </form>
    @endif
</section>
