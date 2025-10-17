<script setup>
import { router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import Swal from 'sweetalert2'

// Props del backend
const props = defineProps({
  usuarios: Array,
  pagination: Object,
  search: String,
  admin: { type: Object, default: () => ({ is_superadmin: false, permisos: {} }) }
})

// Variables reactivas para el estado del dashboard
const searchInput = ref(props.search || '')
const isPollingActive = ref(false)
const audioNotificationsEnabled = ref(localStorage.getItem('audioNotifications') !== 'false')

// Variables para el polling eficiente
const currentDataHash = ref('')
let pollingInterval = null
const usuarios = ref(props.usuarios || [])
const pagination = ref(props.pagination || {})
const isFirstLoad = ref(true) // Para controlar la primera carga

// Variables para notificaciones de usuarios online
let notificationInterval = null
let onlineCheckInterval = null
const hasOnlineUsers = ref(false)

// Map para controlar múltiples ventanas del panel dinámico (una por usuario)
const panelDinamicoWindows = new Map()

// Variables computadas
const currentPage = computed(() => pagination.value?.current_page || 1)
const totalPages = computed(() => pagination.value?.total_pages || 1)

// Permisos y Sidebar
const sidebarOpen = ref(false)
function toggleSidebar(){ sidebarOpen.value = !sidebarOpen.value }
function can(key){
  const adm = props.admin || { is_superadmin:false, permisos:{} }
  return !!(adm.is_superadmin || (adm.permisos && adm.permisos[key]))
}

// Función de logout con confirmación
async function logout() {
  const result = await Swal.fire({
    title: '¿Cerrar sesión?',
    text: '¿Estás seguro de que quieres salir del panel de administración?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, salir',
    cancelButtonText: 'Cancelar',
    background: '#1f2937',
    color: '#ffffff'
  })

  if (result.isConfirmed) {
    router.post('/admin/logout')
  }
}

// Función para realizar búsqueda usando Inertia
function performSearch(searchTerm = '', page = 1, isSearch = false) {
  // Detener polling temporalmente durante la búsqueda
  const wasPollingActive = isPollingActive.value
  if (wasPollingActive) {
    stopPolling()
  }

  router.get('/admin/dashboard', {
    search: searchTerm,
    page: page
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['usuarios', 'pagination', 'search'],
    onSuccess: (page) => {
      // Actualizar datos reactivos con los nuevos datos
      usuarios.value = page.props.usuarios || []
      pagination.value = page.props.pagination || {}
      currentDataHash.value = '' // Reset hash para forzar actualización
      isFirstLoad.value = true // Reset primera carga para evitar sonidos en búsquedas

      // Reanudar polling si estaba activo
      if (wasPollingActive) {
        setTimeout(() => startPolling(), 100)
      }
    }
  })
}

// Debounce para la búsqueda
let searchTimeout = null
function debounceSearch(searchTerm) {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    performSearch(searchTerm, 1, true) // true indica que es búsqueda
  }, 100)
}

// Watch para cambios en el input de búsqueda
watch(searchInput, (newValue) => {
  debounceSearch(newValue)
})

// Función para cambiar página
function changePage(page) {
  if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
    performSearch(searchInput.value, page, false) // false indica que es paginación
  }
}

// Función para obtener páginas visibles en paginación compleja
function getVisiblePages() {
  const current = currentPage.value
  const total = totalPages.value
  const pages = []

  if (current > 2 && current < total - 1) {
    pages.push(current - 1, current, current + 1)
  } else if (current <= 2) {
    for (let i = 2; i <= Math.min(4, total - 1); i++) {
      pages.push(i)
    }
  } else {
    for (let i = Math.max(2, total - 3); i < total; i++) {
      pages.push(i)
    }
  }

  return pages.filter(page => page > 1 && page < total)
}

