<script setup>
/*
 | Panel giriş ekranı. (4C)
 |
 | ⚠️ Düzeni KULLANMIYOR: menü ve çıkış düğmesi henüz anlamsız.
 */
import { useForm, Head, Link } from '@inertiajs/vue3'

const form = useForm({ email: '', password: '' })

function gonder() {
  /*
   | ⚠️ Parola HATADAN SONRA temizleniyor. Formda kalsaydı ortak bir
   | bilgisayarda sonraki kişi tarayıcı geçmişinden geri gelip dolu
   | bir parola alanı bulabilirdi.
   */
  form.post('/yonetim/giris', { onFinish: () => form.reset('password') })
}
</script>

<template>
  <Head title="Giriş" />

  <div class="min-h-screen grid place-items-center bg-yuzey-2 text-metin">
    <form class="w-full max-w-sm bg-yuzey rounded-xl border border-kenar p-6" @submit.prevent="gonder">
      <h1 class="text-xl font-bold mb-1">Panel girişi</h1>
      <p class="text-sm text-soluk mb-5">Mağazanızı yönetmek için giriş yapın.</p>

      <label class="block text-sm mb-3">
        E-posta
        <input
          v-model="form.email"
          type="email"
          autocomplete="username"
          class="mt-1 w-full rounded-lg border border-kenar-kontrol px-3 py-2"
          required
        >
      </label>

      <label class="block text-sm mb-4">
        Parola
        <input
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          class="mt-1 w-full rounded-lg border border-kenar-kontrol px-3 py-2"
          required
        >
      </label>

      <!-- ⚠️ Hata mesajı SUNUCUDAN geliyor: "kullanıcı yok" ile "parola
           yanlış" ayrımı yapılmıyor, yoksa hangi e-postaların panele
           erişimi olduğu tek tek öğrenilebilirdi. -->
      <p v-if="form.errors.email" class="mb-3 text-sm text-tehlike">{{ form.errors.email }}</p>

      <button
        type="submit"
        class="w-full rounded-lg bg-vurgu text-white py-2 font-semibold disabled:opacity-60"
        :disabled="form.processing"
      >{{ form.processing ? 'Giriş yapılıyor…' : 'Giriş yap' }}</button>

      <!-- ⚠️ Şifresini unutan personelin ÖNCEDEN hiçbir yolu yoktu:
           tek çözüm geliştiricinin elle bcrypt hash yazmasıydı. -->
      <p class="mt-4 text-sm text-center">
        <Link href="/yonetim/sifremi-unuttum" class="text-metin-2 hover:text-vurgu-metin">Şifremi unuttum</Link>
      </p>
    </form>
  </div>
</template>
