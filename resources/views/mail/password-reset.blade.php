<x-mail-layout :markaAdi="$markaAdi" :iletisim="$iletisim" :telefon="$telefon">
  <p style="margin:0 0 16px">
    @if ($panel)
      Panel hesabınız için şifre sıfırlama talebi aldık.
    @else
      Hesabınız için şifre sıfırlama talebi aldık.
    @endif
  </p>

  <p style="margin:0 0 20px;font-size:14px">Yeni şifrenizi belirlemek için:</p>

  <p style="margin:0 0 20px">
    <a href="{{ $adres }}" style="background:#18181b;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-size:14px">Şifremi sıfırla</a>
  </p>

  {{--
    ⚠️ "Siz yapmadıysanız YOK SAYIN" cümlesi ŞART. Sıfırlama talebini
    saldırgan da başlatabilir (form herkese açık); kurbanın gelen
    kutusunda beliren postanın tek başına bir tehlike OLMADIĞINI bilmesi
    gerekiyor — aksi hâlde paniğe kapılıp bağlantıya tıklar.
  --}}
  <p style="margin:0;font-size:13px;color:#71717a">
    Bağlantı {{ $dakika }} dakika geçerli. Bu talebi siz yapmadıysanız bu postayı
    yok sayabilirsiniz — şifreniz değişmez.
  </p>
</x-mail-layout>