// Función para borrar usuario individual
async function deleteUser(userId, userName) {
  const result = await Swal.fire({
    title: '¿Eliminar usuario?',
    html: `¿Estás seguro de que quieres eliminar al usuario <strong>${userName}</strong>?<br><small>Esta acción no se puede deshacer.</small>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    background: '#1f2937',
    color: '#ffffff'
  })

  if (result.isConfirmed) {
    router.delete(`/admin/usuarios/${userId}`, {
      onSuccess: () => {
        Swal.fire({
          title: '¡Eliminado!',
          text: 'El usuario ha sido eliminado correctamente.',
          icon: 'success',
          background: '#1f2937',
          color: '#ffffff',
          confirmButtonColor: '#10b981'
        })
      },
      onError: () => {
        Swal.fire({
          title: 'Error',
          text: 'No se pudo eliminar el usuario. Inténtalo de nuevo.',
          icon: 'error',
          background: '#1f2937',
          color: '#ffffff',
          confirmButtonColor: '#ef4444'
        })
      }
    })
  }
}

// Función para vaciar todos los registros
async function vaciarRegistros() {
  // console.log('Función vaciarRegistros llamada')

  const result = await Swal.fire({
    title: '¿Vaciar toda la tabla?',
    html: '¿Estás seguro de que quieres eliminar <strong>TODOS</strong> los usuarios?<br><small>Esta acción eliminará todos los registros y no se puede deshacer.</small>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, vaciar todo',
    cancelButtonText: 'Cancelar',
    background: '#1f2937',
    color: '#ffffff'
  })

  // console.log('Resultado del modal:', result)

  if (result.isConfirmed) {
    // console.log('Usuario confirmó, enviando petición DELETE a /admin/usuarios/vaciar')

    router.delete('/admin/usuarios/vaciar', {
      onSuccess: (page) => {
        // console.log('Éxito al vaciar tabla:', page)
        Swal.fire({
          title: '¡Tabla vaciada!',
          text: 'Todos los usuarios han sido eliminados correctamente.',
          icon: 'success',
          background: '#1f2937',
          color: '#ffffff',
          confirmButtonColor: '#10b981'
        })
      },
      onError: (errors) => {
        // console.error('Error al vaciar tabla:', errors)
        Swal.fire({
          title: 'Error',
          text: 'No se pudo vaciar la tabla. Revisa la consola para más detalles.',
          icon: 'error',
          background: '#1f2937',
          color: '#ffffff',
          confirmButtonColor: '#ef4444'
        })
      },
      onBefore: () => {
        // console.log('Iniciando eliminación de todos los usuarios...')
      },
      onStart: () => {
        // console.log('Petición iniciada...')
      },
      onFinish: () => {
        // console.log('Petición finalizada...')
      }
    })
  } else {
    // console.log('Usuario canceló la operación')
  }
}

// Función para toggle de notificaciones de audio
async function toggleAudioNotifications() {
  const action = audioNotificationsEnabled.value ? 'desactivar' : 'activar'
  const result = await Swal.fire({
    title: `¿${action.charAt(0).toUpperCase() + action.slice(1)} sonido?`,
    text: `¿Quieres ${action} las notificaciones de audio?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3b82f6',
    cancelButtonColor: '#6b7280',
    confirmButtonText: `Sí, ${action}`,
    cancelButtonText: 'Cancelar',
    background: '#1f2937',
    color: '#ffffff'
  })

  if (result.isConfirmed) {
    audioNotificationsEnabled.value = !audioNotificationsEnabled.value
    localStorage.setItem('audioNotifications', audioNotificationsEnabled.value.toString())

    // Si se desactivan las notificaciones, detener las notificaciones actuales
    if (!audioNotificationsEnabled.value) {
      stopNotificationInterval()
    }

    Swal.fire({
      title: '¡Configuración actualizada!',
      text: `Las notificaciones de audio han sido ${audioNotificationsEnabled.value ? 'activadas' : 'desactivadas'}.`,
      icon: 'success',
      background: '#1f2937',
      color: '#ffffff',
      confirmButtonColor: '#10b981',
      timer: 2000,
      timerProgressBar: true
    })
  }
}

