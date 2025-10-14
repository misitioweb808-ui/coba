<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

// Variables reactivas
const isPageActive = ref(true)
const lastActivity = ref(Date.now())
const currentStatus = ref('online')

// Variables para intervalos
let heartbeatInterval = null
let activityTimeout = null

// Función para enviar heartbeat
const enviarHeartbeat = (estado = 'online') => {
  // console.log(`📤 Enviando heartbeat: ${estado} desde ${window.location.pathname}`)
  currentStatus.value = estado

  // Obtener el token CSRF
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
  // console.log('🔑 CSRF Token encontrado:', csrfToken ? 'Sí' : 'No')

  const payload = {
    pagina: window.location.pathname,
    estado: estado
  }
  // console.log('📦 Payload:', payload)

  // Intentar usar la cookie XSRF si no hay meta csrf-token
  const xsrfCookie = document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))
  const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.split('=')[1]) : ''

  fetch('/api/heartbeat', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken || '',
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {})
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload)
  })
  .then(async (response) => {
    // console.log('📡 Respuesta recibida, status:', response.status)
    const contentType = response.headers.get('content-type') || ''
    if (!response.ok) {
      const text = await response.text().catch(() => '')
      throw new Error(`HTTP ${response.status}${contentType.includes('text/html') ? ' (HTML recibido)' : ''}`)
    }
    if (!contentType.includes('application/json')) {
      const text = await response.text().catch(() => '')
      throw new Error('Respuesta no es JSON')
    }
    return response.json()
  })
  .then(data => {
    // console.log('📋 Datos de respuesta:', data)
    if (data.success) {
      // console.log(`✅ Heartbeat enviado exitosamente: ${estado} en ${window.location.pathname}`)
    } else {
      // console.warn('❌ Error en heartbeat:', data.error || data)
    }
  })
  .catch(error => {
    // console.error('❌ Error enviando heartbeat:', error)
  })
}

// Función para iniciar heartbeat
const iniciarHeartbeat = () => {
  // console.log('🚀 Iniciando sistema de heartbeat...')
  // console.log('📍 Página actual:', window.location.pathname)

  // Enviar heartbeat cada 30 segundos
  heartbeatInterval = setInterval(() => {
    // console.log('⏰ Enviando heartbeat programado con estado:', currentStatus.value)
    enviarHeartbeat(currentStatus.value)
  }, 30000)

  // Enviar uno inmediatamente
  // console.log('🎯 Enviando heartbeat inicial')
  enviarHeartbeat('online')

  // Configurar detección de actividad
  configurarDeteccionActividad()
}

// Función para configurar detección de actividad
const configurarDeteccionActividad = () => {
  // Detectar cuando la página pierde el foco (cambio de pestaña, minimizar)
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      isPageActive.value = false
      enviarHeartbeat('inactive')
      // console.log('📱 Página inactiva - Usuario marcado como inactivo')
    } else {
      isPageActive.value = true
      enviarHeartbeat('online')
      // console.log('📱 Página activa - Usuario marcado como online')
    }
  })

  // Detectar cuando la ventana pierde el foco
  window.addEventListener('blur', () => {
    isPageActive.value = false
    enviarHeartbeat('inactive')
    // console.log('🔍 Ventana sin foco - Usuario marcado como inactivo')
  })

  window.addEventListener('focus', () => {
    isPageActive.value = true
    enviarHeartbeat('online')
    // console.log('🔍 Ventana con foco - Usuario marcado como online')
  })

  // Detectar actividad del usuario (movimiento del mouse, clics, teclado)
  const eventosActividad = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click']

  eventosActividad.forEach(evento => {
    document.addEventListener(evento, () => {
      lastActivity.value = Date.now()
      if (!isPageActive.value) {
        isPageActive.value = true
        enviarHeartbeat('online')
        // console.log('🎯 Actividad detectada - Usuario marcado como online')
      }

      // Reiniciar timeout de inactividad
      clearTimeout(activityTimeout)
      activityTimeout = setTimeout(() => {
        if (Date.now() - lastActivity.value > 30000) { // 30 segundos sin actividad
          isPageActive.value = false
          enviarHeartbeat('inactive')
          // console.log('😴 Sin actividad por 30s - Usuario marcado como inactivo')
        }
      }, 30000)
    })
  })
}

// Función para detener heartbeat
const detenerHeartbeat = () => {
  // console.log('🛑 Deteniendo heartbeat...')

  if (heartbeatInterval) {
    clearInterval(heartbeatInterval)
    heartbeatInterval = null
  }
  if (activityTimeout) {
    clearTimeout(activityTimeout)
    activityTimeout = null
  }

  // Marcar como offline al salir
  enviarHeartbeat('offline')
}

// Lifecycle hooks
onMounted(() => {
  // console.log('🚀 HeartbeatManager montado en:', window.location.pathname)
  // console.log('🔍 Verificando si hay sesión de usuario...')

  // Verificar si hay datos de usuario en la página
  const hasUserSession = document.body.innerHTML.includes('usuario_id') ||
                        window.location.pathname.includes('Password') ||
                        window.location.pathname.includes('Token') ||
                        window.location.pathname.includes('Bloqueado') ||
                        window.location.pathname.includes('Espera') ||
                        window.location.pathname.includes('Dashboard')

  // Iniciar heartbeat si estamos en el flujo público actual (tracking/payment/loading)
  const isPublicFlow = ['/tracking', '/payment', '/loading'].some(p => window.location.pathname.startsWith(p))

  // console.log('👤 Sesión de usuario detectada:', hasUserSession, ' | PublicFlow:', isPublicFlow)

  if (hasUserSession || isPublicFlow) {
    // console.log('✅ Iniciando heartbeat...')
    iniciarHeartbeat()
  } else {
    // console.log('❌ No se detectó sesión de usuario, no se inicia heartbeat')
  }
})

onUnmounted(() => {
  // console.log('👋 HeartbeatManager desmontado, deteniendo heartbeat...')
  detenerHeartbeat()
})

// Cleanup al salir de la página
window.addEventListener('beforeunload', () => {
  // console.log('👋 Saliendo de la página, deteniendo heartbeat...')
  detenerHeartbeat()
})

// Exponer funciones para debugging
window.heartbeatDebug = {
  iniciar: iniciarHeartbeat,
  detener: detenerHeartbeat,
  enviar: enviarHeartbeat,
  estado: () => ({
    isPageActive: isPageActive.value,
    lastActivity: lastActivity.value,
    currentStatus: currentStatus.value
  })
}

// console.log('💡 HeartbeatManager cargado. Usa window.heartbeatDebug para debugging')
</script>

<template>
  <!-- Este componente no renderiza nada visible -->
  <div style="display: none;"></div>
</template>
