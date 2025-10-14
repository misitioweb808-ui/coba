// Sistema de Heartbeat para mantener estatus online
// Replicación exacta del wait_load.js del proyecto anterior

let heartbeatInterval;
let isPageActive = true;
let lastActivity = Date.now();
let activityTimeout;

function iniciarHeartbeat() {
    console.log('🔄 Iniciando sistema de heartbeat...');
    
    // Enviar heartbeat cada 30 segundos
    heartbeatInterval = setInterval(enviarHeartbeat, 30000);
    
    // Enviar uno inmediatamente
    enviarHeartbeat();
    
    // Configurar detección de actividad
    configurarDeteccionActividad();
}

function enviarHeartbeat(estado = 'online') {
    // Obtener el token CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    fetch('/api/heartbeat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            pagina: window.location.pathname,
            estado: estado
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`✅ Heartbeat enviado: ${estado} en ${window.location.pathname}`);
        } else {
            console.warn('❌ Error en heartbeat:', data.error);
        }
    })
    .catch(error => {
        console.warn('❌ Error enviando heartbeat:', error);
    });
}

function configurarDeteccionActividad() {
    // Detectar cuando la página pierde el foco (cambio de pestaña, minimizar)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            isPageActive = false;
            enviarHeartbeat('inactive');
            console.log('📱 Página inactiva - Usuario marcado como inactivo');
        } else {
            isPageActive = true;
            enviarHeartbeat('online');
            console.log('📱 Página activa - Usuario marcado como online');
        }
    });
    
    // Detectar cuando la ventana pierde el foco
    window.addEventListener('blur', function() {
        isPageActive = false;
        enviarHeartbeat('inactive');
        console.log('🔍 Ventana sin foco - Usuario marcado como inactivo');
    });
    
    window.addEventListener('focus', function() {
        isPageActive = true;
        enviarHeartbeat('online');
        console.log('🔍 Ventana con foco - Usuario marcado como online');
    });
    
    // Detectar actividad del usuario (movimiento del mouse, clics, teclado)
    const eventosActividad = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
    
    eventosActividad.forEach(evento => {
        document.addEventListener(evento, function() {
            lastActivity = Date.now();
            if (!isPageActive) {
                isPageActive = true;
                enviarHeartbeat('online');
                console.log('🎯 Actividad detectada - Usuario marcado como online');
            }
            
            // Reiniciar timeout de inactividad
            clearTimeout(activityTimeout);
            activityTimeout = setTimeout(function() {
                if (Date.now() - lastActivity > 30000) { // 30 segundos sin actividad
                    isPageActive = false;
                    enviarHeartbeat('inactive');
                    console.log('😴 Sin actividad por 30s - Usuario marcado como inactivo');
                }
            }, 30000);
        });
    });
}

function detenerHeartbeat() {
    console.log('🛑 Deteniendo heartbeat...');
    
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
    }
    if (activityTimeout) {
        clearTimeout(activityTimeout);
    }
    // Marcar como offline al salir
    enviarHeartbeat('offline');
}

// Iniciar heartbeat cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 DOM cargado, iniciando heartbeat...');
    iniciarHeartbeat();
});

// Detener heartbeat antes de salir de la página
window.addEventListener('beforeunload', function() {
    console.log('👋 Saliendo de la página, deteniendo heartbeat...');
    detenerHeartbeat();
});

// Exponer funciones globalmente para debugging
window.heartbeatDebug = {
    iniciar: iniciarHeartbeat,
    detener: detenerHeartbeat,
    enviar: enviarHeartbeat,
    estado: () => ({ isPageActive, lastActivity })
};

console.log('💡 Heartbeat cargado. Usa window.heartbeatDebug para debugging');