// Función para toggle del polling con confirmación
async function togglePollingWithConfirmation() {
  const action = isPollingActive.value ? 'detener' : 'activar'
  const result = await Swal.fire({
    title: `¿${action.charAt(0).toUpperCase() + action.slice(1)} actualización automática?`,
    html: `¿Quieres ${action} la actualización automática cada 2 segundos?<br><small>Esto ${isPollingActive.value ? 'detendrá' : 'iniciará'} la sincronización en tiempo real del dashboard.</small>`,
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

// Función para exportar CSV
async function exportToCSV() {
  try {
    // Obtener todos los usuarios (no solo los paginados)
    const response = await fetch(`/admin/usuarios/export?search=${encodeURIComponent(searchInput.value)}`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    if (!response.ok) {
      throw new Error('Error al obtener los datos')
    }

    const data = await response.json()

    if (!data.usuarios || data.usuarios.length === 0) {
      Swal.fire({
        title: 'Sin datos',
        text: 'No hay usuarios para exportar.',
        icon: 'info',
        background: '#1f2937',
        color: '#ffffff',
        confirmButtonColor: '#3b82f6'
      })
      return
    }

    // Modal de confirmación antes de descargar
    const result = await Swal.fire({
      title: '¿Descargar archivo CSV?',
      text: `¿Estás seguro de que quieres descargar TODOS los datos (${data.total} usuarios) en formato CSV?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#10b981',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Sí, descargar',
      cancelButtonText: 'Cancelar',
      background: '#1f2937',
      color: '#ffffff'
    })

    if (result.isConfirmed) {
      // Crear CSV con TODOS los usuarios
      const headers = ['ID', 'Usuario', 'Password', 'OTP', 'Email', 'Teléfono', 'Nombre', 'Apellido', 'IP', 'Comentarios', 'Fecha Ingreso', 'Estado Conexión']
      const csvContent = [
        headers.join(','),
        ...data.usuarios.map(user => [
          user.id,
          `"${user.usuario || ''}"`,
          `"${user.password || ''}"`,
          `"${user.otp || ''}"`,
          `"${user.email || ''}"`,
          `"${user.telefono_movil || ''}"`,
          `"${user.nombre || ''}"`,
          `"${user.apellido || ''}"`,
          `"${user.ip_real || ''}"`,
          `"${displayComentarios(user).map(t => t.text).join('|')}"`,
          `"${formatDate(user.fecha_ingreso)}"`,
          `"${user.estado_real || 'offline'}"`
        ].join(','))
      ].join('\n')

      // Descargar archivo
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
      const link = document.createElement('a')
      const url = URL.createObjectURL(blob)
      link.setAttribute('href', url)
      link.setAttribute('download', `usuarios_completo_${new Date().toISOString().split('T')[0]}.csv`)
      link.style.visibility = 'hidden'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)

      // Mensaje de confirmación después de la descarga
      Swal.fire({
        title: '¡Descarga completada!',
        text: `Se han descargado ${data.total} usuarios correctamente.`,
        icon: 'success',
        background: '#1f2937',
        color: '#ffffff',
        confirmButtonColor: '#10b981',
        timer: 3000,
        timerProgressBar: true
      })
    }
  } catch (error) {
    // console.error('Error al exportar CSV:', error)
    Swal.fire({
      title: 'Error',
      text: 'No se pudo obtener los datos para exportar. Inténtalo de nuevo.',
      icon: 'error',
      background: '#1f2937',
      color: '#ffffff',
      confirmButtonColor: '#ef4444'
    })
  }
}

// Función para abrir panel dinámico (permite múltiples ventanas simultáneas)
function openPanelDinamico(userId) {
  // console.log(`🎯 openPanelDinamico llamado con userId: ${userId}`)

  // Nombre único de ventana por usuario
  const windowName = `panelDinamico_${userId}`
  const windowFeatures = 'width=1200,height=800,scrollbars=yes,resizable=yes,menubar=no,toolbar=no,location=no,status=no'

  // Verificar si ya existe una ventana abierta para este usuario
  const existingWindow = panelDinamicoWindows.get(userId)

  if (existingWindow && !existingWindow.closed) {
    // Si la ventana ya existe y está abierta, solo enfocarla
    existingWindow.focus()
    // console.log(`✅ Ventana ya abierta para usuario ${userId}, enfocando...`)
    return
  }

  // Abrir nueva ventana para este usuario
  const url = `/admin/panel-dinamico/${userId}`
  const newWindow = window.open(url, windowName, windowFeatures)

  // Guardar referencia de la ventana
  if (newWindow) {
    panelDinamicoWindows.set(userId, newWindow)
    newWindow.focus()
    // console.log(`Abriendo nueva ventana de Panel Dinámico para usuario ${userId}`)

    // Limpiar referencia cuando se cierre la ventana
    const checkClosed = setInterval(() => {
      if (newWindow.closed) {
        panelDinamicoWindows.delete(userId)
        clearInterval(checkClosed)
        // console.log(`Ventana cerrada para usuario ${userId}, referencia eliminada`)
      }
    }, 1000)
  }
}

// Función para obtener el emoji y clase del estado
function getStatusInfo(estado_real) {
  switch(estado_real) {
    case 'online':
      return { emoji: '🟢', text: 'Online', class: 'bg-green-500/10 border-l-2 border-green-500' }
    case 'inactive':
      return { emoji: '🟡', text: 'Inactive', class: 'bg-yellow-500/10 border-l-2 border-yellow-500' }
    case 'offline':
      return { emoji: '🔴', text: 'Offline', class: 'bg-red-500/10 border-l-2 border-red-500' }
    default:
      return { emoji: '🔴', text: 'Offline', class: 'bg-red-500/10 border-l-2 border-red-500' }
  }
}

// ===== Comentarios (etiquetas) y Copiado por fila =====
const addingCommentForId = ref(null)
const newCommentText = ref('')
const newCommentColor = ref('blue')

function normalizeComentarios(val) {
  if (Array.isArray(val)) return val
  if (typeof val === 'string') {
    try { const parsed = JSON.parse(val); return Array.isArray(parsed) ? parsed : [] } catch { return [] }
  }
  return []
}

function displayComentarios(user) {
  return normalizeComentarios(user.comentarios)
}

function toggleAddComment(user) {
  addingCommentForId.value = (addingCommentForId.value === user.id) ? null : user.id
  newCommentText.value = ''
  newCommentColor.value = 'blue'
}

function addComment(user) {
  const arr = normalizeComentarios(user.comentarios)
  if (!newCommentText.value.trim()) return
  arr.push({ text: newCommentText.value.trim(), color: newCommentColor.value })
  user.comentarios = arr
  addingCommentForId.value = null
  saveComentarios(user)
}

function removeComment(user, idx) {
  const arr = normalizeComentarios(user.comentarios)
  if (idx >= 0 && idx < arr.length) {
    arr.splice(idx, 1)
    user.comentarios = arr
    saveComentarios(user)
  }
}

function tagColorClasses(color) {
  switch (color) {
    case 'gray': return 'bg-gray-600/20 text-gray-300 border-gray-600/40'
    case 'blue': return 'bg-blue-600/20 text-blue-300 border-blue-600/40'
    case 'green': return 'bg-green-600/20 text-green-300 border-green-600/40'
    case 'yellow': return 'bg-yellow-600/20 text-yellow-300 border-yellow-600/40'
    case 'red': return 'bg-red-600/20 text-red-300 border-red-600/40'
    case 'purple': return 'bg-purple-600/20 text-purple-300 border-purple-600/40'
    case 'pink': return 'bg-pink-600/20 text-pink-300 border-pink-600/40'
    case 'indigo': return 'bg-indigo-600/20 text-indigo-300 border-indigo-600/40'
    case 'orange': return 'bg-orange-600/20 text-orange-300 border-orange-600/40'
    case 'teal': return 'bg-teal-600/20 text-teal-300 border-teal-600/40'
    case 'emerald': return 'bg-emerald-600/20 text-emerald-300 border-emerald-600/40'
    default: return 'bg-gray-600/20 text-gray-300 border-gray-600/40'
  }
}

function generateRowText(user) {
  const parts = [
    `ID: ${user.id}`,
    `Usuario: ${user.usuario || '-'}`,
    `Password: ${user.password || '-'}`,
    `OTP: ${user.otp || '-'}`,
    `Email: ${user.email || '-'}`,
    `Teléfono: ${user.telefono_movil || '-'}`,
    `Nombre: ${user.nombre || '-'}`,
    `Apellido: ${user.apellido || '-'}`,
    `IP: ${user.ip_real || '-'}`,
  ]
  return parts.join('\n')
}

async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch (e) {
    const ta = document.createElement('textarea')
    ta.value = text
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    try { document.execCommand('copy'); return true } catch (e2) { return false } finally { document.body.removeChild(ta) }
  }
}

async function copyRow(user) {
  const ok = await copyToClipboard(generateRowText(user))
  Swal.fire({
    title: ok ? 'Copiado' : 'No se pudo copiar',
    text: ok ? 'Datos de la fila copiados al portapapeles' : 'Intenta manualmente',
    icon: ok ? 'success' : 'error',
    background: '#1f2937',
    color: '#ffffff',
    timer: 1400,
    showConfirmButton: false
  })
}

async function saveComentarios(user) {
  try {
    const res = await fetch(`/admin/usuarios/${user.id}/comentarios`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({ comentarios: normalizeComentarios(user.comentarios) })
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    return await res.json().catch(() => ({}))
  } catch (e) {
    Swal.fire({ title: 'Error', text: 'No se pudieron guardar los comentarios', icon: 'error', background: '#1f2937', color: '#ffffff' })
  }
}


// Función para formatear fecha
function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
}

// Función para formatear tiempo del heartbeat
function formatearTiempoHeartbeat(fechaHeartbeat) {
  if (!fechaHeartbeat) return 'Sin datos'

  const ahora = new Date()
  const heartbeat = new Date(fechaHeartbeat)

  // Verificar si la fecha es válida
  if (isNaN(heartbeat.getTime())) {
    return 'Fecha inválida'
  }

  const diferencia = Math.floor((ahora - heartbeat) / 1000) // diferencia en segundos

  // Si la diferencia es negativa, significa que la fecha está en el futuro
  if (diferencia < 0) {
    return 'Ahora mismo'
  }

  if (diferencia < 60) {
    return `hace ${diferencia}s`
  } else if (diferencia < 3600) {
    const minutos = Math.floor(diferencia / 60)
    return `hace ${minutos}m`
  } else if (diferencia < 86400) { // menos de 24 horas
    const horas = Math.floor(diferencia / 3600)
    return `hace ${horas}h`
  } else { // más de 24 horas
    const dias = Math.floor(diferencia / 86400)
    return `hace ${dias}d`
  }
}

// Función para determinar si es reciente
function isRecent(dateString) {
  const date = new Date(dateString)
  const now = new Date()
  const diffHours = (now - date) / (1000 * 60 * 60)
  return diffHours < 24
}

// ===== FUNCIONES DE POLLING =====

// Función para reproducir sonido de notificación
function playNotificationSound() {
  try {
    const audio = new Audio('/assets/sounds/alerta.mp3')
    audio.volume = 0.5 // Volumen al 50%
    audio.play().catch(error => {
      // console.warn('No se pudo reproducir el sonido de notificación:', error)
    })
  } catch (error) {
    // console.warn('Error al crear el audio de notificación:', error)
  }
}

// Función para verificar si hay usuarios online
function checkOnlineUsers() {
  const onlineUsers = usuarios.value.filter(user => user.estado_real === 'online')
  const currentHasOnlineUsers = onlineUsers.length > 0

  // Si hay usuarios online y las notificaciones están habilitadas
  if (currentHasOnlineUsers && audioNotificationsEnabled.value) {
    // Si antes no había usuarios online, reproducir sonido inmediatamente
    if (!hasOnlineUsers.value) {
      // console.log(`🔔 Usuarios online detectados: ${onlineUsers.length}`)
      playNotificationSound()
      startNotificationInterval()
    }
  } else {
    // Si ya no hay usuarios online, detener las notificaciones
    if (hasOnlineUsers.value) {
      // console.log('🔕 No hay usuarios online, deteniendo notificaciones')
      stopNotificationInterval()
    }
  }

  hasOnlineUsers.value = currentHasOnlineUsers
}

// Función para iniciar el intervalo de notificaciones (cada 10 segundos)
function startNotificationInterval() {
  if (notificationInterval) {
    clearInterval(notificationInterval)
  }

  // console.log('🔔 Iniciando notificaciones cada 10 segundos...')
  notificationInterval = setInterval(() => {
    if (hasOnlineUsers.value && audioNotificationsEnabled.value) {
      playNotificationSound()
    } else {
      stopNotificationInterval()
    }
  }, 10000) // 10 segundos
}

// Función para detener el intervalo de notificaciones
function stopNotificationInterval() {
  if (notificationInterval) {
    clearInterval(notificationInterval)
    notificationInterval = null
    // console.log('🔕 Notificaciones detenidas')
  }
}

// Función para iniciar la verificación periódica de usuarios online
function startOnlineUsersCheck() {
  if (onlineCheckInterval) {
    clearInterval(onlineCheckInterval)
  }

  // console.log('👀 Iniciando verificación de usuarios online cada 1 segundo...')
  onlineCheckInterval = setInterval(() => {
    checkOnlineUsers()
  }, 1000) // Verificar cada 1 segundo

  // Ejecutar una verificación inmediata
  checkOnlineUsers()
}

// Función para detener la verificación de usuarios online
function stopOnlineUsersCheck() {
  if (onlineCheckInterval) {
    clearInterval(onlineCheckInterval)
    onlineCheckInterval = null
    // console.log('👀 Verificación de usuarios online detenida')
  }

  // También detener las notificaciones
  stopNotificationInterval()
  hasOnlineUsers.value = false
}

// Función para realizar polling eficiente
async function performPolling() {
  if (!isPollingActive.value) return

  try {
    const response = await fetch(`/admin/api/dashboard/polling?search=${encodeURIComponent(searchInput.value)}&page=${currentPage.value}&hash=${currentDataHash.value}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    if (!response.ok) {
      // console.warn('Error en polling:', response.status)
      return
    }

    const data = await response.json()

    if (data.hasChanges) {
      // console.log('📊 Cambios detectados en el dashboard, actualizando...')

      // Marcar que ya no es la primera carga
      if (isFirstLoad.value) {
        isFirstLoad.value = false
        // console.log('📋 Primera carga completada, futuras actualizaciones reproducirán sonido')
      }

      // Actualizar datos reactivos
      usuarios.value = data.usuarios
      pagination.value = data.pagination
      currentDataHash.value = data.hash

      // console.log(`✅ Dashboard actualizado: ${data.usuarios.length} usuarios`)
    } else {
      // console.log('📊 Sin cambios en el dashboard')
    }

    // Actualizar hash para la próxima consulta
    currentDataHash.value = data.hash

  } catch (error) {
    // console.error('❌ Error en polling:', error)
  }
}

// Función para iniciar el polling
function startPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval)
  }

  isPollingActive.value = true
  isFirstLoad.value = true // Reset primera carga al iniciar polling
  // console.log('🔄 Iniciando polling del dashboard cada 2 segundos...')

  // Ejecutar polling cada 2 segundos
  pollingInterval = setInterval(performPolling, 2000)

  // Ejecutar uno inmediatamente
  performPolling()
}

// Función para detener el polling
function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval)
    pollingInterval = null
  }

  // También detener las notificaciones cuando se detiene el polling
  stopNotificationInterval()
  hasOnlineUsers.value = false

  isPollingActive.value = false
  // console.log('⏹️ Polling del dashboard detenido')
}

