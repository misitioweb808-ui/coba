<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const form = useForm({
  usuario: '',
  clave: ''
})

const errors = ref({
  usuario: '',
  clave: ''
})

const showPassword = ref(false)

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}

const submit = () => {
  errors.value.usuario = ''
  errors.value.clave = ''

  if (!form.usuario) {
    errors.value.usuario = 'El campo usuario es obligatorio.'
  }

  if (!form.clave) {
    errors.value.clave = 'El campo contraseña es obligatorio.'
  }

  if (errors.value.usuario || errors.value.clave) {
    return
  }

  form.post('/admin/login')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-900 text-white">

    <form @submit.prevent="submit" class="bg-gray-800 p-8 rounded shadow-md w-full max-w-md">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-semibold mb-6"></h1>
            <h1 class="text-xl font-semibold mb-6">Iniciar sesión</h1>
        </div>


      <div v-if="errors.usuario || errors.clave" class="mb-4 text-red-400 text-sm">
        Llena los campos correctamente.
      </div>

      <div v-if="$page.props.errors.usuario" class="mb-4 text-red-400 text-sm">
        {{ $page.props.errors.usuario }}
      </div>

      <div class="mb-4">
        <label class="block mb-1">Usuario</label>
        <input
          v-model="form.usuario"
          type="text"
          placeholder="Ingresa tu usuario"
          class="w-full bg-gray-700 border border-gray-600 p-2 rounded text-white placeholder-gray-400"
        />
      </div>

      <div class="mb-4">
        <label class="block mb-1">Contraseña</label>
        <div class="relative">
          <input
            v-model="form.clave"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Ingresa tu contraseña"
            class="w-full bg-gray-700 border border-gray-600 p-2 pr-10 rounded text-white placeholder-gray-400"
          />
          <button
            type="button"
            @click="togglePasswordVisibility"
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors duration-200"
          >
            <svg
              v-if="showPassword"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464m1.414 1.414L8.464 8.464m5.656 5.656l1.415 1.415m-1.415-1.415l1.415 1.415M14.828 14.828L16.243 16.243"
              />
            </svg>
            <svg
              v-else
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
              />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
          </button>
        </div>
      </div>
      <div  class="mb-4 text-gray-400 text-sm">
        Acceso restringido solo para administradores
      </div>

      <button
        type="submit"
        class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition-colors duration-200 cursor-pointer"
      >
        Iniciar sesión
      </button>
    </form>
  </div>
</template>
