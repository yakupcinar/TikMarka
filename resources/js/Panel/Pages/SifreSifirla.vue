<script setup>
/*
 | Personel yeni şifre ekranı. (4.6V)
 */
import { useForm, Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({ token: String, email: String })

const bildirim = computed(() => usePage().props.bildirim ?? {})

/*
 | ⚠️ Jeton ve e-posta FORMA gömülüyor: broker ikisini de istekten
 | okuyor. Yalnızca adreste kalsalardı POST gövdesinde bulunmaz ve
 | sıfırlama her seferinde "geçersiz bağlantı" derdi.
 */
const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

function gonder() {
  form.post('/yonetim/sifre-sifirla', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head title="Yeni şifre" />

  <div class="min-h-screen grid place-items-center bg-yuzey-2 text-metin">
    <form class="w-full max-w-sm bg-yuzey rounded-xl border border-kenar p-6 shadow-kart" @submit.prevent="gonder">
      <h1 class="text-xl font-bold mb-5">Yeni şifre belirleyin</h1>

      <p v-if="bildirim.hata" class="mb-4 rounded-lg bg-tehlike-zemin border border-tehlike-kenar px-3 py-2 text-sm">
        {{ bildirim.hata }}
      </p>

      <!--
        ⚠️ E-posta FORM ALANI DEĞİL, düz metin. Önce `readonly` bir kutuydu
        ve doldurulamayan bir alan gibi görünüyordu. Tek işi "hangi hesabın
        şifresi değişiyor"u göstermek; değer `form.email` üzerinden POST
        gövdesinde zaten gidiyor. Jeton BU adrese üretildi, değiştirilirse
        eşleşmez.
      -->
      <p v-if="form.email" class="mb-3 text-sm text-metin-2">
        Hesap: <strong class="text-metin">{{ form.email }}</strong>
      </p>

      <label class="block text-sm mb-3">
        Yeni şifre <span class="text-soluk">(en az 8 karakter)</span>
        <input
          v-model="form.password"
          type="password"
          autocomplete="new-password"
          class="mt-1 w-full rounded-lg border border-kenar-kontrol px-3 py-2"
          required
          autofocus
        >
      </label>

      <label class="block text-sm mb-4">
        Yeni şifre (tekrar)
        <input
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          class="mt-1 w-full rounded-lg border border-kenar-kontrol px-3 py-2"
          required
        >
      </label>

      <p v-for="(mesaj, alan) in form.errors" :key="alan" class="mb-3 text-sm text-tehlike">{{ mesaj }}</p>

      <button
        type="submit"
        class="w-full rounded-lg bg-vurgu text-white py-2 font-semibold disabled:opacity-60"
        :disabled="form.processing"
      >{{ form.processing ? 'Güncelleniyor…' : 'Şifreyi güncelle' }}</button>
    </form>
  </div>
</template>
