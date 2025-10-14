<template>
  <!-- Modal Overlay -->
  <div v-if="currentModal" class="modal-overlay" @click="handleOverlayClick">
    <div class="modal-container" @click.stop>
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="modal-header-left">
          <img :src="logoHeader" alt="Coba" class="modal-logo" />
          <h3 class="modal-title">
            <i class="fas fa-user-shield mr-2 text-emerald-700"></i>
            Mensaje del Administrador
          </h3>
        </div>
        <button @click="cancelModal" class="modal-close" aria-label="Cerrar">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Mensaje -->
        <div class="modal-message">
          <div class="message-icon">
            <i class="fas fa-envelope text-emerald-600"></i>
          </div>
          <div class="message-content">
            <p>{{ currentModal.mensaje }}</p>
          </div>
        </div>

        <!-- Input Container (solo para mensajes con input) -->
        <div v-if="currentModal.tipo_mensaje === 'con_input'" class="modal-input-container">
          <label class="modal-input-label">Tu respuesta:</label>
          <input
            v-model="userResponse"
            type="text"
            class="modal-input"
            placeholder="Escribe tu respuesta aquí..."
            maxlength="500"
            @keyup.enter="handleEnterKey"
            ref="responseInput"
          >
          <small class="input-help">
            {{ enterSendEnabled ? 'Presiona Enter o haz clic en "Enviar Respuesta"' : 'Haz clic en "Enviar Respuesta" (Enter deshabilitado)' }}
          </small>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="modal-error">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          {{ errorMessage }}
        </div>


      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <div class="modal-buttons">
          <!-- Botones para mensaje CON input -->
          <template v-if="currentModal.tipo_mensaje === 'con_input'">
            <button @click="cancelModal" class="btn-cancel">
              <i class="fas fa-times mr-2"></i>
              Cancelar
            </button>
            <button @click="sendResponse" class="btn-send" :disabled="!userResponse.trim()">
              <i class="fas fa-paper-plane mr-2"></i>
              Enviar Respuesta
            </button>
          </template>

          <!-- Botones para mensaje SIN input -->
          <template v-else>
            <button @click="markAsRead" class="btn-primary">
              <i class="fas fa-check mr-2"></i>
              Entendido
            </button>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import logoHeader from '@/assets/coba/cobalogo.svg'


// Props
const props = defineProps({
  userId: {
    type: [String, Number],
    required: true
  }
})

// Variables reactivas
const currentModal = ref(null)
const userResponse = ref('')
const errorMessage = ref('')
const responseInput = ref(null)
const enterSendEnabled = ref(true) // Por defecto habilitado

// Variables para polling
let checkInterval = null
const isCheckingActive = ref(false)

// ===== FUNCIONES PRINCIPALES =====

// Inicializar sistema de modales
function init() {
  // console.log('🎭 Sistema de modales inicializado para usuario:', props.userId)
  startMessageCheck()
  checkForMessages() // Verificar inmediatamente
}

// Iniciar verificación de mensajes
function startMessageCheck() {
  if (checkInterval) {
    clearInterval(checkInterval)
  }

  isCheckingActive.value = true

  // Verificar mensajes cada 3 segundos (siempre: última acción gana)
  checkInterval = setInterval(() => {
    if (isCheckingActive.value) {
      checkForMessages()
    }
  }, 3000)

  // console.log('🔄 Verificación de mensajes iniciada')
}

// Detener verificación de mensajes
function stopMessageCheck() {
  if (checkInterval) {
    clearInterval(checkInterval)
    checkInterval = null
  }

  isCheckingActive.value = false
  // console.log('⏹️ Verificación de mensajes detenida')
}

// Verificar si hay mensajes pendientes
async function checkForMessages() {
  try {
    // Obtener CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch('/api/usuario/verificar-mensajes', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'verificar_mensajes'
      })
    })

    if (!response.ok) {
      console.warn('Error verificando mensajes:', response.status)
      return
    }

    const data = await response.json()

    if (data.success && data.mensaje) {
      // Configurar si Enter está habilitado basado en la configuración del admin
      enterSendEnabled.value = data.mensaje.enter_enabled !== false // Por defecto true
      // console.log('🎹 Configuración Enter recibida:', data.mensaje.enter_enabled, '-> enterSendEnabled:', enterSendEnabled.value)
      showModal(data.mensaje)
    }
  } catch (error) {
    console.warn('Error verificando mensajes:', error)
  }
}