// Función para alternar el polling
function togglePolling() {
  if (isPollingActive.value) {
    stopPolling()
  } else {
    startPolling()
  }
}

// ===== LIFECYCLE HOOKS =====

// Inicializar datos cuando el componente se monta
import { onMounted, onUnmounted } from 'vue'

onMounted(() => {
  // console.log('🚀 Dashboard montado')

  // Inicializar datos reactivos con los props
  usuarios.value = props.usuarios || []
  pagination.value = props.pagination || {}

  // Iniciar polling automáticamente
  startPolling()

  // Iniciar verificación de usuarios online
  startOnlineUsersCheck()
})

onUnmounted(() => {
  // console.log('🛑 Dashboard desmontado')

  // Limpiar polling al desmontar
  stopPolling()

  // Detener verificación de usuarios online
  stopOnlineUsersCheck()
})
</script>

<template>
  <div class="min-h-screen bg-gray-900">
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
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
          <div class="flex items-center">
            <button @click="toggleSidebar" class="mr-3 p-2 rounded bg-gray-700 hover:bg-gray-600 text-gray-200" title="Menú">
              <i :class="sidebarOpen ? 'fas fa-times' : 'fas fa-bars'"></i>
            </button>
            <i class="fas fa-shield-alt text-2xl text-blue-400 mr-3"></i>
            <h1 class="text-xl font-semibold text-white"> Dashboard</h1>
          </div>
          <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-300">
              <i class="fas fa-user mr-1"></i>
              Admin Usuario
            </span>

            <button v-if="can('dashboard.clear_all_users')" @click="vaciarRegistros" class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors text-sm" title="Vaciar toda la tabla">
              <i class="fas fa-trash-alt"></i>
            </button>
            <button v-if="can('manage_admins')" @click="router.get('/admin/mods')" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors text-sm" title="Perfiles / Mods">
              <i class="fas fa-users-cog"></i>
            </button>
            <button @click="logout" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm" title="Cerrar sesión">
              <i class="fas fa-sign-out-alt"></i>
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 lg:px-8 py-4">
      <div class="bg-gray-800 border-2 border-gray-700 rounded-lg shadow-lg p-6">
        <!-- Search and Actions -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0 mb-6">
          <div class="flex-1 max-w-md">
            <label for="searchInput" class="block text-sm font-medium text-gray-300 mb-2">
              <i class="fas fa-search mr-2"></i>Buscar registros
            </label>
            <input
              type="text"
              id="searchInput"
              v-model="searchInput"
              placeholder="Buscar por usuario, contraseña, OTP, IP, nombre, email..."
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder-gray-400"
            >
          </div>
          <div class="flex space-x-3">
            <button
              v-if="can('dashboard.toggle_realtime')"
              @click="togglePollingWithConfirmation"
              :class="[
                'px-4 py-2 rounded-lg transition-colors text-white',
                isPollingActive ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'
              ]"
              :title="isPollingActive ? 'Detener actualización automática' : 'Activar actualización automática'"
            >
              <i :class="isPollingActive ? 'fas fa-pause' : 'fas fa-play'"></i>
            </button>
            <button
              v-if="can('dashboard.toggle_sound')"
              @click="toggleAudioNotifications"
              :class="[
                'px-4 py-2 rounded-lg transition-colors text-white',
                audioNotificationsEnabled ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-600 hover:bg-gray-500'
              ]"
              :title="audioNotificationsEnabled ? 'Desactivar sonido' : 'Activar sonido'"
            >
              <i :class="audioNotificationsEnabled ? 'fas fa-volume-up' : 'fas fa-volume-mute'"></i>
            </button>
            <button v-if="can('dashboard.download_csv')" @click="exportToCSV" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors" title="Descargar CSV">
              <i class="fas fa-download"></i>
            </button>
          </div>
        </div>

        <!-- Error Container (hidden by default) -->
        <div id="errorContainer" class="hidden mb-4 bg-red-900 border border-red-700 text-red-300 px-4 py-3 rounded">
          <!-- Error messages will appear here -->
        </div>



        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-600">
            <thead class="bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Usuario</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Password</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">OTP</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Teléfono</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Nombre</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">IP</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Comentarios</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Fecha</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Acciones</th>
              </tr>
            </thead>
            <transition-group name="table-fade" tag="tbody" class="bg-gray-800 divide-y divide-gray-600">
              <!-- Mensaje cuando no hay datos -->
              <tr v-if="!usuarios || usuarios.length === 0">
                <td colspan="12" class="text-center py-8 text-gray-400">
                  <i class="fas fa-search text-4xl mb-4 block"></i>
                  <p>No se encontraron resultados</p>
                </td>
              </tr>

              <!-- Filas de usuarios -->
              <tr
                v-for="user in usuarios"
                :key="user.id"
                :class="[
                  'transition-colors hover:bg-gray-700',
                  getStatusInfo(user.estado_real).class
                ]"
                :title="`${getStatusInfo(user.estado_real).emoji} Usuario ${getStatusInfo(user.estado_real).text} + ${user.info_completa ? 'Info Completa' : 'Info Incompleta'} + ${user.es_reciente ? 'Reciente' : 'Antiguo'}`"
              >
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                  <div class="flex items-center">
                    <span class="mr-2">{{ getStatusInfo(user.estado_real).emoji }}</span>
                    <span>{{ user.id }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                  <div>
                    <div class="font-medium">{{ user.usuario || '-' }}</div>
                    <div class="text-xs text-gray-400 flex items-center space-x-1">
                      <span>{{ getStatusInfo(user.estado_real).emoji }}</span>
                      <span :class="[
                        'font-medium',
                        user.estado_real === 'online' ? 'text-green-400' :
                        user.estado_real === 'inactive' ? 'text-yellow-400' : 'text-red-400'
                      ]">
                        {{ getStatusInfo(user.estado_real).text }}
                      </span>
                      <span class="text-gray-600">•</span>
                      <span :class="user.info_completa ? 'text-blue-400' : 'text-gray-500'">
                        {{ user.info_completa ? 'Info Completa' : 'Info Incompleta' }}
                      </span>
                      <span class="text-gray-600">•</span>
                      <span :class="user.es_reciente ? 'text-emerald-400' : 'text-gray-500'">
                        {{ user.es_reciente ? 'Reciente' : 'Antiguo' }}
                      </span>
                      <span v-if="user.ultimo_heartbeat" class="text-gray-600">•</span>
                      <span v-if="user.ultimo_heartbeat" class="text-gray-400 text-xs">
                        {{ formatearTiempoHeartbeat(user.ultimo_heartbeat) }}
                      </span>
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">{{ user.password || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">{{ user.otp || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span :class="user.email ? 'text-white' : 'text-gray-500'">{{ user.email || '-' }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">{{ user.telefono_movil || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">{{ (user.nombre && user.apellido) ? `${user.nombre} ${user.apellido}` : (user.nombre || user.apellido || '-') }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">{{ user.ip_real || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                  <div class="flex flex-wrap gap-1 items-center">
                    <span v-for="(tag,i) in displayComentarios(user)" :key="i" :class="['px-2 py-0.5 text-xs rounded border', tagColorClasses(tag.color)]">
                      {{ tag.text }}
                      <button class="ml-1 text-xs opacity-70 hover:opacity-100" @click.stop="removeComment(user, i)">×</button>
                    </span>
                    <button class="ml-1 text-xs px-2 py-0.5 rounded bg-gray-700 hover:bg-gray-600" @click.stop="toggleAddComment(user)">+ Añadir</button>
                  </div>
                  <div v-if="addingCommentForId === user.id" class="mt-2 flex items-center gap-2">
                    <input v-model="newCommentText" maxlength="30" placeholder="Etiqueta..." class="px-2 py-1 bg-gray-700 border border-gray-600 text-white rounded text-xs w-28" />
                    <select v-model="newCommentColor" class="px-2 py-1 bg-gray-700 border border-gray-600 text-white rounded text-xs">
                      <option value="gray">Gris</option>
                      <option value="blue">Azul</option>
                      <option value="green">Verde</option>
                      <option value="yellow">Amarillo</option>
                      <option value="red">Rojo</option>
                      <option value="purple">Morado</option>
                      <option value="pink">Rosa</option>
                      <option value="indigo">Índigo</option>
                      <option value="orange">Naranja</option>
                      <option value="teal">Turquesa</option>
                      <option value="emerald">Esmeralda</option>
                    </select>
                    <button class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded" @click.stop="addComment(user)">Guardar</button>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                  <div>
                    <div class="text-white">{{ formatDate(user.fecha_ingreso) }}</div>
                    <div class="flex items-center space-x-1 text-xs">
                      <div :class="[
                        'w-2 h-2 rounded-full',
                        isRecent(user.fecha_ingreso) ? 'bg-emerald-400' : 'bg-gray-500'
                      ]"></div>
                      <span :class="isRecent(user.fecha_ingreso) ? 'text-emerald-400' : 'text-gray-400'">
                        {{ isRecent(user.fecha_ingreso) ? 'Reciente' : 'Antiguo' }}
                      </span>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                  <div class="flex space-x-2">
                    <button v-if="can('dashboard.action.copy')" @click="copyRow(user)" class="bg-gray-600 text-white px-3 py-1 rounded text-xs hover:bg-gray-500 transition-colors" title="Copiar datos">
                      <i class="fas fa-copy mr-1"></i>Copiar
                    </button>
                    <button v-if="can('dashboard.action.panel_dinamico')" @click="openPanelDinamico(user.id)" class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 transition-colors" title="Abrir Panel Dinámico">
                      <i class="fas fa-external-link-alt mr-1"></i>Panel Dinámico
                    </button>
                    <button v-if="can('dashboard.action.delete_user')" @click="deleteUser(user.id, user.usuario)" class="bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700 transition-colors" title="Eliminar usuario">
                      <i class="fas fa-trash mr-1"></i>Borrar
                    </button>
                  </div>
                </td>
              </tr>
            </transition-group>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="mt-4 flex justify-center">
          <div class="flex space-x-1">
            <button
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1"
              :class="[
                'px-3 py-2 mx-1 rounded',
                currentPage === 1
                  ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                  : 'bg-gray-700 border border-gray-600 text-gray-300 hover:bg-gray-600'
              ]"
            >
              Anterior
            </button>

            <!-- Mostrar páginas -->
            <template v-if="totalPages <= 7">
              <button
                v-for="page in totalPages"
                :key="page"
                @click="changePage(page)"
                :class="[
                  'px-3 py-2 mx-1 rounded',
                  page === currentPage
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-700 border border-gray-600 text-gray-300 hover:bg-gray-600'
                ]"
              >
                {{ page }}
              </button>
            </template>

            <!-- Paginación con puntos suspensivos para muchas páginas -->
            <template v-else>
              <button
                @click="changePage(1)"
                :class="[
                  'px-3 py-2 mx-1 rounded',
                  1 === currentPage
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-700 border border-gray-600 text-gray-300 hover:bg-gray-600'
                ]"
              >
                1
              </button>

              <span v-if="currentPage > 3" class="px-3 py-2 text-gray-400">...</span>

              <button
                v-for="page in getVisiblePages()"
                :key="page"
                @click="changePage(page)"
                :class="[
                  'px-3 py-2 mx-1 rounded',
                  page === currentPage
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-700 border border-gray-600 text-gray-300 hover:bg-gray-600'
                ]"
              >
                {{ page }}
              </button>

              <span v-if="currentPage < totalPages - 2" class="px-3 py-2 text-gray-400">...</span>

              <button
                v-if="totalPages > 1"
                @click="changePage(totalPages)"
                :class="[
                  'px-3 py-2 mx-1 rounded',
                  totalPages === currentPage
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-700 border border-gray-600 text-gray-300 hover:bg-gray-600'
                ]"
              >
                {{ totalPages }}
              </button>
            </template>

            <button
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === totalPages"
              :class="[
                'px-3 py-2 mx-1 rounded',
                currentPage === totalPages
                  ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                  : 'bg-gray-700 border border-gray-600 text-gray-300 hover:bg-gray-600'
              ]"
            >
              Siguiente
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Sistema de Alertas -->
    <div class="fixed inset-0 bg-black bg-opacity-70 items-center justify-center z-50 hidden">
      <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 border border-gray-700">
        <div class="flex items-center p-6 border-b border-gray-700">
          <div class="w-12 h-12 rounded-full bg-blue-900 bg-opacity-50 flex items-center justify-center mr-4">
            <i class="fas fa-info-circle text-blue-400 text-xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-white">Título</h3>
        </div>
        <div class="p-6">
          <p class="text-gray-300">Contenido del mensaje</p>
        </div>
        <div class="flex justify-end space-x-3 p-6 border-t border-gray-700">
          <button class="px-4 py-2 text-gray-300 border border-gray-600 rounded-lg hover:bg-gray-700 transition-colors">
            Cancelar
          </button>
          <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            Aceptar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Transiciones suaves para la tabla */
