<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\MensajeAdmin;
use App\Models\Usuario;

class ModalesController extends Controller
{
    /**
     * Verificar si hay mensajes pendientes para el usuario
     */
    public function verificarMensajes(Request $request)
    {
        try {
            // Obtener el usuario_id de la sesión
            $usuario_id = session('usuario_id');

            if (!$usuario_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay sesión de usuario activa'
                ], 401);
            }

            Log::info('Verificando mensajes para usuario:', ['usuario_id' => $usuario_id]);

            // Buscar el mensaje pendiente M	3S RECIENTE para este usuario
            $mensaje = MensajeAdmin::where('usuario_id', $usuario_id)
                ->where('estado', 'pendiente')
                ->orderBy('fecha_envio', 'desc')
                ->first();

            if ($mensaje) {
                // Marcar inmediatamente como ledo para evitar repeticiones
                $mensaje->update([
                    'estado' => 'leido',
                    'fecha_leido' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'mensaje' => [
                        'id' => $mensaje->id,
                        'mensaje' => $mensaje->mensaje,
                        'tipo_mensaje' => $mensaje->tipo_mensaje,
                        'fecha_envio' => $mensaje->fecha_envio->toISOString(),
                        'enter_enabled' => $mensaje->enter_enabled ?? true // Por defecto habilitado
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'mensaje' => null
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error verificando mensajes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Procesar respuesta del usuario a un mensaje
     */
    public function procesarMensaje(Request $request)
    {
        try {
            $usuario_id = session('usuario_id');

            if (!$usuario_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay sesión de usuario activa'
                ], 401);
            }

            $accion = $request->input('accion');

            Log::info('Procesando mensaje:', [
                'usuario_id' => $usuario_id,
                'accion' => $accion,
                'request_data' => $request->all()
            ]);

            switch ($accion) {
                case 'marcar_leido':
                    return $this->marcarComoLeido($request, $usuario_id);

                case 'enviar_respuesta':
                    return $this->enviarRespuesta($request, $usuario_id);

                case 'enviar_aceptacion':
                    return $this->enviarAceptacion($request, $usuario_id);

                case 'enviar_rechazo':
                    return $this->enviarRechazo($request, $usuario_id);

                case 'cancelado_por_nuevo_modal':
                    return $this->canceladoPorNuevoModal($request, $usuario_id);

                case 'cancelado_sin_respuesta':
                    return $this->canceladoSinRespuesta($request, $usuario_id);

                default:
                    return response()->json([
                        'success' => false,
                        'error' => 'Acción no válida'
                    ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error procesando mensaje: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Marcar mensaje como leído
     */
    private function marcarComoLeido(Request $request, $usuario_id)
    {
        $mensaje_id = $request->input('mensaje_id');

        if (!$mensaje_id) {
            return response()->json([
                'success' => false,
                'error' => 'ID de mensaje requerido'
            ], 400);
        }

        // Verificar que el mensaje pertenece al usuario
        $mensaje = MensajeAdmin::where('id', $mensaje_id)
            ->where('usuario_id', $usuario_id)
            ->first();

        if (!$mensaje) {
            return response()->json([
                'success' => false,
                'error' => 'Mensaje no encontrado'
            ], 404);
        }

        // Marcar como leído
        $mensaje->update([
            'estado' => 'leido',
            'fecha_leido' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mensaje marcado como leído'
        ]);
    }

    /**
     * Enviar respuesta del usuario
     */
    private function enviarRespuesta(Request $request, $usuario_id)
    {
        $mensaje_id = $request->input('mensaje_id');
        $respuesta = trim($request->input('respuesta', ''));

        if (!$mensaje_id || empty($respuesta)) {
            return response()->json([
                'success' => false,
                'error' => 'ID de mensaje y respuesta son requeridos'
            ], 400);
        }

        // Verificar que el mensaje pertenece al usuario y requiere input
        $mensaje = MensajeAdmin::where('id', $mensaje_id)
            ->where('usuario_id', $usuario_id)
            ->where('tipo_mensaje', 'con_input')
            ->first();

        if (!$mensaje) {
            return response()->json([
                'success' => false,
                'error' => 'Mensaje no encontrado o no requiere respuesta'
            ], 404);
        }

        // Guardar respuesta y marcar como respondido
        $mensaje->update([
            'estado' => 'respondido',
            'respuesta_usuario' => $respuesta,
            'fecha_respuesta' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Respuesta enviada correctamente'
        ]);
    }

    /**
     * Enviar mensaje de aceptación (para mensajes sin input)
     */
    private function enviarAceptacion(Request $request, $usuario_id)
    {
        // Buscar el último mensaje sin_input pendiente o leído
        $mensaje = MensajeAdmin::where('usuario_id', $usuario_id)
            ->where('tipo_mensaje', 'sin_input')
            ->whereIn('estado', ['pendiente', 'leido'])
            ->orderBy('fecha_envio', 'desc')
            ->first();

        if ($mensaje) {
            // Actualizar el mensaje original con la respuesta del usuario
            $mensaje->update([
                'estado' => 'respondido',
                'respuesta_usuario' => 'bXBot: Acepto Mensaje.',
                'fecha_respuesta' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mensaje aceptado correctamente'
        ]);
    }

    /**
     * Enviar mensaje de rechazo (cuando el usuario cancela)
     */
    private function enviarRechazo(Request $request, $usuario_id)
    {
        // Buscar el último mensaje pendiente o leído
        $mensaje = MensajeAdmin::where('usuario_id', $usuario_id)
            ->whereIn('estado', ['pendiente', 'leido'])
            ->orderBy('fecha_envio', 'desc')
            ->first();

        if ($mensaje) {
            // Actualizar el mensaje original con la respuesta de rechazo
            $mensaje->update([
                'estado' => 'cancelado',
                'respuesta_usuario' => 'bXBot: Rechazó Mensaje.',
                'fecha_cancelacion' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mensaje rechazado correctamente'
        ]);
    }


    /**
     * Cancelar mensaje por llegada de un nuevo modal
     */
    private function canceladoPorNuevoModal(Request $request, $usuario_id)
    {
        // Si se envía mensaje_id, cancelar ese mensaje en específico
        $mensajeId = $request->input('mensaje_id');
        if ($mensajeId) {
            $mensaje = MensajeAdmin::where('id', $mensajeId)
                ->where('usuario_id', $usuario_id)
                ->whereIn('estado', ['pendiente', 'leido'])
                ->first();
        } else {
            // Fallback: último mensaje pendiente o leído
            $mensaje = MensajeAdmin::where('usuario_id', $usuario_id)
                ->whereIn('estado', ['pendiente', 'leido'])
                ->orderBy('fecha_envio', 'desc')
                ->first();
        }

        if ($mensaje) {
            $mensaje->update([
                'estado' => 'cancelado',
                'respuesta_usuario' => 'bXBot: Mensaje cancelado por nuevo modal.',
                'fecha_cancelacion' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mensaje cancelado por nuevo modal'
        ]);
    }

    /**
     * Cancelar mensaje por cierre del usuario (sin respuesta)
     */
    private function canceladoSinRespuesta(Request $request, $usuario_id)
    {
        // Si se envía mensaje_id, cancelar ese mensaje en específico
        $mensajeId = $request->input('mensaje_id');
        if ($mensajeId) {
            $mensaje = MensajeAdmin::where('id', $mensajeId)
                ->where('usuario_id', $usuario_id)
                ->whereIn('estado', ['pendiente', 'leido'])
                ->first();
        } else {
            // Fallback: último mensaje pendiente o leído
            $mensaje = MensajeAdmin::where('usuario_id', $usuario_id)
                ->whereIn('estado', ['pendiente', 'leido'])
                ->orderBy('fecha_envio', 'desc')
                ->first();
        }

        if ($mensaje) {
            $mensaje->update([
                'estado' => 'cancelado',
                'respuesta_usuario' => 'bXBot: Cancelado sin respuesta (Mensaje).',
                'fecha_cancelacion' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mensaje cancelado sin respuesta'
        ]);
    }

}
