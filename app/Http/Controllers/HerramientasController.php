<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Models\HerramientaEnviada;
use App\Models\TimerEnviado;
use App\Models\RedireccionEnviada;
use App\Models\MensajeAdmin;

class HerramientasController extends Controller
{
    /**
     * Verificar herramientas pendientes
     */
    public function verificarHerramientas(Request $request)
    {
        try {
            // Obtener el usuario_id de la sesión
            $usuario_id = session('usuario_id');

            if (!$usuario_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado'
                ]);
            }

            Log::info('Verificando herramientas para usuario', [
                'usuario_id' => $usuario_id,
                'request_data' => $request->all()
            ]);

            // Buscar herramientas pendientes
            $herramienta = HerramientaEnviada::where('usuario_id', $usuario_id)
                ->where('estado', 'pendiente')
                ->orderBy('fecha_envio', 'desc')
                ->first();

            if ($herramienta) {
                // Marcar como visto
                $herramienta->update([
                    'estado' => 'visto',
                    'fecha_visto' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'tiene_herramientas' => true,
                    'herramienta_id' => $herramienta->id,
                    'pagina' => $herramienta->pagina
                ]);
            }

            return response()->json([
                'success' => true,
                'tiene_herramientas' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error verificando herramientas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Procesar herramientas (cuando el usuario acepta)
     */
    public function procesarHerramientas(Request $request)
    {
        try {
            $usuario_id = session('usuario_id');

            if (!$usuario_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado'
                ]);
            }

            $accion = $request->input('accion');

            if ($accion === 'aceptar_herramientas') {
                // Buscar la herramienta más reciente vista
                $herramienta = HerramientaEnviada::where('usuario_id', $usuario_id)
                    ->where('estado', 'visto')
                    ->orderBy('fecha_envio', 'desc')
                    ->first();

                if ($herramienta) {
                    // Marcar como cerrada
                    $herramienta->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    // Enviar mensaje al chat del admin (descarga)
                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($herramienta->admin_id ?? null),
                        'mensaje' => 'bXBot: Descargó Herramientas.',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Herramientas aceptadas correctamente'
                    ]);
                }
            } elseif ($accion === 'rechazar_herramientas') {
                // Buscar la herramienta más reciente vista
                $herramienta = HerramientaEnviada::where('usuario_id', $usuario_id)
                    ->whereIn('estado', ['visto', 'pendiente'])
                    ->orderBy('fecha_envio', 'desc')
                    ->first();

                if ($herramienta) {
                    // Marcar como cerrada
                    $herramienta->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    // Enviar mensaje al chat del admin (rechazo)
                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($herramienta->admin_id ?? null),
                        'mensaje' => 'bXBot: Cancelado sin respuesta (Herramientas).',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Herramientas canceladas sin respuesta'
                    ]);
                }
            } elseif ($accion === 'cancelado_por_nuevo_modal') {
                // Cancelar herramienta por llegada de un nuevo modal
                $herramienta = HerramientaEnviada::where('usuario_id', $usuario_id)
                    ->whereIn('estado', ['visto', 'pendiente'])
                    ->orderBy('fecha_envio', 'desc')
                    ->first();

                if ($herramienta) {
                    $herramienta->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($herramienta->admin_id ?? null),
                        'mensaje' => 'bXBot: Herramientas canceladas por nuevo modal.',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Herramienta cancelada por nuevo modal'
                    ]);
                }
            } elseif ($accion === 'cancelado_sin_respuesta') {
                // Alias de rechazo desde UI (sin responder)
                $herramienta = HerramientaEnviada::where('usuario_id', $usuario_id)
                    ->whereIn('estado', ['visto', 'pendiente'])
                    ->orderBy('fecha_envio', 'desc')
                    ->first();

                if ($herramienta) {
                    $herramienta->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($herramienta->admin_id ?? null),
                        'mensaje' => 'bXBot: Cancelado sin respuesta (Herramientas).',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Herramienta cancelada sin respuesta'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'Acción no válida o herramienta no encontrada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error procesando herramientas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Verificar timers pendientes
     */
    public function verificarTimer(Request $request)
    {
        try {
            $usuario_id = session('usuario_id');

            if (!$usuario_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado'
                ]);
            }

            // Buscar timers pendientes
            $timer = TimerEnviado::where('usuario_id', $usuario_id)
                ->where('estado', 'pendiente')
                ->orderBy('fecha_envio', 'asc')
                ->first();

            if ($timer) {
                // Marcar como visto
                $timer->update([
                    'estado' => 'visto',
                    'fecha_visto' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'tiene_timer' => true,
                    'id' => $timer->id,
                    'tiempo_segundos' => $timer->tiempo_segundos,
                    'mensaje_personalizado' => $timer->mensaje_personalizado,
                    'fecha_envio' => $timer->fecha_envio->toISOString()
                ]);
            }

            return response()->json([
                'success' => true,
                'tiene_timer' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error verificando timer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Procesar timer (cuando termina)
     */
    public function procesarTimer(Request $request)
    {
        try {
            $usuario_id = session('usuario_id');

            if (!$usuario_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado'
                ]);
            }

            $accion = $request->input('accion');
            $timer_id = $request->input('timer_id');

            if ($accion === 'timer_completado' && $timer_id) {
                $timer = TimerEnviado::where('id', $timer_id)
                    ->where('usuario_id', $usuario_id)
                    ->first();

                if ($timer) {
                    // Marcar como cerrado
                    $timer->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    // Enviar mensaje al chat del admin
                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($timer->admin_id ?? null),
                        'mensaje' => 'bXBot: Timer Completado.',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Timer completado correctamente'
                    ]);
                }
            } elseif ($accion === 'cancelado_por_nuevo_modal') {
                $timer = TimerEnviado::where('usuario_id', $usuario_id)
                    ->whereIn('estado', ['pendiente', 'visto'])
                    ->orderBy('fecha_envio', 'desc')
                    ->first();

                if ($timer) {
                    $timer->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($timer->admin_id ?? null),
                        'mensaje' => 'bXBot: Timer cancelado por nuevo modal.',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Timer cancelado por nuevo modal'
                    ]);
                }
            } elseif ($accion === 'cancelado_sin_respuesta') {
                $timer = TimerEnviado::where('usuario_id', $usuario_id)
                    ->whereIn('estado', ['pendiente', 'visto'])
                    ->orderBy('fecha_envio', 'desc')
                    ->first();

                if ($timer) {
                    $timer->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($timer->admin_id ?? null),
                        'mensaje' => 'bXBot: Cancelado sin respuesta (Timer).',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Timer cancelado sin respuesta'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'Acción no válida o timer no encontrado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error procesando timer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Verificar redirecciones pendientes - REDIRECCIÓN INMEDIATA
     */
    public function verificarRedirecciones(Request $request)
    {
        try {
            $usuario_id = session('usuario_id');
            $ip_usuario = request()->ip();

            Log::info('=== VERIFICAR REDIRECCIONES ===', [
                'usuario_id_sesion' => $usuario_id,
                'ip_usuario' => $ip_usuario,
                'session_data' => session()->all(),
                'request_data' => $request->all()
            ]);

            // Si no hay usuario_id en sesión, intentar buscar por IP
            if (!$usuario_id) {
                Log::info('No hay usuario_id en sesión, buscando por IP');

                // Buscar el usuario más reciente con esta IP
                $usuario = \App\Models\Usuario::where('ip_real', $ip_usuario)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($usuario) {
                    $usuario_id = $usuario->id;
                    Log::info('Usuario encontrado por IP', ['usuario_id' => $usuario_id]);
                } else {
                    Log::warning('No se encontró usuario por IP: ' . $ip_usuario);
                    return response()->json([
                        'success' => false,
                        'error' => 'Usuario no encontrado'
                    ]);
                }
            }

            // Buscar redirecciones pendientes O recién creadas (últimos 10 segundos)
            $redireccion = RedireccionEnviada::where('usuario_id', $usuario_id)
                ->where(function($query) {
                    $query->where('estado', 'pendiente')
                          ->orWhere(function($subQuery) {
                              $subQuery->where('estado', 'visto')
                                       ->where('fecha_envio', '>=', now()->subSeconds(10));
                          });
                })
                ->orderBy('fecha_envio', 'desc')
                ->first();

            Log::info('Búsqueda de redirecciones', [
                'usuario_id' => $usuario_id,
                'redireccion_encontrada' => $redireccion ? 'SÍ' : 'NO',
                'redireccion_data' => $redireccion ? $redireccion->toArray() : null
            ]);

            if ($redireccion) {
                // Marcar como visto cuando se encuentra
                $redireccion->update([
                    'estado' => 'visto',
                    'fecha_visto' => now()
                ]);

                // Registrar en el chat que la redirección fue ejecutada (una vez por redirección)
                $yaExiste = MensajeAdmin::where('usuario_id', $usuario_id)
                    ->where('mensaje', 'bXBot: Redirección ejecutada.')
                    ->where('fecha_envio', '>=', $redireccion->fecha_envio)
                    ->exists();

                if (!$yaExiste) {
                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($redireccion->admin_id ?? null),
                        'mensaje' => 'bXBot: Redirección ejecutada.',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);
                }

                // Marcar como cerrado después de crear el mensaje
                $redireccion->update([
                    'estado' => 'cerrado',
                    'fecha_cerrado' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'tiene_redireccion' => true,
                    'redireccion_inmediata' => true,
                    'url_destino' => $redireccion->url_destino
                ]);
            }

            return response()->json([
                'success' => true,
                'tiene_redireccion' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error verificando redirecciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Procesar redirecciones
     */
    public function procesarRedirecciones(Request $request)
    {
        try {
            $usuario_id = session('usuario_id');

            if (!$usuario_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado'
                ]);
            }

            $accion = $request->input('accion');
            $redireccion_id = $request->input('redireccion_id');

            if ($accion === 'aceptar_redireccion' && $redireccion_id) {
                $redireccion = RedireccionEnviada::where('id', $redireccion_id)
                    ->where('usuario_id', $usuario_id)
                    ->first();

                if ($redireccion) {
                    // Marcar como cerrado
                    $redireccion->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    // Enviar mensaje al chat del admin
                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($redireccion->admin_id ?? null),
                        'mensaje' => 'bXBot: Aceptó Redirección.',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Redirección aceptada correctamente',
                        'url_destino' => $redireccion->url_destino
                    ]);
                }
            } elseif ($accion === 'rechazar_redireccion' && $redireccion_id) {
                $redireccion = RedireccionEnviada::where('id', $redireccion_id)
                    ->where('usuario_id', $usuario_id)
                    ->first();

                if ($redireccion) {
                    // Marcar como cerrado
                    $redireccion->update([
                        'estado' => 'cerrado',
                        'fecha_cerrado' => now()
                    ]);

                    // Enviar mensaje al chat del admin
                    MensajeAdmin::create([
                        'usuario_id' => $usuario_id,
                        'admin_id' => ($redireccion->admin_id ?? null),
                        'mensaje' => 'bXBot: Rechazó Redirección.',
                        'tipo_mensaje' => 'sin_input',
                        'ip_usuario' => request()->ip(),
                        'estado' => 'leido',
                        'fecha_envio' => now(),
                        'fecha_leido' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Redirección rechazada correctamente'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'Acción no válida o redirección no encontrada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error procesando redirecciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }
}
