<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\AdministracionControl;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware('permission:key.name')
     */
    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        $adminId = Session::get('admin_id');
        if (!$adminId) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión como administrador');
        }

        $admin = AdministracionControl::find($adminId);
        if (!$admin || (!$admin->is_admin && !$admin->is_mod)) {
            Session::forget('admin_id');
            return redirect()->route('login')->with('error', 'No tienes permisos de administrador');
        }

        // Superadmin siempre permitido
        if ($admin->is_admin === true) {
            return $next($request);
        }

        $perms = is_array($admin->permisos) ? $admin->permisos : [];
        $allowed = $perms[$permissionKey] ?? false;

        if (!$allowed) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Permiso denegado: ' . $permissionKey,
                ], 403);
            }
            return redirect()->back()->with('error', 'Permiso denegado: ' . $permissionKey);
        }

        return $next($request);
    }
}

