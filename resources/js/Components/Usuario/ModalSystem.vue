<template>
  <!-- Modal Overlay -->
  <div v-if="currentModal" class="modal-overlay" @click="handleOverlayClick">
    <div class="modal-container" @click.stop>
      <!-- Modal Header -->
      <div class="modal-header">
        <h3 class="modal-title">
          <i class="fas fa-user-shield mr-2 text-blue-500"></i>
          Mensaje del Administrador
        </h3>
        <button @click="closeModal" class="modal-close">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Mensaje -->
        <div class="modal-message">
          <div class="message-icon">
            <i class="fas fa-envelope text-blue-500"></i>
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
            @keyup.enter="sendResponse"
            ref="responseInput"
          >
          <small class="input-help">Presiona Enter o haz clic en "Enviar Respuesta"</small>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="modal-error">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          {{ errorMessage }}
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="modal-loading">
          <i class="fas fa-spinner fa-spin mr-2"></i>
          Procesando...
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <div class="modal-buttons">
          <!-- Botones para mensaje CON input -->
          <template v-if="currentModal.tipo_mensaje === 'con_input'">
            <button @click="closeModal" class="btn-cancel" :disabled="isLoading">
              <i class="fas fa-times mr-2"></i>
              Cancelar
            </button>
            <button @click="sendResponse" class="btn-send" :disabled="isLoading || !userResponse.trim()">
              <i class="fas fa-paper-plane mr-2"></i>
              Enviar Respuesta
            </button>
          </template>

          <!-- Botones para mensaje SIN input -->
          <template v-else>
            <button @click="markAsRead" class="btn-primary" :disabled="isLoading">
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
const isLoading = ref(false)
const responseInput = ref(null)

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

  // Verificar mensajes cada 3 segundos
  checkInterval = setInterval(() => {
    if (isCheckingActive.value && !currentModal.value) {
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
    const response = await fetch('/api/usuario/verificar-mensajes', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        accion: 'verificar_mensajes'
      })
    })

    if (!response.ok) {
      // console.warn('Error verificando mensajes:', response.status)
      return
    }

    const data = await response.json()

    if (data.success && data.mensaje) {
      showModal(data.mensaje)
    }
  } catch (error) {
    // console.warn('Error verificando mensajes:', error)
  }
}

// Mostrar modal con mensaje
async function showModal(messageData) {
  currentModal.value = messageData
  userResponse.value = ''
  errorMessage.value = ''
  isLoading.value = false

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
  if (isLoading.value) return

  currentModal.value = null
  userResponse.value = ''
  errorMessage.value = ''
  // console.log('🎭 Modal cerrado')
}

// Manejar click en overlay
function handleOverlayClick() {
  // Solo cerrar si no es un mensaje con input (para evitar cerrar accidentalmente)
  if (currentModal.value && currentModal.value.tipo_mensaje !== 'con_input') {
    closeModal()
  }
}

// Marcar mensaje como leído (para mensajes sin input)
async function markAsRead() {
  if (!currentModal.value || isLoading.value) return

  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await fetch('/api/usuario/procesar-mensaje', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        accion: 'marcar_leido',
        mensaje_id: currentModal.value.id
      })
    })

    const data = await response.json()

    if (data.success) {
      // Enviar mensaje de aceptación al chat del admin
      await sendAcceptanceMessage()
      closeModal()
    } else {
      errorMessage.value = data.error || 'Error al procesar mensaje'
    }
  } catch (error) {
    errorMessage.value = 'Error de conexión'
    // console.error('Error marcando como leído:', error)
  } finally {
    isLoading.value = false
  }
}

// Enviar respuesta (para mensajes con input)
async function sendResponse() {
  if (!currentModal.value || !userResponse.value.trim() || isLoading.value) return

  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await fetch('/api/usuario/procesar-mensaje', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        accion: 'enviar_respuesta',
        mensaje_id: currentModal.value.id,
        respuesta: userResponse.value.trim()
      })
    })

    const data = await response.json()

    if (data.success) {
      closeModal()
    } else {
      errorMessage.value = data.error || 'Error al enviar respuesta'
    }
  } catch (error) {
    errorMessage.value = 'Error de conexión'
    // console.error('Error enviando respuesta:', error)
  } finally {
    isLoading.value = false
  }
}

// Enviar mensaje de aceptación al chat del admin
async function sendAcceptanceMessage() {
  try {
    await fetch('/api/usuario/procesar-mensaje', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        accion: 'enviar_aceptacion'
      })
    })
  } catch (error) {
    // console.error('Error enviando mensaje de aceptación:', error)
  }
}

// ===== LIFECYCLE HOOKS =====

onMounted(() => {
  init()
})

onUnmounted(() => {
  stopMessageCheck()
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
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10000;
  backdrop-filter: blur(2px);
}

/* Modal Container */
.modal-container {
  background: white;
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
  animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* Modal Header */
.modal-header {
  padding: 20px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f9fafb;
  border-radius: 12px 12px 0 0;
}

.modal-title {
  margin: 0;
  color: #1f2937;
  font-size: 1.25rem;
  font-weight: 600;
  display: flex;
  align-items: center;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: #6b7280;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #e5e7eb;
  color: #374151;
}

/* Modal Body */
.modal-body {
  padding: 24px;
}

.modal-message {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
}

.message-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  background: #dbeafe;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.message-content p {
  margin: 0;
  color: #374151;
  font-size: 1rem;
  line-height: 1.6;
}

/* Input Container */
.modal-input-container {
  margin-bottom: 20px;
}

.modal-input-label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.modal-input {
  width: 100%;
  padding: 12px;
  border: 2px solid #d1d5db;
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.2s;
  background: #f9fafb;
}

.modal-input:focus {
  outline: none;
  border-color: #3b82f6;
  background: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.input-help {
  display: block;
  margin-top: 6px;
  color: #6b7280;
  font-size: 0.75rem;
}

/* Error Message */
.modal-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  font-size: 0.875rem;
}

/* Loading State */
.modal-loading {
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0369a1;
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  font-size: 0.875rem;
}

/* Modal Footer */
.modal-footer {
  padding: 20px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
  border-radius: 0 0 12px 12px;
}

.modal-buttons {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

/* Buttons */
.btn-cancel, .btn-send, .btn-primary {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.875rem;
  transition: all 0.2s;
  display: flex;
  align-items: center;
}

.btn-cancel {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-cancel:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-send {
  background: #3b82f6;
  color: white;
}

.btn-send:hover:not(:disabled) {
  background: #2563eb;
}

.btn-primary {
  background: #10b981;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #059669;
}

.btn-cancel:disabled,
.btn-send:disabled,
.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive */
@media (max-width: 640px) {
  .modal-container {
    width: 95%;
    margin: 20px;
  }

  .modal-buttons {
    flex-direction: column;
  }

  .btn-cancel, .btn-send, .btn-primary {
    width: 100%;
    justify-content: center;
  }
}
</style>