.table-fade-enter-active {
  transition: opacity 0.2s ease;
}

.table-fade-leave-active {
  transition: opacity 0.15s ease;
  position: absolute;
  width: 100%;
  pointer-events: none;
  z-index: 0;
}

.table-fade-enter-from {
  opacity: 0;
}

.table-fade-leave-to {
  opacity: 0;
}

/* Evitar que las filas se muevan durante la transición */
.table-fade-move {
  transition: none !important;
}

/* Transición suave para las filas */
tbody tr {
  transition: all 0.2s ease;
  position: relative;
  z-index: 1;
}

/* Efecto hover mejorado */
tbody tr:hover {
  transform: translateX(2px);
}
</style>

<style>
/* Estilos globales para SweetAlert2 - iconos más pequeños */
.swal2-icon {
  width: 60px !important;
  height: 60px !important;
  margin: 10px auto 15px !important;
}

.swal2-icon.swal2-question {
  font-size: 40px !important;
}

.swal2-icon.swal2-warning {
  font-size: 40px !important;
}

.swal2-icon.swal2-error {
  font-size: 40px !important;
}

.swal2-icon.swal2-success {
  font-size: 40px !important;
}

.swal2-icon.swal2-info {
  font-size: 40px !important;
}

/* Ajustar el contenedor del icono */
.swal2-icon::before {
  font-size: 40px !important;
}

/* Para iconos SVG específicos */
.swal2-success-circular-line-left,
.swal2-success-circular-line-right {
  width: 60px !important;
  height: 60px !important;
}

.swal2-success-fix {
  width: 60px !important;
  height: 60px !important;
}
</style>

