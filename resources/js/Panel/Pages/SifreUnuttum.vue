<script setup>
/*
 | Personel şifre sıfırlama isteği. (4.6V)
 |
 | ⚠️ Giriş ekranıyla aynı sade düzen: menü ve çıkış düğmesi burada da
 | anlamsız — kullanıcı henüz giriş yapamıyor.
 */
import { useForm, Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const form = useForm({ email: '' })

const bildirim = computed(() => usePage().props.bildirim ?? {})

function gonder() {
  form.post('/yonetim/sifremi-unuttum')
}
</script>

<template>
  <Head title="Şifremi unuttum" />

  <div class="min-h-screen grid place-items-center bg-stone-100 text-stone-900">
    <form class="w-full max-w-sm bg-white rounded-xl border border-stone-200 p-6" @submit.prevent="gonder">
      <h1 class="text-xl font-bold mb-1">Şifremi unuttum</h1>
      <p class="text-sm text-stone-500 mb-5">
        Personel hesabınızın e-posta adresini girin; sıfırlama bağlantısını gönderelim.
      </p>

      <!-- ⚠️ Başarı mesajı hesap OLSA DA OLMASA DA aynı: hangi
           e-postaların bu markada çalıştığı sızdırılmamalı. -->
      <p v-if="bildirim.mesaj" class="mb-4 rounded-lg bg-green-100 border border-green-300 px-3 py-2 text-sm">
        {{ bildirim.mesaj }}
      </p>

      <label class="block text-sm mb-4">
        E-posta
        <input
          v-model="form.email"
          type="email"
          autocomplete="username"
          class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"
          required
        >
      </label>

      <p v-if="form.errors.email" class="mb-3 text-sm text-red-700">{{ form.errors.email }}</p>

      <button
        type="submit"
        class="w-full rounded-lg bg-orange-600 text-white py-2 font-semibold disabled:opacity-60"
        :disabled="form.processing"
      >{{ form.processing ? 'Gönderiliyor…' : 'Sıfırlama bağlantısı gönder' }}</button>

      <p class="mt-4 text-sm text-center">
        <Link href="/yonetim/giris" class="text-stone-600 hover:text-orange-600">Giriş ekranına dön</Link>
      </p>
    </form>
  </div>
</template>
