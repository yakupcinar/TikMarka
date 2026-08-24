<x-mail-layout :markaAdi="$markaAdi" :iletisim="$iletisim" :telefon="$telefon">
  <p style="margin:0 0 16px">Hoş geldiniz! Hesabınız oluşturuldu.</p>

  <p style="margin:0 0 20px;font-size:14px">
    Adresin size ait olduğunu doğrulamak için:
  </p>

  <p style="margin:0 0 20px">
    <a href="{{ $adres }}" style="background:#18181b;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-size:14px">E-postamı doğrula</a>
  </p>

  {{--
    ⚠️ "Doğrulamadan da alışveriş yapabilirsiniz" cümlesi BİLEREK var.
    Doğrulama yumuşak bir kapı (4.6W): ödeme engellenmiyor. Bu cümle
    olmasaydı müşteri postayı görmediğinde alışverişin durduğunu sanıp
    sepetini bırakırdı.
  --}}
  <p style="margin:0 0 12px;font-size:13px;color:#71717a">
    Bağlantı {{ $dakika }} dakika geçerli. Doğrulamadan da alışveriş
    yapabilirsiniz; doğrulama yalnızca hesabınızı güvenceye alır.
  </p>

  {{--
    ⚠️ Şifre sıfırlamadaki "yok sayın" cümlesinin karşılığı: hesabı
    başkası da SİZİN adresinizle açmış olabilir. O durumda tıklamak
    yanlış hesabı doğrulamak olurdu.
  --}}
  <p style="margin:0;font-size:13px;color:#71717a">
    Bu hesabı siz açmadıysanız bu postayı yok sayın.
  </p>
</x-mail-layout>
