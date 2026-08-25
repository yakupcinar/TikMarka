{{--
    VARYANT SEÇİCİSİ — ORTAK PARÇA (4.6A · 4.6A.1)

    ⚠️ ÖNCE YALNIZCA `sade` DÜZENİNDEYDİ ve bu ölçülerek bulundu: `vitrinli`
    düzenini kullanan marka (geliştirme markası dâhil) hâlâ 4.6A'nın
    KALDIRMAYI AMAÇLADIĞI düz açılır listeyi görüyordu — "kirmizi · m —
    249,90 TL". Yani blok yarım uygulanmıştı ve testler bunu göremiyordu,
    çünkü hepsi varsayılan düzende koşuyordu.

    ⚠️ Tema bir AYAR (4-K5): hangi düzenin kullanıldığını marka belirliyor.
    Bu yüzden ürün sayfasına eklenen her şey İKİ DÜZENİ de kapsamalı —
    4.6C ve 4.6D'de aynı ders için ortak parça kullanılmıştı.
--}}
                    {{--
                        VARYANT SEÇİCİSİ (4.6A).

                        ⚠️ Önce TEK DÜZ AÇILIR LİSTE vardı ve tüm varyantları
                        "Kırmızı · M — 100 TL" diye basıyordu: müşteri iki
                        ekseni birden okumak zorundaydı ve stokta olmayan
                        birleşimler de seçilebiliyordu.

                        ⚠️ Seçilen varyant GİZLİ girdiye yazılıyor; sunucu
                        yalnızca `variant_uuid` görüyor, yani seçici bozulsa
                        bile sepete giden veri aynı biçimde doğrulanıyor.
                    --}}
                    @if ($secici['eksenler'] !== [])
                        <div class="varyant-secici"
                             data-secici
                             data-varyantlar='@json($secici['varyantlar'])'>

                            @foreach ($secici['eksenler'] as $eksen)
                                <div class="eksen" data-eksen="{{ $eksen['slug'] }}">
                                    <span class="eksen-ad">{{ $eksen['ad'] }}</span>

                                    {{--
                                        ⚠️ Eşiği aşan eksen AÇILIR LİSTEYE düşüyor
                                        ({{ $listeEsigi }} değerden fazlası): 30 bedenlik bir
                                        eksen kutucuk olarak basılsaydı sayfa okunamazdı.
                                    --}}
                                    @if ($eksen['listeMi'])
                                        <select data-deger-liste>
                                            <option value="">— seçin —</option>
                                            @foreach ($eksen['degerler'] as $deger)
                                                <option value="{{ $deger['slug'] }}">{{ $deger['ad'] }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="kutucuklar">
                                            @foreach ($eksen['degerler'] as $deger)
                                                {{--
                                                    ⚠️ `type="button"`: form içindeyiz ve varsayılan
                                                    `submit` olurdu — kutucuğa basan müşteri sepete
                                                    eksik veriyle istek atardı.
                                                --}}
                                                <button type="button" class="kutucuk" data-deger="{{ $deger['slug'] }}">
                                                    @if ($deger['swatch'])
                                                        <span class="renk" style="background: {{ $deger['swatch'] }}"></span>
                                                    @endif
                                                    {{ $deger['ad'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            <p class="ipucu" data-secici-uyari hidden></p>
                        </div>

                        <input type="hidden" name="variant_uuid" value="" data-varyant-uuid required>
                    @else
                        {{-- ⚠️ EKSENSİZ ÜRÜN: seçilecek bir şey yok, gizli girdi yeter. --}}
                        <input type="hidden" name="variant_uuid" value="{{ $secici['tekVaryant'] ?? $urun->variants->first()->uuid }}">
                    @endif
