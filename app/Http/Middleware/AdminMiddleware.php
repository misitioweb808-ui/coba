<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\AdministracionControl;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si hay una sesión de admin activa
        $adminId = Session::get('admin_id');

        if (!$adminId) {
            return to_route('login')->with('error', 'Debes iniciar sesión como administrador');
        }

        // Verificar que el admin existe y está activo
        $admin = AdministracionControl::find($adminId);

        if (!$admin || (!$admin->is_admin && !$admin->is_mod)) {
            Session::forget('admin_id');
            return to_route('login')->with('error', 'No tienes permisos de administrador');
        }

        // Actualizar última fecha de ingreso
        $admin->update(['ultima_fecha_ingreso' => now()]);

        return $next($request);
    }
}