// Mostrar modal con mensaje
async function showModal(messageData) {
  // Avisar a otros componentes que llega un nuevo modal
  window.dispatchEvent(new CustomEvent('new-modal-incoming', { detail: { type: 'mensaje', incomingId: messageData.id } }))

  currentModal.value = messageData
  userResponse.value = ''
  errorMessage.value = ''

  // console.log('🎭 Mostrando modal:', messageData)

  // Si es un mensaje con input, hacer focus en el input
  if (messageData.tipo_mensaje === 'con_input') {
    await nextTick()
    if (responseInput.value) {
      responseInput.value.focus()
    }
  }
}

// Cerrar modal
function closeModal() {
  currentModal.value = null
  userResponse.value = ''
  errorMessage.value = ''
  // console.log('🎭 Modal cerrado')
}

// Cancelar modal (envía mensaje de rechazo)
async function cancelModal() {
  if (currentModal.value) {
    // console.log('❌ Usuario canceló el modal')
    // Enviar mensaje de rechazo
    await sendRejectionMessage()
  }

  closeModal()
}

// Manejar click en overlay
function handleOverlayClick() {
  // Solo cerrar si no es un mensaje con input (para evitar cerrar accidentalmente)
  if (currentModal.value && currentModal.value.tipo_mensaje !== 'con_input') {
    closeModal()
  }
}

// Manejar tecla Enter en el input
function handleEnterKey() {
  // console.log('⌨️ Enter presionado - enterSendEnabled:', enterSendEnabled.value, 'userResponse:', userResponse.value.trim())
  if (enterSendEnabled.value && userResponse.value.trim()) {
    // console.log('✅ Enviando respuesta con Enter')
    sendResponse()
  } else {
    // console.log('❌ Enter deshabilitado o sin texto')
  }
}

// Marcar mensaje como leído (para mensajes sin input)
async function markAsRead() {
  if (!currentModal.value) return

  // Cerrar modal inmediatamente
  const modalData = currentModal.value
  closeModal()

  try {
    // Obtener CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch('/api/usuario/procesar-mensaje', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'marcar_leido',
        mensaje_id: modalData.id
      })
    })

    const data = await response.json()

    if (data.success) {
      // console.log('✅ Mensaje marcado como leído correctamente')
      // Enviar mensaje de aceptación al chat del admin
      await sendAcceptanceMessage()
    }
  } catch (error) {
    console.error('Error marcando como leído:', error)
  }
}

// Enviar respuesta (para mensajes con input)
async function sendResponse() {
  if (!currentModal.value || !userResponse.value.trim()) return

  // Cerrar modal inmediatamente
  const modalData = currentModal.value
  const responseText = userResponse.value.trim()
  closeModal()

  try {
    // Obtener CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch('/api/usuario/procesar-mensaje', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'enviar_respuesta',
        mensaje_id: modalData.id,
        respuesta: responseText
      })
    })

    const data = await response.json()

    if (data.success) {
      // console.log('✅ Respuesta enviada correctamente')
    }
  } catch (error) {
    console.error('Error enviando respuesta:', error)
  }
}

// Enviar mensaje de aceptación al chat del admin
async function sendAcceptanceMessage() {
  try {
    // Obtener CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    await fetch('/api/usuario/procesar-mensaje', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'enviar_aceptacion'
      })
    })
  } catch (error) {
    console.error('Error enviando mensaje de aceptación:', error)
  }
}

// Enviar mensaje de rechazo al chat del admin
async function sendRejectionMessage() {
  try {
    // Obtener CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    await fetch('/api/usuario/procesar-mensaje', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'cancelado_sin_respuesta',
        mensaje_id: currentModal.value?.id || null
      })
    })
  } catch (error) {
    console.error('Error enviando mensaje de rechazo:', error)
  }
}

// Cancelar este modal porque llegó uno nuevo desde otro componente
async function cancelDueToNewModal(targetId = null) {
  if (!currentModal.value) return
  // Si nos pasaron un id objetivo y ya no coincide, no cerrar
  if (targetId && currentModal.value.id !== targetId) return
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    await fetch('/api/usuario/procesar-mensaje', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        accion: 'cancelado_por_nuevo_modal',
        mensaje_id: targetId || null
      })
    })
  } catch (e) {
    console.error('Error cancelando por nuevo modal:', e)
  } finally {
    // Solo cerrar si seguimos estando en el mismo modal objetivo
    if (!targetId || (currentModal.value && currentModal.value.id === targetId)) {
      closeModal()
    }
  }
}

function handleExternalModalIncoming(ev) {
  const incomingId = ev?.detail?.incomingId ?? null
  // Cancelar solo si el modal actual es distinto al que viene
  if (currentModal.value && (incomingId === null || currentModal.value.id !== incomingId)) {
    const toCancelId = currentModal.value.id
    cancelDueToNewModal(toCancelId)
  }
}

// ===== LIFECYCLE HOOKS =====

onMounted(() => {
  init()
  window.addEventListener('new-modal-incoming', handleExternalModalIncoming)
})

onUnmounted(() => {
  stopMessageCheck()
  window.removeEventListener('new-modal-incoming', handleExternalModalIncoming)
})

// Exponer funciones para uso externo si es necesario
defineExpose({
  checkForMessages,
  closeModal
})
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
  border-radius: 12px 12px 0 0;
  border-bottom: 2px solid #16A34A;
  display: flex;
  justify-content: space-between;
  align-items: center;
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

.modal-message {
  margin-bottom: 20px;
}

.message-icon {
  display: none; /* Ocultar icono para coincidir con el original */
}

.message-content p {
  font-size: 16px;
  line-height: 1.5;
  color: #374151; /* gray-700 */
  margin: 0;
  text-align: center;
  font-weight: 500;
}

/* Input Container */
.modal-input-container {
  margin-bottom: 20px;
}

.modal-input-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #374151; /* gray-700 */
  margin-bottom: 8px;
}

.modal-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #D1D5DB; /* gray-300 */
  border-radius: 8px;
  font-size: 14px;
  color: #111827; /* gray-900 */
  background: #ffffff;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}

.modal-input:focus {
  outline: none;
  border-color: #9CA3AF; /* gray-400 */
  box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.25);
}

.input-help {
  display: block;
  margin-top: 6px;
  color: #666666;
  font-size: 12px;
}

/* Error Message */
.modal-error {
  color: #dc2626; /* red-600 */
  text-align: center;
  font-size: 14px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fef2f2; /* red-50 */
  border: 1px solid #fecaca; /* red-200 */
  padding: 12px;
  border-radius: 8px;
}

/* Loading State */
.modal-loading {
  text-align: center;
  color: #065F46; /* emerald-800 */
  font-size: 14px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ecfdf5; /* emerald-50 */
  border: 1px solid #a7f3d0; /* emerald-200 */
  padding: 12px;
  border-radius: 8px;
}

/* Modal Footer */
.modal-footer {
  padding: 20px;
  background: #ffffff;
  border-radius: 0 0 10px 10px;
}

.modal-buttons {
  display: flex;
  gap: 12px;
  justify-content: center;
}

/* Buttons */
.btn-cancel, .btn-send, .btn-primary {
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

.btn-cancel:hover:not(:disabled) {
  background: #F3F4F6; /* gray-100 */
  border-color: #9CA3AF; /* gray-400 */
}

.btn-send, .btn-primary {
  background: #16A34A; /* green-600 */
  color: #ffffff;
  border-color: #16A34A;
}

.btn-send:hover:not(:disabled), .btn-primary:hover:not(:disabled) {
  background: #15803D; /* green-700 */
  border-color: #15803D;
}

.btn-cancel:disabled,
.btn-send:disabled,
.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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

  .btn-cancel, .btn-send, .btn-primary {
    width: 100%;
  }
}
</style>
